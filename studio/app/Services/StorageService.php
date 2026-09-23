<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\FileStorage;
use App\Exceptions\StorageException;

/**
 * Domain-level view of private storage.
 *
 * Knows where an original, a preview and a thumbnail belong; FileStorage
 * knows how to touch the filesystem safely. Controllers use neither directly
 * for photo files — they go through PhotoUploadService and DownloadService.
 */
final class StorageService
{
    public function __construct(private ?FileStorage $files = null)
    {
        $this->files = $files ?? new FileStorage();
    }

    public function files(): FileStorage
    {
        return $this->files;
    }

    public function ensureReady(): void
    {
        $this->files->ensureDirectories();
    }

    /**
     * Opaque, unguessable filename for a stored file.
     *
     * The client's filename is kept in the database for the download's
     * Content-Disposition, but it never becomes a path on disk: a name like
     * "../../.htaccess" or "shell.php.jpg" must not be able to influence
     * where bytes land.
     */
    public function generateFilename(string $extension): string
    {
        $extension = strtolower(preg_replace('/[^a-z0-9]/i', '', $extension) ?: 'jpg');

        return bin2hex(random_bytes(16)) . '.' . $extension;
    }

    public function originalPath(int $galleryId, string $filename): string
    {
        return sprintf('originals/%d/%s', $galleryId, $filename);
    }

    public function previewPath(int $galleryId, string $filename): string
    {
        return sprintf('previews/%d/%s', $galleryId, $filename);
    }

    public function thumbnailPath(int $galleryId, string $filename): string
    {
        return sprintf('thumbnails/%d/%s', $galleryId, $filename);
    }

    public function temporaryPath(string $filename): string
    {
        return 'temporary/' . $filename;
    }

    public function absolute(string $relative): string
    {
        return $this->files->path($relative);
    }

    public function exists(string $relative): bool
    {
        return $this->files->exists($relative);
    }

    public function delete(string $relative): bool
    {
        return $this->files->delete($relative);
    }

    /** @param array<int, string> $relativePaths */
    public function deleteMany(array $relativePaths): int
    {
        $deleted = 0;

        foreach ($relativePaths as $relative) {
            if ($relative === '') {
                continue;
            }

            try {
                if ($this->files->delete($relative)) {
                    $deleted++;
                }
            } catch (StorageException) {
                // A path that no longer resolves is already gone; deleting the
                // database row must not be blocked by it.
                continue;
            }
        }

        return $deleted;
    }

    /** Remove a gallery's per-gallery directories once its rows are gone. */
    public function removeGalleryDirectories(int $galleryId): void
    {
        foreach (['originals', 'previews', 'thumbnails'] as $kind) {
            $directory = $this->files->root() . '/' . $kind . '/' . $galleryId;

            if (!is_dir($directory)) {
                continue;
            }

            foreach (glob($directory . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }

            @rmdir($directory);
        }
    }

    public function pruneTemporary(): int
    {
        return $this->files->pruneTemporary((int) Config::get('storage.zip_ttl_seconds', 3600));
    }

    /** @return array{originals: int, previews: int, thumbnails: int, temporary: int, total: int} */
    public function usage(): array
    {
        $usage = ['originals' => 0, 'previews' => 0, 'thumbnails' => 0, 'temporary' => 0, 'total' => 0];

        foreach (array_keys($usage) as $kind) {
            if ($kind === 'total') {
                continue;
            }

            $usage[$kind] = $this->directorySize((string) Config::get('storage.' . $kind));
            $usage['total'] += $usage[$kind];
        }

        return $usage;
    }

    private function directorySize(string $directory): int
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $total = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $total += $file->getSize();
            }
        }

        return $total;
    }

    /**
     * Is the private storage tree reachable from the web?
     *
     * Surfaced on the settings screen: a host that puts storage inside the
     * document root is the single most damaging misconfiguration possible
     * here, and it is silent until someone guesses a URL.
     */
    public function isInsideDocumentRoot(): bool
    {
        // realpath('') returns the current working directory, so an unset
        // DOCUMENT_ROOT — which is the normal case on the command line — has
        // to be rejected before resolving anything, or every CLI run would
        // report the storage as exposed.
        $raw = trim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));

        if ($raw === '') {
            return false;
        }

        $documentRoot = realpath($raw);
        $storageRoot = realpath($this->files->root());

        if ($documentRoot === false || $storageRoot === false) {
            return false;
        }

        return str_starts_with($storageRoot . '/', rtrim($documentRoot, '/') . '/');
    }

    /** Whether the exposure check could be performed at all. */
    public function canCheckDocumentRoot(): bool
    {
        return trim((string) ($_SERVER['DOCUMENT_ROOT'] ?? '')) !== '';
    }
}
