<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Config;
use App\Models\EventStatus;
use App\Models\GalleryStatus;
use App\Models\Role;
use App\Models\TokenType;
use App\Repositories\ClientRepository;
use App\Repositories\EventRepository;
use App\Repositories\PortfolioRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\UserRepository;
use App\Services\GalleryService;
use App\Services\PhotoUploadService;
use App\Services\PublicImageService;
use App\Services\SettingsService;
use App\Services\StorageService;
use App\Services\TokenService;

require_once __DIR__ . '/ImageFactory.php';
require_once __DIR__ . '/SettingsSeeder.php';

/**
 * Demonstration data: a complete, working example of the whole workflow.
 *
 * Everything is invented. No real name, e-mail address or photograph is used,
 * and the generated images are gradients, not pictures of people.
 */
final class DemoSeeder
{
    private const DEMO_PASSWORD = 'demo-photographe-2026';
    private const GALLERY_PASSWORD = 'jeanmarie2026';

    /** @return array<int, string> Lines describing what was created. */
    public function run(): array
    {
        $summary = [];

        (new SettingsSeeder())->run();
        $summary[] = 'Paramètres du site initialisés.';

        $images = new ImageFactory();

        if (!$images->isAvailable()) {
            throw new \RuntimeException(
                "L'extension GD est requise pour générer les images de démonstration."
            );
        }

        (new StorageService())->ensureReady();

        $summary[] = $this->seedUser();
        $summary[] = $this->seedBranding($images);
        $summary = array_merge($summary, $this->seedServices());
        $summary = array_merge($summary, $this->seedPortfolio($images));
        $summary = array_merge($summary, $this->seedGallery($images));

        return $summary;
    }

    private function seedUser(): string
    {
        $users = new UserRepository();
        $email = 'photographe@example.com';

        if ($users->emailExists($email)) {
            return 'Compte de démonstration déjà présent (' . $email . ').';
        }

        $users->create('Camille Rivière', $email, self::DEMO_PASSWORD, Role::SUPER_ADMIN);

        return sprintf('Compte photographe : %s / %s', $email, self::DEMO_PASSWORD);
    }

    /**
     * The hero and the portrait.
     *
     * A photographer's homepage is judged on its first image. Shipping the
     * demo without one leaves a wall of white above the fold, which reads as
     * a broken site rather than an empty one.
     */
    private function seedBranding(ImageFactory $images): string
    {
        $settings = new SettingsService();
        $publicImages = new PublicImageService();
        $root = $publicImages->publicRoot() . '/site';

        if (trim((string) $settings->get('hero_image', '')) !== '') {
            return 'Images de marque déjà présentes.';
        }

        // Wide and dark: the hero carries white text over it.
        $images->create($root . '/demo-hero.jpg', 2400, 1350, 1);
        $settings->set('hero_image', 'assets/uploads/site/demo-hero.jpg', 'site');

        // Portrait orientation for the about page.
        $images->create($root . '/demo-portrait.jpg', 1200, 1600, 6);
        $settings->set('about_image', 'assets/uploads/site/demo-portrait.jpg', 'site');

        return 'Image d\'accueil et portrait générés.';
    }

