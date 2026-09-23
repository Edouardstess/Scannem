<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;
use App\Exceptions\StorageException;

/**
 * Derivative generation: thumbnails and previews.
 *
 * Imagick is used when the host provides it (better resampling, and it reads
 * formats GD cannot, such as TIFF); otherwise GD. The available engine is
 * detected at runtime, because shared hosts differ and the photographer
 * cannot install extensions.
 *
 * Originals are only ever read. Every derivative is written to a new file.
 */
final class ImageProcessingService
{
    public const DRIVER_IMAGICK = 'imagick';
    public const DRIVER_GD      = 'gd';
    public const DRIVER_NONE    = 'none';

    public function __construct(private ?WatermarkService $watermark = null)
    {
        $this->watermark = $watermark ?? new WatermarkService();
    }

    public function driver(): string
    {
        if (extension_loaded('imagick') && class_exists(\Imagick::class)) {
            return self::DRIVER_IMAGICK;
        }

        if (extension_loaded('gd') && function_exists('imagecreatetruecolor')) {
            return self::DRIVER_GD;
        }

        return self::DRIVER_NONE;
    }

    public function isAvailable(): bool
    {
        return $this->driver() !== self::DRIVER_NONE;
    }

    /**
     * Can this server write WebP?
     *
     * GD is compiled with WebP support on most modern hosts but not all, and
     * Imagick depends on the delegates ImageMagick was built with. Both are
     * checked at runtime rather than assumed.
     */
    public function supportsWebp(): bool
    {
        if (!(bool) Config::get('storage.webp_enabled', true)) {
            return false;
        }

        return match ($this->driver()) {
            self::DRIVER_IMAGICK => in_array('WEBP', array_map('strtoupper', \Imagick::queryFormats('WEBP')), true),
            self::DRIVER_GD      => function_exists('imagewebp'),
            default              => false,
        };
    }

    /**
     * Read the dimensions, MIME type and capture date of an image.
     *
     * GPS tags are deliberately not returned: they are read from the file but
     * never stored or shown, because a client gallery should not broadcast
     * where a photograph was taken.
     *
     * @return array{width: int, height: int, mime: string, orientation: string, taken_at: ?string}
     */
    public function readMetadata(string $absolutePath): array
    {
        $meta = [
            'width'       => 0,
            'height'      => 0,
            'mime'        => 'application/octet-stream',
            'orientation' => 'landscape',
            'taken_at'    => null,
        ];

        $info = @getimagesize($absolutePath);

        if (is_array($info)) {
            $meta['width'] = (int) $info[0];
            $meta['height'] = (int) $info[1];
            $meta['mime'] = (string) ($info['mime'] ?? $meta['mime']);
        } elseif ($this->driver() === self::DRIVER_IMAGICK) {
            try {
                $image = new \Imagick($absolutePath);
                $meta['width'] = $image->getImageWidth();
                $meta['height'] = $image->getImageHeight();
                $meta['mime'] = (string) $image->getImageMimeType();
                $image->clear();
            } catch (\Throwable $e) {
                Logger::warning('Imagick could not read image metadata', ['error' => $e->getMessage()]);
            }
        }

        // EXIF orientation 5-8 mean the stored pixels are rotated 90°, so the
        // real aspect ratio is the transpose of the stored one.
        $exifOrientation = $this->exifOrientation($absolutePath);

        if (in_array($exifOrientation, [5, 6, 7, 8], true)) {
            [$meta['width'], $meta['height']] = [$meta['height'], $meta['width']];
        }

        $meta['orientation'] = match (true) {
            $meta['width'] > $meta['height'] => 'landscape',
            $meta['width'] < $meta['height'] => 'portrait',
            default                          => 'square',
        };

        $meta['taken_at'] = $this->exifTakenAt($absolutePath);

        return $meta;
    }

