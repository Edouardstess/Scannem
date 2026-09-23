<?php
/**
 * Brand colours, injected as CSS custom properties.
 *
 * The photographer picks these in the admin, so they cannot live in the
 * stylesheet. Each value is validated as a hex colour on save and escaped
 * here, so nothing arbitrary can be written into a <style> block.
 *
 * @var array<string, mixed> $settings
 */

// Settings are shared on every normal request; the fallback keeps the
// error pages renderable when a failure happens before that.
$settings = $settings ?? \App\Services\SettingsService::DEFAULTS;

$hex = static function (mixed $value, string $fallback): string {
    $value = is_string($value) ? trim($value) : '';

    return preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) === 1 ? $value : $fallback;
};

$primary = $hex($settings['color_primary'] ?? null, '#1a1a1a');
$accent = $hex($settings['color_accent'] ?? null, '#b08d57');
$background = $hex($settings['color_background'] ?? null, '#fbfaf8');
?>
<style>
:root {
    --color-primary: <?= e($primary) ?>;
    --color-accent: <?= e($accent) ?>;
    --color-background: <?= e($background) ?>;
}
</style>