    /** @return array<int, string> */
    private function seedServices(): array
    {
        $services = new ServiceRepository();

        if ($services->count() > 0) {
            return ['Prestations déjà présentes.'];
        }

        $definitions = [
            [
                'title'    => 'Reportage de mariage',
                'summary'  => 'Une journée complète, de la préparation à la fin de soirée.',
                'price'    => 2400.00,
                'duration' => 'Journée complète',
                'items'    => "Reportage de 12 heures\nGalerie privée en ligne\n600 à 900 photographies retouchées\nFichiers haute définition téléchargeables\nLivraison sous trois semaines",
                'text'     => "Je vous suis du matin au soir, sans vous demander de poser. Le reportage est documentaire : "
                    . "je photographie ce qui se passe, et je réserve un moment court pour les portraits "
                    . "que vous souhaitez.",
            ],
            [
                'title'    => 'Séance portrait',
                'summary'  => 'Une heure et demie en extérieur ou en studio.',
                'price'    => 380.00,
                'duration' => '1 h 30',
                'items'    => "Séance de 90 minutes\nRepérage du lieu inclus\n30 photographies retouchées\nGalerie privée\nLivraison sous une semaine",
                'text'     => "Un portrait réussi demande d'abord d'être à l'aise. La séance commence donc par "
                    . "une conversation, pas par un appareil photo.",
            ],
            [
                'title'    => 'Événement d\'entreprise',
                'summary'  => 'Conférences, lancements, portraits d\'équipe.',
                'price'    => 890.00,
                'duration' => 'Demi-journée',
                'items'    => "Couverture de 4 heures\nPortraits d'équipe\nGalerie privée avec accès partagé\nDroits d'usage commercial inclus\nLivraison sous cinq jours",
                'text'     => "Des images utilisables immédiatement pour votre communication, livrées vite "
                    . "et dans les formats dont vos équipes ont besoin.",
            ],
        ];

        foreach ($definitions as $index => $definition) {
            $services->insert([
                'title'        => $definition['title'],
                'slug'         => str_slug($definition['title']),
                'summary'      => $definition['summary'],
                'description'  => $definition['text'],
                'price_from'   => $definition['price'],
                'currency'     => 'EUR',
                'duration'     => $definition['duration'],
                'deliverables' => $definition['items'],
                'sort_order'   => $index + 1,
                'status'       => 'published',
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
        }

        return [sprintf('%d prestations créées.', count($definitions))];
    }

    /** @return array<int, string> */
    private function seedPortfolio(ImageFactory $images): array
    {
        $portfolio = new PortfolioRepository();

        if ($portfolio->count() > 0) {
            return ['Portfolio déjà rempli.'];
        }

        $publicImages = new PublicImageService();
        $root = $publicImages->publicRoot() . '/portfolio';

        // Real-sounding titles, not "Image 1". A demo whose captions read
        // like placeholders is a demo the photographer cannot show anyone,
        // and the category is already displayed beside the title.
        $categories = [
            'Mariage'   => ['Premiers regards', 'La cérémonie', 'Sortie des invités', 'Dernière danse'],
            'Portrait'  => ['Lumière de fenêtre', 'En extérieur', 'Atelier'],
            'Événement' => ['Discours d\'ouverture', 'Dans la salle'],
            'Corporate' => ['Portrait d\'équipe', 'Lancement de produit'],
        ];

        $seed = 1;
        $total = 0;
        $order = 1;

        foreach ($categories as $name => $titles) {
            $categoryId = $portfolio->createCategory([
                'name'       => $name,
                'slug'       => str_slug($name),
                'sort_order' => $order,
                'status'     => 'published',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            foreach ($titles as $position => $title) {
                $index = $position + 1;
                $portrait = $seed % 3 === 0;
                $width = $portrait ? 1200 : 1600;
                $height = $portrait ? 1600 : 1067;

                $basename = 'demo-' . str_slug($name) . '-' . $index;

                $images->create($root . '/' . $basename . '.jpg', $width, $height, $seed);
                $images->create(
                    $root . '/' . $basename . '-thumb.jpg',
                    (int) round($width / 2),
                    (int) round($height / 2),
                    $seed
                );

                $portfolio->insert([
                    'category_id'    => $categoryId,
                    'title'          => $title,
                    'description'    => null,
                    'image_path'     => 'assets/uploads/portfolio/' . $basename . '.jpg',
                    'thumbnail_path' => 'assets/uploads/portfolio/' . $basename . '-thumb.jpg',
                    'width'          => $width,
                    'height'         => $height,
                    'featured'       => $index === 1 ? 1 : 0,
                    'sort_order'     => $total + 1,
                    'status'         => 'published',
                    'created_at'     => date('Y-m-d H:i:s'),
                    'updated_at'     => date('Y-m-d H:i:s'),
                ]);

                $seed++;
                $total++;
            }

            $order++;
        }

        return [sprintf('%d images de portfolio dans %d catégories.', $total, count($categories))];
    }

    /**
     * Build the full workflow: client → event → gallery → photos → links.
     *
     * @return array<int, string>
     */
    private function seedGallery(ImageFactory $images): array
    {
        $clients = new ClientRepository();

        if ($clients->count() > 0) {
            return ['Clients de démonstration déjà présents.'];
        }

        $summary = [];

        $clientId = $clients->insert([
            'first_name' => 'Jean',
            'last_name'  => 'Martin',
            'email'      => 'jean.martin@example.com',
            'phone'      => '+33 6 12 34 56 78',
            'company'    => null,
            'notes'      => 'Client de démonstration. Aucune donnée réelle.',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $secondClientId = $clients->insert([
            'first_name' => 'Sophie',
            'last_name'  => 'Bernard',
            'email'      => 'sophie.bernard@example.com',
            'phone'      => '+33 6 98 76 54 32',
            'company'    => 'Studio Bernard',
            'notes'      => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $events = new EventRepository();

        $eventId = $events->insert([
            'client_id'   => $clientId,
            'title'       => 'Mariage Jean & Marie',
            'description' => 'Cérémonie et réception, domaine en Provence.',
            'event_type'  => 'wedding',
            'event_date'  => date('Y-m-d', strtotime('-3 weeks')),
            'location'    => 'Domaine des Oliviers, Aix-en-Provence',
            'status'      => EventStatus::COMPLETED,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $events->insert([
            'client_id'   => $secondClientId,
            'title'       => 'Portraits corporate — Studio Bernard',
            'description' => 'Portraits d\'équipe pour le site institutionnel.',
            'event_type'  => 'corporate',
            'event_date'  => date('Y-m-d', strtotime('+2 weeks')),
            'location'    => 'Lyon',
            'status'      => EventStatus::ACTIVE,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $summary[] = '2 clients et 2 événements créés.';

        $galleryService = new GalleryService();

        // Downloads start disabled: this is the product's intended flow —
        // share the view link first, open downloads later.
        $created = $galleryService->create([
            'event_id'          => $eventId,
            'title'             => 'Mariage Jean & Marie',
            'description'       => "Voici vos photographies. Prenez le temps de les regarder : "
                . "le lien de téléchargement vous sera transmis ensuite.",
            'password'          => self::GALLERY_PASSWORD,
            'watermark_enabled' => true,
            'download_enabled'  => true,
            'selection_enabled' => true,
            'status'            => GalleryStatus::ACTIVE,
            'expires_at'        => null,
        ]);

        $galleryId = $created['gallery_id'];

        $uploads = new PhotoUploadService();
        $gallery = (new \App\Repositories\GalleryRepository())->find($galleryId);
        $photoCount = 12;

        for ($index = 1; $index <= $photoCount; $index++) {
            $portrait = $index % 4 === 0;
            $temporary = $images->createTemporary(
                $portrait ? 1400 : 2000,
                $portrait ? 2000 : 1333,
                100 + $index
            );

            // Fed through the real upload pipeline so the demo exercises
            // validation, storage and preview generation exactly as a real
            // upload would.
            $uploads->store([
                'name'     => sprintf('IMG_%04d.jpg', 1000 + $index),
                'type'     => 'image/jpeg',
                'tmp_name' => $temporary,
                'error'    => UPLOAD_ERR_OK,
                'size'     => (int) filesize($temporary),
            ], $gallery);
        }

        $summary[] = sprintf('Galerie « Mariage Jean & Marie » avec %d photographies.', $photoCount);
        $summary[] = 'Mot de passe de la galerie : ' . self::GALLERY_PASSWORD;

        $tokens = new TokenService();
        $active = $galleryService->activeTokens($galleryId);
        $base = rtrim((string) Config::get('app.url'), '/');

        foreach (['view' => TokenType::VIEW, 'download' => TokenType::DOWNLOAD] as $key => $type) {
            $raw = $active[$key] === null ? null : $tokens->revealRawToken($active[$key]);

            $summary[] = $raw === null
                ? sprintf('Lien %s : définissez APP_KEY puis régénérez-le depuis l\'administration.', $key)
                : sprintf('Lien %s : %s', $key, $tokens->urlFor($type, $raw));
        }

        unset($base);

        return $summary;
    }
}
