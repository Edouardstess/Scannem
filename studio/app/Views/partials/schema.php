<?php
/**
 * schema.org markup for the public site.
 *
 * json_encode handles the escaping; JSON_HEX_TAG in particular prevents a
 * settings value containing "</script>" from breaking out of the block.
 *
 * @var array<string, mixed> $settings
 */

// Settings are shared on every normal request; the fallback keeps the
// error pages renderable when a failure happens before that.
$settings = $settings ?? \App\Services\SettingsService::DEFAULTS;

$currentPath = $currentPath ?? '/';
$studioName = (string) ($settings['studio_name'] ?? 'L\'ENFANT VISUAL');
$photographer = (string) ($settings['photographer_name'] ?? $studioName);

$person = [
    '@context' => 'https://schema.org',
    '@type'    => 'Person',
    'name'     => $photographer,
    'jobTitle' => 'Photographe',
    'url'      => url('/'),
];

$sameAs = array_values(array_filter([
    $settings['social_instagram'] ?? '',
    $settings['social_facebook'] ?? '',
    $settings['social_linkedin'] ?? '',
    $settings['social_pinterest'] ?? '',
], static fn ($value): bool => is_string($value) && $value !== ''));

if ($sameAs !== []) {
    $person['sameAs'] = $sameAs;
}

$business = [
    '@context'    => 'https://schema.org',
    '@type'       => 'ProfessionalService',
    'name'        => $studioName,
    'description' => (string) ($settings['tagline'] ?? ''),
    'url'         => url('/'),
];

if (($settings['contact_email'] ?? '') !== '') {
    $business['email'] = (string) $settings['contact_email'];
}

if (($settings['contact_phone'] ?? '') !== '') {
    $business['telephone'] = (string) $settings['contact_phone'];
}

if (($settings['contact_address'] ?? '') !== '') {
    $business['address'] = ['@type' => 'PostalAddress', 'streetAddress' => (string) $settings['contact_address']];
}

// Lets Google tie the site to the place on Maps.
$map = new \App\Services\MapService($settings);

if (($mapUrl = $map->searchUrl()) !== null) {
    $business['hasMap'] = $mapUrl;
}

if (($coordinates = $map->coordinates()) !== null) {
    $business['geo'] = [
        '@type'     => 'GeoCoordinates',
        'latitude'  => $coordinates['lat'],
        'longitude' => $coordinates['lng'],
    ];
}
?>
<script type="application/ld+json"><?= ejs($person) ?></script>
<script type="application/ld+json"><?= ejs($business) ?></script>
