<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Exceptions\UploadException;

/**
 * Uploads for images that are meant to be public: portfolio pieces, service
 * illustrations, the hero image, the logo.
 *
 * These live under public/assets/uploads because they are published work —
 * the opposite of client photographs, which never leave private storage. The
 * validation is identical, and the stored filename is still generated rather
 * than taken from the client: a file called "x.php" in a web-served directory
 * would otherwise be remote code execution.
 */
final class PublicImageService
{
    private const RELATIVE_ROOT = 'assets/uploads';

    public function __construct(private ?ImageProcessingService $images = null)
    {
        $this->images = $images ?? new ImageProcessingService();
    }

    public function publicRoot(): string
    {
        return dirname(__DIR__, 2) . '/public/' . self::RELATIVE_ROOT;
    }

    /**
     * Store an uploaded public image and build a thumbnail beside it.
     *
     * @param array<string, mixed> $file
     * @return array{path: string, thumbnail: string, width: int, height: int}
     */
    public function store(array $file, string $folder = 'portfolio'): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            throw new UploadException(match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Image trop volumineuse.',
                UPLOAD_ERR_NO_FILE => 'Aucune image reçue.',
                default => "L'image n'a pas pu être envoyée.",
            });
        }

        $temporary = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);

        if ($size <= 0 || $size > 20 * 1024 * 1024) {
            throw new UploadException('Image vide ou supérieure à 20 Mo.');
        }

        $info = @getimagesize($temporary);

        if (!is_array($info) || (int) $info[0] <= 0) {
            throw new UploadException("Ce fichier n'est pas une image valide.");
        }

        $mime = strtolower((string) ($info['mime'] ?? ''));

        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new UploadException('Format accepté : JPG, PNG ou WEBP.');
        }

        $folder = preg_replace('/[^a-z0-9_-]/i', '', $folder) ?: 'portfolio';
        $directory = $this->publicRoot() . '/' . $folder;

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new UploadException("Impossible de créer le dossier d'images.");
        }

        $this->protectDirectory();

        $base = bin2hex(random_bytes(12));
        $fullName = $base . '.jpg';
        $thumbName = $base . '-thumb.jpg';

        // Re-encoded rather than copied: it normalises the format, strips
        // metadata (including GPS) and guarantees the stored bytes really are
        // an image, in a directory the web server will happily serve.
        $this->images->makeVariant($temporary, $directory . '/' . $fullName, 2000, 86);
        $result = $this->images->makeVariant($temporary, $directory . '/' . $thumbName, 800, 80);

        @unlink($temporary);

        $full = @getimagesize($directory . '/' . $fullName);

        return [
            'path'      => self::RELATIVE_ROOT . '/' . $folder . '/' . $fullName,
            'thumbnail' => self::RELATIVE_ROOT . '/' . $folder . '/' . $thumbName,
            'width'     => is_array($full) ? (int) $full[0] : $result['width'],
            'height'    => is_array($full) ? (int) $full[1] : $result['height'],
        ];
    }

    /**
     * Stop the upload directory from executing anything.
     *
     * The generated filenames already make a PHP upload impossible, but this
     * survives a future change that reintroduces client-controlled names.
     */
    private function protectDirectory(): void
    {
        $htaccess = $this->publicRoot() . '/.htaccess';

        if (is_file($htaccess)) {
            return;
        }

        @file_put_contents(
            $htaccess,
            "# Uploaded public images. Served as files, never executed.\n"
            . "php_flag engine off\n"
            . "<FilesMatch \"\\.(php|phtml|php\\d|phar|cgi|pl|py|sh)$\">\n"
            . "    <IfModule mod_authz_core.c>\n        Require all denied\n    </IfModule>\n"
            . "    <IfModule !mod_authz_core.c>\n        Order allow,deny\n        Deny from all\n    </IfModule>\n"
            . "</FilesMatch>\n"
        );
    }

    /** Delete a stored public image and its thumbnail. */
    public function delete(?string $relativePath): void
    {
        if ($relativePath === null || !str_starts_with($relativePath, self::RELATIVE_ROOT . '/')) {
            return;
        }

        $absolute = dirname(__DIR__, 2) . '/public/' . $relativePath;
        $real = realpath($absolute);
        $root = realpath($this->publicRoot());

        if ($real === false || $root === false || !str_starts_with($real, $root . '/')) {
            return;
        }

        @unlink($real);

        $thumb = preg_replace('/\.jpg$/', '-thumb.jpg', $real);

        if (is_string($thumb) && is_file($thumb)) {
            @unlink($thumb);
        }
    }

    public function url(?string $relativePath): ?string
    {
        if ($relativePath === null || $relativePath === '') {
            return null;
        }

        return url($relativePath);
    }

    /** Unused config hook kept explicit so the folder is configurable later. */
    public static function maxBytes(): int
    {
        return (int) Config::get('storage.public_image_max_bytes', 20 * 1024 * 1024);
    }
}