    private function exifOrientation(string $absolutePath): int
    {
        if (!function_exists('exif_read_data')) {
            return 1;
        }

        $mime = (string) (@getimagesize($absolutePath)['mime'] ?? '');

        if (!in_array($mime, ['image/jpeg', 'image/tiff'], true)) {
            return 1;
        }

        $exif = @exif_read_data($absolutePath);

        return is_array($exif) && isset($exif['Orientation']) ? (int) $exif['Orientation'] : 1;
    }

    private function exifTakenAt(string $absolutePath): ?string
    {
        if (!function_exists('exif_read_data')) {
            return null;
        }

        $mime = (string) (@getimagesize($absolutePath)['mime'] ?? '');

        if (!in_array($mime, ['image/jpeg', 'image/tiff'], true)) {
            return null;
        }

        $exif = @exif_read_data($absolutePath);

        if (!is_array($exif)) {
            return null;
        }

        foreach (['DateTimeOriginal', 'DateTimeDigitized', 'DateTime'] as $key) {
            $value = $exif[$key] ?? null;

            if (!is_string($value) || $value === '') {
                continue;
            }

            // EXIF uses "Y:m:d H:i:s", which strtotime does not parse.
            $normalised = preg_replace('/^(\d{4}):(\d{2}):(\d{2})/', '$1-$2-$3', $value);
            $timestamp = strtotime((string) $normalised);

            if ($timestamp !== false) {
                return date('Y-m-d H:i:s', $timestamp);
            }
        }

        return null;
    }

    /**
     * Write a resized copy of an image.
     *
     * @param  bool   $watermark Apply the studio watermark to the output.
     * @param  string $format 'jpeg' or 'webp'.
     * @return array{width: int, height: int, bytes: int, mime: string}
     * @throws StorageException when no imaging engine can read the source.
     */
    public function makeVariant(
        string $sourcePath,
        string $destinationPath,
        int $maxWidth,
        int $quality,
        bool $watermark = false,
        string $watermarkText = '',
        string $format = 'jpeg'
    ): array {
        $directory = dirname($destinationPath);

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new StorageException('Unable to create variant directory: ' . $directory);
        }

        $format = $format === 'webp' ? 'webp' : 'jpeg';

