<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PortfolioRepository;

/**
 * Content of the immersive homepage (template « Site Immersif »).
 *
 * Builds the same structure as the template's window.SITE_CONTENT, but from
 * the database: texts from the « immersive » setting (edited in
 * Admin → Page d'accueil), images from the published portfolio — featured
 * photos first. Nothing is hard-coded except the fallbacks used before the
 * photographer has published any work.
 */
final class ImmersiveHomeService
{
    /** Exactly what the template's masonry is drawn for. */
    public const PROOF_COUNT = 8;
    public const FLOATER_COUNT = 10;
    public const TRAIL_COUNT = 20;

    /** Editable texts and their defaults. */
    public const DEFAULTS = [
        'theme'          => 'light',
        'accent'         => '#f8cf9f',
        'kicker'         => '',
        'hook_line1'     => 'Vos plus beaux moments,',
        'hook_line2a'    => 'gardés',
        'hook_line2b'    => 'pour toujours.',
        'positioning'    => 'Mariage, portrait, famille et événement.',
        'manifesto'      => 'Je photographie ce qui ne se rejoue pas : un regard, un rire, une main qui tremble avant le oui. '
            . 'Pas de poses figées, seulement des [[instants vrais]], que vous garderez toute votre vie.',
        'proof_kicker'   => 'TRAVAUX CHOISIS',
        'proof_title'    => 'Des histoires en images',
        'proof_sub'      => 'Mariages, portraits, familles, entreprises : chaque séance a sa propre lumière.',
        'proof_meta'     => '',
        'motto_kicker'   => 'CE QUI GUIDE CHAQUE SÉANCE',
        'motto_1_word'   => 'Lumière',
        'motto_1_hint'   => 'Chercher la plus belle, à chaque heure du jour.',
        'motto_2_word'   => 'Émotion',
        'motto_2_hint'   => 'Saisir ce qui se passe vraiment, sans le mettre en scène.',
        'motto_3_word'   => 'Mémoire',
        'motto_3_hint'   => 'Des images faites pour traverser les années.',
        'steps_intro_a'  => 'Une',
        'steps_intro_b'  => 'séance,',
        'steps_intro_c'  => '3 étapes.',
        'steps_cta'      => 'Réserver →',
        'step_1_name'    => 'On se parle',
        'step_1_desc'    => 'Un échange pour comprendre votre projet, le lieu, l’ambiance et ce que vous attendez des images.',
        'step_2_name'    => 'Le jour J',
        'step_2_desc'    => 'Je photographie sans vous diriger, et je reste disponible pour les portraits que vous souhaitez.',
        'step_3_name'    => 'Votre galerie',
        'step_3_desc'    => 'Vos photos retouchées arrivent dans une galerie privée, à partager et à télécharger en haute définition.',
        'quote_kicker'   => 'MARIAGE — 180 INVITÉS',
        'quote_figure'   => '3',
        'quote_unit'     => 'sem.',
        'quote_text'     => 'Nous avons reçu notre galerie trois semaines après le mariage. Toute la famille a pu voir et '
            . 'télécharger les photos, même ceux qui vivent loin.',
        'quote_author'   => 'SARAH ET MARC — MARIÉS EN 2025',
        'objection_1'    => 'Pas de poses figées.',
        'objection_2'    => 'Pas de surprise sur le prix.',
        'objection_3'    => 'Pas de photos perdues.',
        'finale'         => 'Juste vos',
        'pill'           => 'souvenirs.',
        'contact_kicker' => 'UN PROJET EN TÊTE ?',
        'reassurance'    => 'RÉPONSE SOUS 48 H — DEVIS GRATUIT, SANS ENGAGEMENT',
        'signature'      => 'PHOTOGRAPHIÉ AVEC SOIN',
        'nav_proof'      => 'TRAVAUX',
        'nav_steps'      => 'ÉTAPES',
        'nav_cta'        => 'RÉSERVER',
    ];

    /** @var array<int, array<string, mixed>>|null */
    private ?array $works = null;

    public function __construct(
        private array $settings,
        private ?PortfolioRepository $portfolio = null
    ) {
        $this->portfolio = $portfolio ?? new PortfolioRepository();
    }

    /** Stored texts over the defaults; blank fields fall back too. */
    public function texts(): array
    {
        $stored = is_array($this->settings['immersive'] ?? null) ? $this->settings['immersive'] : [];
        $texts = self::DEFAULTS;

        foreach (self::DEFAULTS as $key => $default) {
            $value = $stored[$key] ?? null;

            if (is_scalar($value) && trim((string) $value) !== '') {
                $texts[$key] = trim((string) $value);
            }
        }

        return $texts;
    }

