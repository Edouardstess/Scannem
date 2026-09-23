<?php

declare(strict_types=1);

namespace Database\Seeders;

/**
 * Generates placeholder photographs for the demonstration data.
 *
 * No stock photo is bundled and no real person appears anywhere: the demo
 * images are gradients drawn with GD, which keeps the repository small and
 * avoids shipping anyone's likeness or a licence obligation.
 */
final class ImageFactory
{
    /** Muted, photographic palettes rather than saturated test colours. */
    private const PALETTES = [
        [[38, 44, 56], [120, 134, 150]],
        [[62, 48, 44], [188, 150, 116]],
        [[34, 52, 48], [140, 168, 150]],
        [[58, 44, 62], [172, 140, 176]],
        [[70, 58, 40], [206, 178, 126]],
        [[40, 40, 40], [150, 150, 150]],
    ];

    public function isAvailable(): bool
    {
        return function_exists('imagecreatetruecolor') && function_exists('imagejpeg');
    }

    /**
     * Write a JPEG placeholder and return its path.
     *
     * @param string $label Drawn onto the image so demo photos are telling apart.
     */
    public function create(string $path, int $width, int $height, int $seed, string $label = ''): string
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException('GD is required to generate the demonstration images.');
        }

        $directory = dirname($path);

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create directory: ' . $directory);
        }

        $image = imagecreatetruecolor($width, $height);
        [$from, $to] = self::PALETTES[$seed % count(self::PALETTES)];

        // A diagonal gradient reads as a photograph at thumbnail size far
        // better than a flat fill does.
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / max(1, $height - 1);
            $colour = imagecolorallocate(
                $image,
                (int) round($from[0] + ($to[0] - $from[0]) * $ratio),
                (int) round($from[1] + ($to[1] - $from[1]) * $ratio),
                (int) round($from[2] + ($to[2] - $from[2]) * $ratio)
            );

            imagefilledrectangle($image, 0, $y, $width, $y, $colour);
        }

        $this->drawGrain($image, $width, $height, $seed);

        if ($label !== '') {
            $this->drawLabel($image, $width, $height, $label);
        }

        imagejpeg($image, $path, 86);
        imagedestroy($image);

        return $path;
    }

    private function drawGrain(\GdImage $image, int $width, int $height, int $seed): void
    {
        mt_srand($seed);

        $light = imagecolorallocatealpha($image, 255, 255, 255, 105);
        $dark = imagecolorallocatealpha($image, 0, 0, 0, 105);

        for ($i = 0; $i < 90; $i++) {
            $x = mt_rand(0, $width);
            $y = mt_rand(0, $height);
            $radius = mt_rand((int) ($width / 14), (int) ($width / 4));

            imagefilledellipse($image, $x, $y, $radius, $radius, $i % 2 === 0 ? $light : $dark);
        }

        mt_srand();
    }

    private function drawLabel(\GdImage $image, int $width, int $height, string $label): void
    {
        $ascii = (string) preg_replace('/[^\x20-\x7E]/', '', $label);
        $font = 5;
        $textWidth = imagefontwidth($font) * strlen($ascii);
        $x = max(8, (int) (($width - $textWidth) / 2));
        $y = (int) (($height - imagefontheight($font)) / 2);

        $shadow = imagecolorallocatealpha($image, 0, 0, 0, 60);
        $white = imagecolorallocatealpha($image, 255, 255, 255, 30);

        imagestring($image, $font, $x + 1, $y + 1, $ascii, $shadow);
        imagestring($image, $font, $x, $y, $ascii, $white);
    }

    /** Create the file in a temporary location, as an upload would arrive. */
    public function createTemporary(int $width, int $height, int $seed, string $label = ''): string
    {
        $path = sys_get_temp_dir() . '/studio-seed-' . bin2hex(random_bytes(6)) . '.jpg';

        return $this->create($path, $width, $height, $seed, $label);
    }
}
