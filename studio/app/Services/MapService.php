<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Google Maps location of the studio.
 *
 * No API key is needed: the map is Google's public embed, and the buttons use
 * the documented "Maps URLs" (https://developers.google.com/maps/documentation/urls).
 *
 * Two ways to place the pin, from the settings:
 *  - map_query: an address, a place name or "lat, lng" coordinates;
 *  - map_embed_url: the exact map copied from Google Maps (Share → Embed a
 *    map), which wins for the displayed map when present.
 * Without either, the contact address is used.
 */
final class MapService
{
    /** Hosts a pasted embed may point to; anything else is refused. */
    private const EMBED_HOSTS = ['www.google.com', 'google.com', 'maps.google.com'];

    /** @param array<string, mixed> $settings */
    public function __construct(private array $settings)
    {
    }

    /** What to search for: the explicit location, else the contact address. */
    public function location(): string
    {
        $query = trim((string) ($this->settings['map_query'] ?? ''));

        return $query !== '' ? $query : trim((string) ($this->settings['contact_address'] ?? ''));
    }

    public function isAvailable(): bool
    {
        return $this->embedUrl() !== null;
    }

    public function embedUrl(): ?string
    {
        $pasted = self::extractEmbedUrl((string) ($this->settings['map_embed_url'] ?? ''));

        if ($pasted !== null) {
            return $pasted;
        }

        $query = $this->location();

        return $query === ''
            ? null
            : 'https://www.google.com/maps?q=' . rawurlencode($query) . '&hl=fr&z=15&output=embed';
    }

    /** Opens Google Maps (app on phones) with the route to the studio. */
    public function directionsUrl(): ?string
    {
        $query = $this->location();

        return $query === '' ? null : 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($query);
    }

    /** Opens the location itself in Google Maps. */
    public function searchUrl(): ?string
    {
        $query = $this->location();

        return $query === '' ? null : 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($query);
    }

    public function clickToLoad(): bool
    {
        return !empty($this->settings['map_click_to_load']);
    }

    public function showOnHome(): bool
    {
        return (bool) ($this->settings['map_show_home'] ?? true);
    }

    /** "lat, lng" when the location is typed as coordinates (for schema.org). */
    public function coordinates(): ?array
    {
        if (preg_match('/^\s*(-?\d{1,2}(?:\.\d+)?)\s*[,;]\s*(-?\d{1,3}(?:\.\d+)?)\s*$/', $this->location(), $match) !== 1) {
            return null;
        }

        [$lat, $lng] = [(float) $match[1], (float) $match[2]];

        return abs($lat) <= 90 && abs($lng) <= 180 ? ['lat' => $lat, 'lng' => $lng] : null;
    }

    /**
     * The embed address from what the photographer pasted: the whole
     * <iframe> code Google gives, or just its URL.
     *
     * The URL is rebuilt from its parsed parts and only accepted on Google's
     * own embed path, so a pasted value can never inject markup or point the
     * frame at another site.
     */
    public static function extractEmbedUrl(string $input): ?string
    {
        $input = trim($input);

        if ($input === '') {
            return null;
        }

        if (preg_match('/<iframe\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1/is', $input, $match) === 1) {
            $input = $match[2];
        }

        $url = html_entity_decode(trim($input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $parts = parse_url($url);

        if (!is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || !in_array(strtolower((string) ($parts['host'] ?? '')), self::EMBED_HOSTS, true)
            || isset($parts['user'])
            || isset($parts['port'])) {
            return null;
        }

        $path = (string) ($parts['path'] ?? '');
        $query = (string) ($parts['query'] ?? '');

        $isEmbedPath = str_starts_with($path, '/maps/embed');
        $isOutputEmbed = $path === '/maps' && preg_match('/(^|&)output=embed(&|$)/', $query) === 1;

        if ((!$isEmbedPath && !$isOutputEmbed) || $query === '' || preg_match('/[\s<>"\'`]/', $path . $query) === 1) {
            return null;
        }

        return 'https://www.google.com' . $path . '?' . $query;
    }
}
