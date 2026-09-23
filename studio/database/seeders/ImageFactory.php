<?php

declare(strict_types=1);

namespace Database\Seeders;

/**
 * Placeholder photographs for the demonstration data.
 *
 * No stock photo is bundled and no real person appears: the demo images are
 * drawn with GD. They imitate out-of-focus light — a deep gradient, soft
 * overlapping discs, a vignette — because a photographer's site judged on a
 * flat grey rectangle looks broken, and the demo is the first impression.
 *
 * They carry no caption. A visible "Sample 1" is what makes a demo look like
 * a demo instead of like the product.
 */
final class ImageFactory
{
    /** Deep, photographic pairs: shadow first, highlight second. */
    private const PALETTES = [
        [[18, 22, 30], [148, 166, 188]],   // dusk blue
        [[34, 22, 18], [214, 166, 116]],   // warm amber
        [[16, 30, 26], [140, 184, 158]],   // forest
        [[30, 20, 34], [196, 154, 196]],   // plum
        [[38, 28, 16], [228, 194, 132]],   // golden hour
        [[20, 20, 22], [176, 176, 180]],   // monochrome
        [[26, 16, 20], [206, 140, 136]],   // rose
        [[14, 26, 34], [132, 176, 200]],   // steel
    ];

    public function isAvailable(): bool
    {
        return function_exists('imagecreatetruecolor') && function_exists('imagejpeg');
    }

    public function create(string $path, int $width, int $height, int $seed): string
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException('GD is required to generate the demonstration images.');
        }

        $directory = dirname($path);

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create directory: ' . $directory);
        }

        $image = imagecreatetruecolor($width, $height);
        [$shadow, $highlight] = self::PALETTES[$seed % count(self::PALETTES)];

        $this->drawGradient($image, $width, $height, $shadow, $highlight);
        $this->drawBokeh($image, $width, $height, $seed, $highlight);
        $this->drawVignette($image, $width, $height);

        imagejpeg($image, $path, 88);
        imagedestroy($image);

        return $path;
    }

    /**
     * A diagonal gradient, light falling from one corner.
     *
     * Drawn per pixel-row on a diagonal axis rather than straight down: a
     * purely vertical gradient reads as a UI background, a diagonal one reads
     * as light.
     */
    private function drawGradient(\GdImage $image, int $width, int $height, array $shadow, array $highlight): void
    {
        $span = $width + $height;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x += 8) {
                // Eased just enough that the highlight stays in a corner
                // rather than washing evenly across the frame.
                $ratio = (($x + $y) / max(1, $span)) ** 1.15;

                $colour = imagecolorallocate(
                    $image,
                    (int) round($shadow[0] + ($highlight[0] - $shadow[0]) * $ratio),
                    (int) round($shadow[1] + ($highlight[1] - $shadow[1]) * $ratio),
                    (int) round($shadow[2] + ($highlight[2] - $shadow[2]) * $ratio)
                );

                imagefilledrectangle($image, $x, $y, $x + 8, $y, $colour);
            }
        }
    }

    /** Soft discs of light, as a fast lens renders an out-of-focus background. */
    private function drawBokeh(\GdImage $image, int $width, int $height, int $seed, array $highlight): void
    {
        mt_srand($seed * 7919);

        $count = (int) max(18, round(($width * $height) / 90000));

        for ($i = 0; $i < $count; $i++) {
            $radius = mt_rand((int) ($width / 16), (int) ($width / 5));
            $x = mt_rand(-$radius, $width + $radius);
            $y = mt_rand(-$radius, $height + $radius);

            // Concentric rings fake a soft edge: GD has no blur cheap enough
            // to run over a whole demo set. Faint on purpose — bokeh is a
            // suggestion of light, not a pattern of circles.
            for ($ring = 5; $ring >= 1; $ring--) {
                $colour = imagecolorallocatealpha(
                    $image,
                    min(255, $highlight[0] + 30),
                    min(255, $highlight[1] + 30),
                    min(255, $highlight[2] + 30),
                    122 - $ring
                );

                if ($colour === false) {
                    continue;
                }

                $size = (int) ($radius * ($ring / 5));
                imagefilledellipse($image, $x, $y, $size, $size, $colour);
            }
        }

        mt_srand();
    }

    /**
     * Darken the four edges.
     *
     * Drawn as edge bands rather than concentric ellipses: filled ellipses
     * centred on the frame stack up in the middle, which darkens the centre —
     * the opposite of a vignette. Bands are also far cheaper than testing the
     * distance of every pixel from the centre.
     */
    private function drawVignette(\GdImage $image, int $width, int $height): void
    {
        $depthX = (int) max(24, $width * 0.34);
        $depthY = (int) max(24, $height * 0.34);
        $strongest = 96; // 0 opaque, 127 transparent: a light touch.

        for ($i = 0; $i < $depthX; $i++) {
            $alpha = (int) round($strongest + (127 - $strongest) * ($i / $depthX));
            $colour = imagecolorallocatealpha($image, 0, 0, 0, min(127, $alpha));

            if ($colour === false) {
                continue;
            }

            imagefilledrectangle($image, $i, 0, $i, $height, $colour);
            imagefilledrectangle($image, $width - 1 - $i, 0, $width - 1 - $i, $height, $colour);
        }

        for ($i = 0; $i < $depthY; $i++) {
            $alpha = (int) round($strongest + (127 - $strongest) * ($i / $depthY));
            $colour = imagecolorallocatealpha($image, 0, 0, 0, min(127, $alpha));

            if ($colour === false) {
                continue;
            }

            imagefilledrectangle($image, 0, $i, $width, $i, $colour);
            imagefilledrectangle($image, 0, $height - 1 - $i, $width, $height - 1 - $i, $colour);
        }
    }

    /** Create the file in a temporary location, as an upload would arrive. */
    public function createTemporary(int $width, int $height, int $seed): string
    {
        $path = sys_get_temp_dir() . '/studio-seed-' . bin2hex(random_bytes(6)) . '.jpg';

        return $this->create($path, $width, $height, $seed);
    }
}