    /**
     * The template's SITE_CONTENT, filled from the database.
     *
     * @return array<string, mixed>
     */
    public function content(): array
    {
        $t = $this->texts();
        $s = $this->settings;
        $name = first_filled($s['studio_name'] ?? '', 'L’ENFANT VISUAL');
        $works = $this->works();
        $thumbs = array_map(static fn (array $w): string => $w['thumb'], $works);

        $hookImage = first_filled($s['hero_image'] ?? '', $works[0]['full'] ?? '');
        $processImage = $works[1]['full'] ?? ($works[0]['full'] ?? '');

        return [
            'brand' => [
                'name'        => $name,
                'title'       => $name . ' — ' . first_filled($s['speciality'] ?? '', 'Photographe'),
                'description' => first_filled($s['meta_description'] ?? '', $s['tagline'] ?? ''),
                'kicker'      => first_filled($t['kicker'], mb_strtoupper($name) . ' — PHOTOGRAPHE'),
                'copyright'   => '© ' . date('Y') . ' — ' . mb_strtoupper($name),
                'signature'   => $t['signature'],
                'socials'     => $this->socials(),
            ],
            'nav' => ['proof' => $t['nav_proof'], 'universes' => $t['nav_steps'], 'cta' => $t['nav_cta']],
            'hook' => [
                'line1'    => $t['hook_line1'],
                'line2a'   => $t['hook_line2a'],
                'line2b'   => $t['hook_line2b'],
                'image'    => $hookImage !== '' ? $this->url($hookImage) : $this->placeholder('hero.jpg'),
                'imageAlt' => 'Photographie de ' . $name,
                'floaters' => $this->cycle($thumbs, self::FLOATER_COUNT, 'fl-%02d.jpg', 1),
            ],
            'positioning' => $t['positioning'],
            'manifesto'   => ['text' => $t['manifesto']],
            'proof' => [
                'layout'   => 'masonry',
                'kicker'   => $t['proof_kicker'],
                'title'    => $t['proof_title'],
                'sub'      => $t['proof_sub'],
                'meta'     => first_filled($t['proof_meta'], $this->proofMeta($works)),
                'projects' => $this->projects($works),
            ],
            'motto' => [
                'kicker' => $t['motto_kicker'],
                'words'  => array_map(static fn (int $i): array => [
                    'word' => $t["motto_{$i}_word"],
                    'hint' => $t["motto_{$i}_hint"],
                ], [1, 2, 3]),
            ],
            'universes' => [
                'introA' => $t['steps_intro_a'],
                'introB' => $t['steps_intro_b'],
                'introC' => $t['steps_intro_c'],
                'cta'    => $t['steps_cta'],
                'image'  => $processImage !== '' ? $this->url($processImage) : $this->placeholder('process.jpg'),
                'items'  => array_map(static fn (int $i): array => [
                    'name' => $t["step_{$i}_name"],
                    'meta' => sprintf('ÉTAPE — %02d', $i),
                    'desc' => $t["step_{$i}_desc"],
                ], [1, 2, 3]),
            ],
            'testimonial' => [
                'kicker' => $t['quote_kicker'],
                'figure' => $t['quote_figure'],
                'unit'   => $t['quote_unit'],
                'quote'  => $t['quote_text'],
                'author' => $t['quote_author'],
            ],
            'objections' => [
                'items'  => [$t['objection_1'], $t['objection_2'], $t['objection_3']],
                'finale' => $t['finale'],
                'pill'   => $t['pill'],
            ],
            'contact' => [
                'kicker'      => $t['contact_kicker'],
                'email'       => trim((string) ($s['contact_email'] ?? '')),
                'reassurance' => $t['reassurance'],
            ],
            'trail' => $this->cycle($thumbs, self::TRAIL_COUNT, 'tr-%02d.jpg', 3),
        ];
    }

    public function theme(): string
    {
        return $this->texts()['theme'] === 'dark' ? 'dark' : 'light';
    }

    public function accent(): string
    {
        $accent = $this->texts()['accent'];

        return preg_match('/^#[0-9a-f]{6}$/i', $accent) === 1 ? $accent : self::DEFAULTS['accent'];
    }

    /** Text colour on the accent: dark ink on a light accent, white on a dark one. */
    public function accentInk(): string
    {
        $hex = ltrim($this->accent(), '#');
        [$r, $g, $b] = array_map('hexdec', str_split($hex, 2));
        $luminance = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;

        return $luminance > 0.55 ? '#1a1915' : '#ffffff';
    }

