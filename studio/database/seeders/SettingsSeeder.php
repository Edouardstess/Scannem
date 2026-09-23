<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Repositories\SettingsRepository;
use App\Services\SettingsService;

/**
 * Default site settings.
 *
 * Idempotent: it only writes a key that has no value yet, so re-running the
 * installer never overwrites what the photographer has typed in.
 */
final class SettingsSeeder
{
    public function run(): void
    {
        $settings = new SettingsService();

        // The stored rows, not SettingsService::all(): that one merges in the
        // code defaults, so every key would look like it already had a value
        // and nothing would ever be written.
        $existing = (new SettingsRepository())->allValues();

        $defaults = [
            'site' => [
                'studio_name'       => 'Atelier Lumière',
                'photographer_name' => 'Camille Rivière',
                'tagline'           => 'Capturer les moments qui méritent de rester.',
                'speciality'        => 'Mariage · Portrait · Éditorial',
                'hero_title'        => 'Capturer les moments qui méritent de rester.',
                'hero_subtitle'     => 'Photographie de mariage, de portrait et d\'événement, en France et ailleurs.',
                'about_title'       => 'Photographier ce qui ne se rejoue pas',
                'about_text'        => "Je photographie depuis douze ans les moments qui ne se répètent pas : "
                    . "un regard pendant une cérémonie, une main qui tremble avant un discours, "
                    . "la lumière de fin de journée sur un visage.\n\n"
                    . "Mon travail est documentaire plus que posé. Je m'efface, j'observe, "
                    . "et je déclenche quand quelque chose de vrai se produit.\n\n"
                    . "Chaque reportage est livré dans une galerie privée : vous y retrouvez vos photographies "
                    . "en haute définition, consultables depuis n'importe quel appareil, "
                    . "et téléchargeables quand vous le souhaitez.",
                'meta_description'  => 'Photographe de mariage, de portrait et d\'événement. '
                    . 'Galeries privées, livraison en haute définition.',
                'footer_text'       => 'Photographe de mariage, de portrait et d\'événement.',
                'color_primary'     => '#1a1a1a',
                'color_accent'      => '#b08d57',
                'color_background'  => '#fbfaf8',
                'booking_enabled'   => true,
                'client_area_enabled' => true,
            ],
            'contact' => [
                'contact_email'   => 'contact@example.com',
                'contact_phone'   => '+33 6 00 00 00 00',
                'contact_address' => 'Paris, France',
            ],
            'home' => [
                'process_steps' => [
                    ['title' => 'Premier échange', 'text' => 'Un appel pour comprendre votre projet, vos contraintes et ce que vous attendez des images.'],
                    ['title' => 'Repérage et préparation', 'text' => 'Lieux, horaires, lumière : tout est calé avant le jour J pour que je puisse me concentrer sur vous.'],
                    ['title' => 'Le reportage', 'text' => 'Je photographie sans diriger, en restant disponible pour les portraits que vous souhaitez.'],
                    ['title' => 'Votre galerie privée', 'text' => 'Sous trois semaines, vos photographies retouchées dans une galerie privée, à partager et à télécharger.'],
                ],
                'testimonials' => [
                    ['text' => 'Nous avions peur de poser. Nous ne l\'avons jamais fait : les photos racontent exactement la journée que nous avons vécue.', 'author' => 'Léa et Thomas, mariage'],
                    ['text' => 'La galerie privée a beaucoup plu à nos familles. Tout le monde a pu voir les photos puis les télécharger sans créer de compte.', 'author' => 'Famille Moreau'],
                ],
            ],
        ];

        foreach ($defaults as $group => $values) {
            foreach ($values as $key => $value) {
                // An empty string counts as "not set yet": it is what a fresh
                // install has before anyone has filled the field in.
                $current = $existing[$key] ?? null;

                if ($current !== null && $current !== '' && $current !== []) {
                    continue;
                }

                $settings->set($key, $value, $group);
            }
        }

        SettingsService::flush();
    }
}
