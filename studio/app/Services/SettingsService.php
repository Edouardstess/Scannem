<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SettingsRepository;

/**
 * Site settings with defaults.
 *
 * Read on every request, so the values are cached for the request's lifetime;
 * a fresh install with no settings rows still renders a complete site from
 * the defaults below.
 */
final class SettingsService
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    public const DEFAULTS = [
        'studio_name'        => 'L\'ENFANT VISUAL',
        'photographer_name'  => 'Photographe',
        'tagline'            => 'Capturer les moments qui méritent de rester.',
        'speciality'         => 'Mariage · Portrait · Éditorial',
        'hero_title'         => 'Capturer les moments qui méritent de rester.',
        'hero_subtitle'      => 'Photographie de mariage, de portrait et d\'événement.',
        'hero_image'         => '',
        'about_title'        => 'À propos',
        'about_text'         => '',
        'about_image'        => '',
        'contact_email'      => '',
        'contact_phone'      => '',
        'contact_address'    => '',
        'social_instagram'   => '',
        'social_facebook'    => '',
        'social_linkedin'    => '',
        'social_pinterest'   => '',
        'footer_text'        => '',
        'meta_description'   => '',
        'watermark_text'     => '',
        'color_primary'      => '#1a1a1a',
        'color_accent'       => '#b08d57',
        'color_background'   => '#fbfaf8',
        'logo_path'          => '',
        'booking_enabled'    => true,
        'client_area_enabled'=> true,
        'testimonials'       => [],
        'process_steps'      => [],
    ];

    public function __construct(private ?SettingsRepository $repository = null)
    {
        $this->repository = $repository ?? new SettingsRepository();
    }

    public static function make(): self
    {
        return new self();
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        try {
            $stored = $this->repository->allValues();
        } catch (\Throwable) {
            // Before the first migration there is no settings table; the site
            // must still render (the installer page, most importantly).
            $stored = [];
        }

        self::$cache = array_merge(self::DEFAULTS, $stored);

        return self::$cache;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return $all[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public function set(string $key, mixed $value, string $group = 'general'): void
    {
        $type = match (true) {
            is_bool($value)  => 'boolean',
            is_int($value)   => 'integer',
            is_array($value) => 'json',
            default          => 'string',
        };

        $this->repository->set($key, $value, $group, $type);
        self::$cache = null;
    }

    /** @param array<string, mixed> $values */
    public function setMany(array $values, string $group = 'general'): void
    {
        $this->repository->setMany($values, $group);
        self::$cache = null;
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    public function grouped(): array
    {
        return $this->repository->grouped();
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    /** Text used for preview watermarks: an explicit value, else the studio name. */
    public function watermarkText(): string
    {
        $explicit = trim((string) $this->get('watermark_text', ''));

        return $explicit !== '' ? $explicit : trim((string) $this->get('studio_name', ''));
    }
}