        return match ($this->driver()) {
            self::DRIVER_IMAGICK => $this->makeVariantImagick(
                $sourcePath, $destinationPath, $maxWidth, $quality, $watermark, $watermarkText, $format
            ),
            self::DRIVER_GD => $this->makeVariantGd(
                $sourcePath, $destinationPath, $maxWidth, $quality, $watermark, $watermarkText, $format
            ),
            default => throw new StorageException(
                'No image processing extension available. Enable GD or Imagick.'
            ),
        };
    }

    /** @return array{width: int, height: int, bytes: int, mime: string} */
    private function makeVariantImagick(
        string $sourcePath,
        string $destinationPath,
        int $maxWidth,
        int $quality,
        bool $watermark,
        string $watermarkText,
        string $format = 'jpeg'
    ): array {
        $image = new \Imagick();

        try {
            $image->readImage($sourcePath);
            $image->setIteratorIndex(0);

            // Bakes EXIF rotation into the pixels and drops the tag, so the
            // derivative displays correctly everywhere.
            $image->autoOrient();
            $image->stripImage();

            $width = $image->getImageWidth();
            $height = $image->getImageHeight();
            [$targetWidth, $targetHeight] = $this->scaledSize($width, $height, $maxWidth);

            $image->resizeImage($targetWidth, $targetHeight, \Imagick::FILTER_LANCZOS, 1);
            $image->setImageFormat($format);
            $image->setImageCompressionQuality(max(1, min(100, $quality)));

            if ($watermark && $watermarkText !== '') {
                $this->watermark->applyImagick($image, $watermarkText);
            }

            $image->writeImage($destinationPath);

            $result = [
                'width'  => $targetWidth,
                'height' => $targetHeight,
                'bytes'  => (int) (@filesize($destinationPath) ?: 0),
                'mime'   => 'image/' . $format,
            ];
        } finally {
            $image->clear();
            $image->destroy();
        }

        @chmod($destinationPath, 0640);

        return $result;
    }

    /** @return array{width: int, height: int, bytes: int, mime: string} */
    private function makeVariantGd(
        string $sourcePath,
        string $destinationPath,
        int $maxWidth,
        int $quality,
        bool $watermark,
        string $watermarkText,
        string $format = 'jpeg'
    ): array {
        $source = $this->openGdImage($sourcePath);

        try {
            $source = $this->applyExifRotationGd($source, $sourcePath);

            $width = imagesx($source);
            $height = imagesy($source);
            [$targetWidth, $targetHeight] = $this->scaledSize($width, $height, $maxWidth);

            $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

            // JPEG output has no alpha channel; a white matte avoids the black
            // background PNG transparency would otherwise produce.
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $white);

            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

            if ($watermark && $watermarkText !== '') {
                $this->watermark->applyGd($canvas, $watermarkText);
            }

            $written = $format === 'webp' && function_exists('imagewebp')
                ? imagewebp($canvas, $destinationPath, max(1, min(100, $quality)))
                : imagejpeg($canvas, $destinationPath, max(1, min(100, $quality)));

            if (!$written) {
                throw new StorageException('Unable to write image variant.');
            }

            imagedestroy($canvas);

            $result = [
                'width'  => $targetWidth,
                'height' => $targetHeight,
                'bytes'  => (int) (@filesize($destinationPath) ?: 0),
                'mime'   => 'image/' . $format,
            ];
        } finally {
            if (is_object($source)) {
                imagedestroy($source);
            }
        }

        @chmod($destinationPath, 0640);

        return $result;
    }

    private function openGdImage(string $path): \GdImage
    {
        $info = @getimagesize($path);
        $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';

        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            'image/gif'  => @imagecreatefromgif($path),
            default      => false,
        };

        if (!$image instanceof \GdImage) {
            throw new StorageException(
                'Unsupported image format for GD (' . ($mime ?: 'unknown') . '). Install Imagick to handle it.'
            );
        }

        return $image;
    }

    private function applyExifRotationGd(\GdImage $image, string $path): \GdImage
    {
        $orientation = $this->exifOrientation($path);

        if ($orientation === 1) {
            return $image;
        }

        $rotated = match ($orientation) {
            3, 4 => imagerotate($image, 180, 0),
            5, 6 => imagerotate($image, -90, 0),
            7, 8 => imagerotate($image, 90, 0),
            default => null,
        };

        if (!$rotated instanceof \GdImage) {
            return $image;
        }

        imagedestroy($image);

        // Orientations 2/4/5/7 are also mirrored.
        if (in_array($orientation, [2, 4, 5, 7], true) && function_exists('imageflip')) {
            imageflip($rotated, IMG_FLIP_HORIZONTAL);
        }

        return $rotated;
    }

    /**
     * Target size for a max-width constraint.
     *
     * Images smaller than the target are never enlarged: upscaling a 800px
     * photo to a 1600px "preview" only wastes bandwidth.
     *
     * @return array{0: int, 1: int}
     */
    public function scaledSize(int $width, int $height, int $maxWidth): array
    {
        if ($width <= 0 || $height <= 0) {
            return [max(1, $maxWidth), max(1, $maxWidth)];
        }

        $longest = max($width, $height);

        if ($longest <= $maxWidth) {
            return [$width, $height];
        }

        $ratio = $maxWidth / $longest;

        return [max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio))];
    }

    /** Human-readable engine name for the settings screen. */
    public function driverLabel(): string
    {
        return match ($this->driver()) {
            self::DRIVER_IMAGICK => 'Imagick',
            self::DRIVER_GD      => 'GD',
            default              => 'Aucune (installez GD ou Imagick)',
        };
    }

    public function thumbnailWidth(): int
    {
        return (int) Config::get('storage.thumbnail_width', 400);
    }

    public function previewWidth(): int
    {
        return (int) Config::get('storage.preview_width', 1600);
    }
}
