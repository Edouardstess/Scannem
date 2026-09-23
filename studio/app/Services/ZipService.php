<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;
use App\Exceptions\StorageException;
use ZipArchive;

/**
 * Temporary ZIP archives of original photographs.
 *
 * Files are added by path with ZipArchive::addFile, never by reading them
 * into memory: a 250-photo wedding gallery is several gigabytes, and
 * file_get_contents on that would exhaust any shared host.
 *
 * Archives are written to storage/private/temporary and deleted by TTL, so a
 * download link that leaks a ZIP path is worthless minutes later — and the
 * path is never exposed anyway, since PHP streams the file.
 */
final class ZipService
{
    public function __construct(private ?StorageService $storage = null)
    {
        $this->storage = $storage ?? new StorageService();
    }

    public function isAvailable(): bool
    {
        return class_exists(ZipArchive::class);
    }

    /**
     * Build an archive from a set of photo rows.
     *
     * @param array<int, array<string, mixed>> $photos Rows from PhotoRepository.
     * @return array{relative: string, absolute: string, filename: string, bytes: int, count: int}
     * @throws StorageException
     */
    public function createArchive(array $photos, string $galleryTitle): array
    {
        if (!$this->isAvailable()) {
            throw new StorageException("L'extension ZIP n'est pas disponible sur ce serveur.");
        }

        if ($photos === []) {
            throw new StorageException('Aucune photo à archiver.');
        }

        $this->guardTotalSize($photos);

        // Housekeeping runs here rather than on a cron, because a shared host
        // may not offer one and the temporary directory must not grow forever.
        $this->storage->pruneTemporary();
        $this->storage->ensureReady();

        $filename = $this->archiveFilename($galleryTitle);
        $relative = $this->storage->temporaryPath(bin2hex(random_bytes(12)) . '.zip');
        $absolute = $this->storage->absolute($relative);

        $zip = new ZipArchive();
        $opened = $zip->open($absolute, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            throw new StorageException("Impossible de créer l'archive ZIP (code " . (string) $opened . ').');
        }

        $used = [];
        $added = 0;

        foreach ($photos as $photo) {
            $source = (string) ($photo['storage_path'] ?? '');

            if ($source === '' || !$this->storage->exists($source)) {
                Logger::warning('ZIP: missing source file skipped', ['photo_id' => $photo['id'] ?? null]);

                continue;
            }

            $entryName = $this->uniqueEntryName((string) ($photo['original_filename'] ?? 'photo.jpg'), $used);

            if ($zip->addFile($this->storage->absolute($source), $entryName)) {
                $added++;
            }
        }

        if ($added === 0) {
            $zip->close();
            $this->storage->delete($relative);

            throw new StorageException('Aucun fichier n\'a pu être ajouté à l\'archive.');
        }

        if (!$zip->close()) {
            $this->storage->delete($relative);

            throw new StorageException("L'archive ZIP n'a pas pu être finalisée.");
        }

        return [
            'relative' => $relative,
            'absolute' => $absolute,
            'filename' => $filename,
            'bytes'    => (int) (@filesize($absolute) ?: 0),
            'count'    => $added,
        ];
    }

    /** @param array<int, array<string, mixed>> $photos */
    private function guardTotalSize(array $photos): void
    {
        $total = 0;

        foreach ($photos as $photo) {
            $total += (int) ($photo['file_size'] ?? 0);
        }

        $max = (int) Config::get('storage.zip_max_bytes', 4 * 1024 * 1024 * 1024);

        if ($total > $max) {
            throw new StorageException(sprintf(
                'Sélection trop volumineuse (%s). Maximum par archive : %s. Téléchargez en plusieurs fois.',
                format_bytes($total),
                format_bytes($max)
            ));
        }

        // ZIP64 is required past 4 GiB; older PHP/ZipArchive builds silently
        // produce a corrupt archive instead of failing, so the guard above is
        // what keeps that from happening.
    }

    /** A readable, filesystem-safe archive name for the browser. */
    public function archiveFilename(string $galleryTitle): string
    {
        $slug = str_slug($galleryTitle);

        if ($slug === '') {
            $slug = 'galerie';
        }

        return 'galerie-' . mb_substr($slug, 0, 60) . '.zip';
    }

    /**
     * Keep entry names unique inside the archive.
     *
     * Two cameras routinely produce IMG_0001.JPG; without this, one silently
     * overwrites the other and the client is short a photograph.
     *
     * @param array<string, bool> $used
     */
    private function uniqueEntryName(string $originalName, array &$used): string
    {
        $name = basename(str_replace(['\\', "\0"], '/', $originalName));
        $name = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $name);

        if ($name === '' || $name === '.' || $name === '..') {
            $name = 'photo.jpg';
        }

        $candidate = $name;
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $base = $extension === '' ? $name : (string) pathinfo($name, PATHINFO_FILENAME);
        $counter = 2;

        while (isset($used[strtolower($candidate)])) {
            $candidate = $extension === ''
                ? $base . '-' . $counter
                : $base . '-' . $counter . '.' . $extension;
            $counter++;
        }

        $used[strtolower($candidate)] = true;

        return $candidate;
    }

    public function delete(string $relative): void
    {
        $this->storage->delete($relative);
    }
}
