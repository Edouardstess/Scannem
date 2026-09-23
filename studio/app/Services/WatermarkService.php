<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Discreet text watermark for previews.
 *
 * Applied while a derivative is being generated, so the original file is never
 * touched. A watermark is a deterrent and an attribution mark, not a security
 * control: the real protection is that originals are never served to a VIEW
 * token at all.
 */
final class WatermarkService
{
    /** Watermark height as a fraction of the image height. */
    private const SCALE = 0.035;

    private const MARGIN_RATIO = 0.03;

    public function applyGd(\GdImage $image, string $text): void
    {
        $text = trim($text);

        if ($text === '') {
            return;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $margin = (int) max(12, round($height * self::MARGIN_RATIO));

        $font = $this->truetypeFont();

        if ($font !== null && function_exists('imagettftext')) {
            $this->applyGdTrueType($image, $text, $width, $height, $margin, $font);

            return;
        }

        $this->applyGdBitmapFont($image, $text, $width, $height, $margin);
    }

    private function applyGdTrueType(
        \GdImage $image,
        string $text,
        int $width,
        int $height,
        int $margin,
        string $font
    ): void {
        $size = max(10.0, $height * self::SCALE);
        $box = imagettfbbox($size, 0, $font, $text);

        if ($box === false) {
            $this->applyGdBitmapFont($image, $text, $width, $height, $margin);

            return;
        }

        $textWidth = abs($box[4] - $box[0]);
        $textHeight = abs($box[5] - $box[1]);

        $x = $width - $textWidth - $margin;
        $y = $height - $margin;

        // A translucent dark shadow keeps the mark legible on light images.
        $shadow = imagecolorallocatealpha($image, 0, 0, 0, 90);
        $white = imagecolorallocatealpha($image, 255, 255, 255, 55);

        if ($shadow !== false) {
            imagettftext($image, $size, 0, $x + 1, $y + 1, $shadow, $font, $text);
        }

        if ($white !== false) {
            imagettftext($image, $size, 0, $x, $y, $white, $font, $text);
        }

        unset($textHeight);
    }

    private function applyGdBitmapFont(\GdImage $image, string $text, int $width, int $height, int $margin): void
    {
        // GD's built-in fonts are ASCII-only, so accents are transliterated
        // rather than rendered as mojibake.
        $ascii = $this->toAscii($text);
        $font = 5;
        $textWidth = imagefontwidth($font) * strlen($ascii);
        $textHeight = imagefontheight($font);

        $x = max(2, $width - $textWidth - $margin);
        $y = max(2, $height - $textHeight - $margin);

        $shadow = imagecolorallocatealpha($image, 0, 0, 0, 90);
        $white = imagecolorallocatealpha($image, 255, 255, 255, 50);

        if ($shadow !== false) {
            imagestring($image, $font, $x + 1, $y + 1, $ascii, $shadow);
        }

        if ($white !== false) {
            imagestring($image, $font, $x, $y, $ascii, $white);
        }
    }

    public function applyImagick(\Imagick $image, string $text): void
    {
        $text = trim($text);

        if ($text === '') {
            return;
        }

        $height = $image->getImageHeight();
        $margin = (int) max(12, round($height * self::MARGIN_RATIO));

        $draw = new \ImagickDraw();
        $draw->setFillColor(new \ImagickPixel('rgba(255, 255, 255, 0.78)'));
        $draw->setFontSize(max(11.0, $height * self::SCALE));
        $draw->setGravity(\Imagick::GRAVITY_SOUTHEAST);

        $font = $this->truetypeFont();

        if ($font !== null) {
            $draw->setFont($font);
        }

        $shadow = clone $draw;
        $shadow->setFillColor(new \ImagickPixel('rgba(0, 0, 0, 0.45)'));

        $image->annotateImage($shadow, $margin - 1, $margin - 1, 0, $text);
        $image->annotateImage($draw, $margin, $margin, 0, $text);

        $draw->destroy();
        $shadow->destroy();
    }

    /** Locate a usable TrueType font, or null to fall back to bitmap output. */
    private function truetypeFont(): ?string
    {
        $bundled = dirname(__DIR__, 2) . '/public/assets/fonts/watermark.ttf';

        $candidates = [
            $bundled,
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
            '/Library/Fonts/Arial.ttf',
            'C:/Windows/Fonts/arial.ttf',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function toAscii(string $text): string
    {
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

            if ($converted !== false) {
                return $converted;
            }
        }

        return (string) preg_replace('/[^\x20-\x7E]/', '', $text);
    }
}
