<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Environment;
use App\Core\Logger;
use App\Exceptions\UploadException;
use App\Models\VariantType;
use App\Repositories\PhotoRepository;

/**
 * Validation, storage and derivative generation for an uploaded photograph.
 *
 * The validation order matters and is deliberate:
 *   1. PHP-level upload errors (size limits, partial writes)
 *   2. The real MIME type, read from the file's bytes with finfo
 *   3. That the bytes actually decode as an image
 *   4. Only then is the file moved into private storage
 *
 * The client-supplied filename and Content-Type are treated as decoration:
 * they are recorded for the download filename and never trusted for anything
 * that decides where bytes go or how they are served.
 */
final class PhotoUploadService
{
    public function __construct(
        private ?StorageService $storage = null,
        private ?ImageProcessingService $images = null,
        private ?PhotoRepository $photos = null,
        private ?SettingsService $settings = null
    ) {
        $this->storage = $storage ?? new StorageService();
        $this->images = $images ?? new ImageProcessingService();
        $this->photos = $photos ?? new PhotoRepository();
        $this->settings = $settings ?? new SettingsService();
    }

    /**
     * Store one uploaded file into a gallery.
     *
     * @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int} $file
     * @param array<string, mixed> $gallery
     * @return array<string, mixed> The created photo row.
     * @throws UploadException on any validation or storage failure.
     */
    public function store(array $file, array $gallery): array
    {
        $galleryId = (int) $gallery['id'];

        $this->assertUploadSucceeded($file);

        $temporaryPath = (string) ($file['tmp_name'] ?? '');
        $originalName = $this->sanitiseOriginalName((string) ($file['name'] ?? 'photo'));
        $size = (int) ($file['size'] ?? 0);

        $this->assertSizeAllowed($size);
        $mime = $this->detectMimeType($temporaryPath);
        $this->assertMimeAllowed($mime);
        $this->assertDecodesAsImage($temporaryPath, $mime);

        $this->storage->ensureReady();

        $extension = $this->extensionFor($mime, $originalName);
        $filename = $this->storage->generateFilename($extension);
        $originalRelative = $this->storage->originalPath($galleryId, $filename);

        $checksum = hash_file('sha256', $temporaryPath) ?: null;

        $this->storage->files()->moveUploadedFile($temporaryPath, $originalRelative);

        $absoluteOriginal = $this->storage->absolute($originalRelative);
        $metadata = $this->images->readMetadata($absoluteOriginal);

        try {
            return Database::transaction(function () use (
                $galleryId, $filename, $originalName, $originalRelative,
                $mime, $size, $metadata, $checksum, $gallery
            ): array {
                $photoId = $this->photos->insert([
                    'gallery_id'        => $galleryId,
                    'filename'          => $filename,
                    'original_filename' => $originalName,
                    'storage_path'      => $originalRelative,
                    'mime_type'         => $mime,
                    'file_size'         => $size,
                    'width'             => $metadata['width'] ?: null,
                    'height'            => $metadata['height'] ?: null,
                    'orientation'       => $metadata['orientation'],
                    'taken_at'          => $metadata['taken_at'],
                    'checksum'          => $checksum,
                    'downloadable'      => 1,
                    'sort_order'        => $this->photos->nextSortOrder($galleryId),
                    'status'            => 'ready',
                    'created_at'        => date('Y-m-d H:i:s'),
                    'updated_at'        => date('Y-m-d H:i:s'),
                ]);

                $this->generateVariants($photoId, $galleryId, $filename, $originalRelative, $gallery);

                $photo = $this->photos->find($photoId);

                if ($photo === null) {
                    throw new UploadException('La photo n\'a pas pu être enregistrée.');
                }

                return $photo;
            });
        } catch (\Throwable $e) {
            // The row is gone (transaction rolled back); the file on disk must
            // go too, or storage fills with photographs nothing references.
            $this->storage->delete($originalRelative);

            if ($e instanceof UploadException) {
                throw $e;
            }

            Logger::error('Upload failed after the original was stored', [
                'gallery_id' => $galleryId,
                'error'      => $e->getMessage(),
            ]);

            throw new UploadException('La photo n\'a pas pu être traitée : ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Build the thumbnail and preview for a stored original.
     *
     * @param array<string, mixed> $gallery
     */
    public function generateVariants(
        int $photoId,
        int $galleryId,
        string $filename,
        string $originalRelative,
        array $gallery
    ): void {
        if (!$this->images->isAvailable()) {
            throw new UploadException(
                'Aucune extension de traitement d\'image (GD ou Imagick) n\'est disponible sur ce serveur.'
            );
        }

        $absoluteOriginal = $this->storage->absolute($originalRelative);
        $watermarkEnabled = (bool) ($gallery['watermark_enabled'] ?? false);
        $watermarkText = $watermarkEnabled ? $this->settings->watermarkText() : '';

        // JPEG is the baseline: every browser and every GD build handles it.
        $base = pathinfo($filename, PATHINFO_FILENAME);

        $definitions = [
            VariantType::THUMBNAIL => [
                'directory' => 'thumbnail',
                'width'     => (int) Config::get('storage.thumbnail_width', 400),
                'quality'   => (int) Config::get('storage.thumbnail_quality', 78),
                // Thumbnails are small contact-sheet tiles; a watermark on them
                // is illegible and only degrades the grid.
                'watermark' => false,
            ],
            VariantType::PREVIEW => [
                'directory' => 'preview',
                'width'     => (int) Config::get('storage.preview_width', 1600),
                'quality'   => (int) Config::get('storage.preview_quality', 82),
                'watermark' => $watermarkEnabled,
            ],
        ];

        $this->photos->deleteVariants($photoId);
        $webpSupported = $this->images->supportsWebp();

        foreach ($definitions as $type => $definition) {
            $this->writeVariant($photoId, $galleryId, $type, $base, 'jpeg', $definition, $absoluteOriginal, $watermarkText);

            if (!$webpSupported) {
                continue;
            }

            $webpType = VariantType::webpOf($type);

            if ($webpType === null) {
                continue;
            }

            try {
                $this->writeVariant(
                    $photoId,
                    $galleryId,
                    $webpType,
                    $base,
                    'webp',
                    $definition,
                    $absoluteOriginal,
                    $watermarkText
                );
            } catch (\Throwable $e) {
                // A missing WebP companion costs bandwidth, not correctness:
                // the JPEG is already written and the gallery works.
                Logger::warning('WebP variant could not be generated', [
                    'photo_id' => $photoId,
                    'error'    => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Render one rendition and record it.
     *
     * @param array{directory: string, width: int, quality: int, watermark: bool} $definition
     */
    private function writeVariant(
        int $photoId,
        int $galleryId,
        string $type,
        string $basename,
        string $format,
        array $definition,
        string $absoluteOriginal,
        string $watermarkText
    ): void {
        $extension = $format === 'webp' ? '.webp' : '.jpg';
        $filename = $basename . $extension;

        $relative = $definition['directory'] === 'thumbnail'
            ? $this->storage->thumbnailPath($galleryId, $filename)
            : $this->storage->previewPath($galleryId, $filename);

        $result = $this->images->makeVariant(
            $absoluteOriginal,
            $this->storage->absolute($relative),
            $definition['width'],
            $definition['quality'],
            $definition['watermark'],
            $watermarkText,
            $format
        );

        $this->photos->insertVariant([
            'photo_id'     => $photoId,
            'variant_type' => $type,
            'storage_path' => $relative,
            'mime_type'    => $result['mime'],
            'width'        => $result['width'],
            'height'       => $result['height'],
            'file_size'    => $result['bytes'],
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Rebuild every derivative of a gallery.
     *
     * Needed after the watermark setting changes: existing previews carry the
     * old state and must be regenerated from the untouched originals.
     *
     * @param array<string, mixed> $gallery
     * @return array{processed: int, failed: int}
     */
    public function regenerateGalleryVariants(array $gallery): array
    {
        $galleryId = (int) $gallery['id'];
        $processed = 0;
        $failed = 0;

        foreach ($this->photos->forGalleryWithVariants($galleryId) as $photo) {
            try {
                $this->generateVariants(
                    (int) $photo['id'],
                    $galleryId,
                    (string) $photo['filename'],
                    (string) $photo['storage_path'],
                    $gallery
                );
                $processed++;
            } catch (\Throwable $e) {
                $failed++;
                Logger::warning('Variant regeneration failed', [
                    'photo_id' => $photo['id'],
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return ['processed' => $processed, 'failed' => $failed];
    }

    /** Delete a photo, its derivatives and its files. */
    public function deletePhoto(int $photoId): bool
    {
        $photo = $this->photos->find($photoId);

        if ($photo === null) {
            return false;
        }

        $paths = array_map(
            static fn (array $row): string => (string) $row['storage_path'],
            $this->photos->pathsFor($photoId)
        );

        Database::transaction(function () use ($photoId): void {
            (new \App\Repositories\GalleryRepository())->clearCoverPhoto($photoId);
            $this->photos->deleteVariants($photoId);
            $this->photos->delete($photoId);
        });

        $this->storage->deleteMany($paths);

        return true;
    }

    // --- Validation --------------------------------------------------------

    /** @param array<string, mixed> $file */
    private function assertUploadSucceeded(array $file): void
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_OK) {
            if (!is_string($file['tmp_name'] ?? null) || $file['tmp_name'] === '') {
                throw new UploadException('Fichier temporaire introuvable.');
            }

            return;
        }

        throw new UploadException(match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => sprintf(
                'Le fichier dépasse la taille maximale acceptée par le serveur (%s).',
                format_bytes(Environment::uploadLimitBytes((int) Config::get('storage.max_upload_bytes')))
            ),
            UPLOAD_ERR_PARTIAL    => 'Le fichier n\'a été que partiellement envoyé. Réessayez.',
            UPLOAD_ERR_NO_FILE    => 'Aucun fichier reçu.',
            UPLOAD_ERR_NO_TMP_DIR => 'Répertoire temporaire manquant sur le serveur.',
            UPLOAD_ERR_CANT_WRITE => 'Écriture impossible sur le disque du serveur.',
            UPLOAD_ERR_EXTENSION  => 'Envoi bloqué par une extension PHP.',
            default               => 'Envoi du fichier impossible.',
        });
    }

    private function assertSizeAllowed(int $size): void
    {
        $max = (int) Config::get('storage.max_upload_bytes', 100 * 1024 * 1024);

        if ($size <= 0) {
            throw new UploadException('Fichier vide.');
        }

        if ($size > $max) {
            throw new UploadException(sprintf(
                'Fichier trop volumineux (%s). Maximum : %s.',
                format_bytes($size),
                format_bytes($max)
            ));
        }
    }

    /** The MIME type as read from the file's own bytes. */
    public function detectMimeType(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            if ($finfo !== false) {
                $mime = finfo_file($finfo, $path);
                finfo_close($finfo);

                if (is_string($mime) && $mime !== '') {
                    return strtolower($mime);
                }
            }
        }

        $info = @getimagesize($path);

        if (is_array($info) && isset($info['mime'])) {
            return strtolower((string) $info['mime']);
        }

        return 'application/octet-stream';
    }

    private function assertMimeAllowed(string $mime): void
    {
        $allowed = (array) Config::get('storage.allowed_mime', []);

        if (!in_array($mime, $allowed, true)) {
            throw new UploadException('Format non autorisé (' . $mime . '). Formats acceptés : JPG, PNG, WEBP, TIFF, HEIC.');
        }
    }

    /**
     * Confirm the bytes really are a decodable image.
     *
     * A file can announce image/jpeg through finfo and still be a polyglot
     * crafted to be interpreted as something else downstream. Requiring a
     * successful decode closes that gap for the formats GD understands.
     */
    private function assertDecodesAsImage(string $path, string $mime): void
    {
        $info = @getimagesize($path);

        if (is_array($info) && (int) $info[0] > 0 && (int) $info[1] > 0) {
            return;
        }

        // getimagesize does not know HEIC or every TIFF flavour. Imagick is
        // then the authority; without it, the format cannot be accepted
        // because nothing could generate previews from it either.
        if (in_array($mime, ['image/heic', 'image/heif', 'image/tiff'], true)
            && $this->images->driver() === ImageProcessingService::DRIVER_IMAGICK) {
            try {
                $image = new \Imagick();
                $image->pingImage($path);
                $valid = $image->getImageWidth() > 0;
                $image->clear();

                if ($valid) {
                    return;
                }
            } catch (\Throwable) {
                // Falls through to the exception below.
            }
        }

        throw new UploadException('Ce fichier n\'est pas une image valide ou son format n\'est pas pris en charge ici.');
    }

    private function extensionFor(string $mime, string $originalName): string
    {
        $fromMime = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/tiff' => 'tif',
            'image/heic', 'image/heif' => 'heic',
            default      => null,
        };

        if ($fromMime !== null) {
            return $fromMime;
        }

        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = (array) Config::get('storage.allowed_extensions', []);

        return in_array($extension, $allowed, true) ? $extension : 'jpg';
    }

    /**
     * Keep the client's filename readable but harmless.
     *
     * It is only ever used as a download filename, never as a path.
     */
    private function sanitiseOriginalName(string $name): string
    {
        $name = basename(str_replace(['\\', "\0"], '/', $name));
        $name = (string) preg_replace('/[\x00-\x1F\x7F"]+/u', '', $name);
        $name = trim($name);

        if ($name === '' || $name === '.' || $name === '..') {
            $name = 'photo.jpg';
        }

        return mb_substr($name, 0, 255);
    }

    /** @return array<int, array<string, mixed>> Normalise PHP's odd multi-file $_FILES shape. */
    public static function normaliseFilesArray(array $files): array
    {
        if (!isset($files['name'])) {
            return [];
        }

        if (!is_array($files['name'])) {
            return [$files];
        }

        $normalised = [];

        foreach (array_keys($files['name']) as $index) {
            $normalised[] = [
                'name'     => $files['name'][$index] ?? '',
                'type'     => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error'    => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $files['size'][$index] ?? 0,
            ];
        }

        return $normalised;
    }
}