    /** Whether the page shows the photographer's work or the shipped placeholders. */
    public function usesPlaceholders(): bool
    {
        return $this->works() === [];
    }

    /**
     * Published portfolio, featured first, as public URLs.
     *
     * @return array<int, array{full: string, thumb: string, title: string, meta: string, year: int}>
     */
    private function works(): array
    {
        if ($this->works !== null) {
            return $this->works;
        }

        $items = [];
        $seen = [];

        foreach ([$this->portfolio->featured(40), $this->portfolio->publishedItems(null, 60)] as $batch) {
            foreach ($batch as $item) {
                $id = (int) $item['id'];

                if (isset($seen[$id]) || (string) ($item['image_path'] ?? '') === '') {
                    continue;
                }

                $seen[$id] = true;
                $year = (int) substr((string) ($item['created_at'] ?? ''), 0, 4);
                $category = trim((string) ($item['category_name'] ?? ''));
                $items[] = [
                    'full'  => (string) $item['image_path'],
                    'thumb' => first_filled($item['thumbnail_path'] ?? '', $item['image_path']),
                    'title' => (string) $item['title'],
                    'meta'  => mb_strtoupper(first_filled($category, 'Photographie')) . ($year > 0 ? ' — ' . $year : ''),
                    'year'  => $year,
                ];
            }
        }

        return $this->works = $items;
    }

    /** @return array<int, array{img: string, title: string, meta: string}> */
    private function projects(array $works): array
    {
        if ($works === []) {
            $titles = [
                ['Mariage au jardin', 'MARIAGE — 2025'], ['Portrait en lumière douce', 'PORTRAIT — 2025'],
                ['Dimanche en famille', 'FAMILLE — 2024'], ['Premiers jours', 'NAISSANCE — 2026'],
                ['Lancement de marque', 'ENTREPRISE — 2025'], ['Soirée de gala', 'ÉVÉNEMENT — 2024'],
                ['Séance en studio', 'STUDIO — 2026'], ['La cérémonie', 'MARIAGE — 2026'],
            ];

            return array_map(fn (array $t, int $i): array => [
                'img' => $this->placeholder('pr-' . ($i + 1) . '.jpg'), 'title' => $t[0], 'meta' => $t[1],
            ], $titles, array_keys($titles));
        }

        return array_map(fn (array $w): array => [
            'img' => $this->url($w['thumb']), 'title' => $w['title'], 'meta' => $w['meta'],
        ], array_slice($works, 0, self::PROOF_COUNT));
    }

    private function proofMeta(array $works): string
    {
        $shown = array_slice($works, 0, self::PROOF_COUNT);
        $years = array_filter(array_column($shown, 'year'));

        if ($shown === [] || $years === []) {
            return 'SÉLECTION — ' . date('Y');
        }

        $range = min($years) === max($years) ? (string) max($years) : min($years) . ' → ' . max($years);

        return sprintf('%d %s — %s', count($shown), count($shown) > 1 ? 'SÉANCES' : 'SÉANCE', $range);
    }

    /**
     * Fill a slot list from the portfolio, cycling when there are fewer photos
     * than slots, starting at an offset so the hero cloud does not simply
     * repeat the selection below it.
     *
     * @param array<int, string> $paths
     * @return array<int, string>
     */
    private function cycle(array $paths, int $count, string $placeholderPattern, int $offset): array
    {
        $urls = [];

        for ($i = 0; $i < $count; $i++) {
            $urls[] = $paths === []
                ? $this->placeholder(sprintf($placeholderPattern, $i + 1))
                : $this->url($paths[($i + $offset) % count($paths)]);
        }

        return $urls;
    }

    /** @return array<int, array{label: string, url: string}> */
    private function socials(): array
    {
        $links = [];

        foreach (['social_instagram' => 'INSTAGRAM', 'social_facebook' => 'FACEBOOK',
                  'social_linkedin' => 'LINKEDIN', 'social_pinterest' => 'PINTEREST'] as $key => $label) {
            $url = trim((string) ($this->settings[$key] ?? ''));

            if (preg_match('#^https://#i', $url) === 1) {
                $links[] = ['label' => $label . ' ↗', 'url' => $url];
            }
        }

        return $links;
    }

    private function url(string $relative): string
    {
        return url(ltrim($relative, '/'));
    }

    private function placeholder(string $file): string
    {
        return asset('immersif/placeholders/' . $file);
    }
}
