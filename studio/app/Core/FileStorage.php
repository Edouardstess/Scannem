<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\StorageException;

/**
 * Filesystem gateway for the private storage tree.
 *
 * Every path handed to this class is resolved and checked against the storage
 * root. Photos are addressed in the database by a *relative* path; turning
 * that relative path into an absolute one happens here and nowhere else, so
 * there is a single place where traversal can be stopped.
 */
final class FileStorage
{
    public function __construct(private ?string $root = null)
    {
        $this->root = rtrim($root ?? (string) Config::get('storage.root'), '/');
    }

    public function root(): string
    {
        return (string) $this->root;
    }

    public function ensureDirectories(): void
    {
        foreach (['originals', 'previews', 'thumbnails', 'temporary'] as $key) {
            $path = (string) Config::get('storage.' . $key);

            if (!is_dir($path) && !@mkdir($path, 0775, true) && !is_dir($path)) {
                throw new StorageException('Unable to create storage directory: ' . $path);
            }
        }

        $this->protectRoot();
    }

    /**
     * Defence in depth for misconfigured hosts.
     *
     * Storage is supposed to live outside the document root. If an installer
     * or a hosting layout puts it inside anyway, these files stop Apache from
     * serving the originals.
     */
    private function protectRoot(): void
    {
        $htaccess = $this->root . '/.htaccess';

        if (!is_file($htaccess)) {
            @file_put_contents(
                $htaccess,
                "# Private storage. Never served directly: PHP delivers these files after permission checks.\n"
                . "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
                . "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n"
                . "php_flag engine off\n"
            );
        }

        $index = $this->root . '/index.html';

        if (!is_file($index)) {
            @file_put_contents($index, '');
        }
    }

    /**
     * Absolute path for a relative storage path, with traversal refused.
     *
     * The argument is always relative to the storage root. An absolute path
     * is refused rather than silently rebased: a caller that passes one has a
     * bug, and quietly reinterpreting "/etc/passwd" as
     * "<root>/etc/passwd" would hide it.
     */
    public function path(string $relative): string
    {
        $relative = str_replace('\\', '/', $relative);

        if ($relative === '' || str_contains($relative, "\0")) {
            throw new StorageException('Invalid storage path.');
        }

        if (str_starts_with($relative, '/')) {
            throw new StorageException('Storage paths must be relative to the storage root.');
        }

        $absolute = $this->root . '/' . $relative;
        $normalised = $this->normalise($absolute);

        if (!str_starts_with($normalised, $this->root . '/')) {
            throw new StorageException('Storage path escapes the storage root.');
        }

        return $normalised;
    }

    /** Resolve "." and ".." without requiring the file to exist. */
    private function normalise(string $path): string
    {
        $parts = [];

        foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($parts);

                continue;
            }

            $parts[] = $segment;
        }

        return '/' . implode('/', $parts);
    }

    public function exists(string $relative): bool
    {
        return is_file($this->path($relative));
    }

    public function size(string $relative): int
    {
        $size = @filesize($this->path($relative));

        return $size === false ? 0 : $size;
    }

    public function put(string $relative, string $contents): void
    {
        $absolute = $this->path($relative);
        $this->makeDirectory(dirname($absolute));

        if (@file_put_contents($absolute, $contents, LOCK_EX) === false) {
            throw new StorageException('Unable to write file: ' . $relative);
        }

        @chmod($absolute, 0640);
    }

    public function get(string $relative): string
    {
        $absolute = $this->path($relative);
        $contents = @file_get_contents($absolute);

        if ($contents === false) {
            throw new StorageException('Unable to read file: ' . $relative);
        }

        return $contents;
    }

    public function delete(string $relative): bool
    {
        $absolute = $this->path($relative);

        return is_file($absolute) ? @unlink($absolute) : true;
    }

    public function moveUploadedFile(string $temporaryPath, string $relative): void
    {
        $absolute = $this->path($relative);
        $this->makeDirectory(dirname($absolute));

        $moved = is_uploaded_file($temporaryPath)
            ? @move_uploaded_file($temporaryPath, $absolute)
            : @rename($temporaryPath, $absolute);

        if (!$moved) {
            throw new StorageException('Unable to store the uploaded file.');
        }

        @chmod($absolute, 0640);
    }

    public function makeDirectory(string $absolute): void
    {
        if (!is_dir($absolute) && !@mkdir($absolute, 0775, true) && !is_dir($absolute)) {
            throw new StorageException('Unable to create directory: ' . $absolute);
        }
    }

    /**
     * Relative path for a new file of a given kind.
     *
     * Files are sharded by date so no directory ends up with 100k entries,
     * which some shared hosts handle poorly.
     */
    public function buildRelativePath(string $kind, string $filename, ?string $date = null): string
    {
        $date ??= date('Y/m');

        return sprintf('%s/%s/%s', $kind, $date, $filename);
    }

    /** Remove temporary files older than the TTL (ZIP archives, mostly). */
    public function pruneTemporary(int $ttlSeconds): int
    {
        $directory = (string) Config::get('storage.temporary');

        if (!is_dir($directory)) {
            return 0;
        }

        $removed = 0;
        $threshold = time() - max(60, $ttlSeconds);

        foreach (glob($directory . '/*') ?: [] as $file) {
            if (!is_file($file) || basename($file) === '.htaccess' || basename($file) === 'index.html') {
                continue;
            }

            $mtime = @filemtime($file);

            if ($mtime !== false && $mtime < $threshold && @unlink($file)) {
                $removed++;
            }
        }

        return $removed;
    }

    public function freeSpaceBytes(): float
    {
        if (!Environment::functionAvailable('disk_free_space')) {
            return 0.0;
        }

        $free = @disk_free_space($this->root);

        return $free === false ? 0.0 : $free;
    }
}
