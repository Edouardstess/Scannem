<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\GalleryStatus;
use App\Models\TokenType;
use App\Repositories\GalleryRepository;
use App\Repositories\GalleryTokenRepository;
use App\Repositories\PhotoRepository;

/**
 * Gallery lifecycle: creation, settings, links, deletion.
 *
 * Controllers call this; they never write gallery rows or tokens directly.
 */
final class GalleryService
{
    public function __construct(
        private ?GalleryRepository $galleries = null,
        private ?GalleryTokenRepository $tokens = null,
        private ?TokenService $tokenService = null,
        private ?PhotoRepository $photos = null,
        private ?StorageService $storage = null
    ) {
        $this->galleries = $galleries ?? new GalleryRepository();
        $this->tokens = $tokens ?? new GalleryTokenRepository();
        $this->tokenService = $tokenService ?? new TokenService();
        $this->photos = $photos ?? new PhotoRepository();
        $this->storage = $storage ?? new StorageService();
    }

    /**
     * Create a gallery and both of its access links.
     *
     * The two tokens are always created together, at creation time, because
     * the product promise is that every gallery has a view link and a download
     * link from the start. The download link exists but is inert until
     * download_enabled is turned on — which is what lets the photographer
     * share the view link first and open downloads later without re-sharing.
     *
     * @param array<string, mixed> $attributes
     * @return array{gallery_id: int, view_token: string, download_token: string}
     */
    public function create(array $attributes): array
    {
        $expiresAt = $attributes['expires_at'] ?? null;

        return Database::transaction(function () use ($attributes, $expiresAt): array {
            $galleryId = $this->galleries->insert([
                'event_id'          => (int) $attributes['event_id'],
                'title'             => (string) $attributes['title'],
                'description'       => $attributes['description'] ?? null,
                'password_hash'     => $this->hashPassword($attributes['password'] ?? null),
                'watermark_enabled' => !empty($attributes['watermark_enabled']) ? 1 : 0,
                'download_enabled'  => !empty($attributes['download_enabled']) ? 1 : 0,
                'selection_enabled' => !empty($attributes['selection_enabled']) ? 1 : 0,
                'status'            => $attributes['status'] ?? GalleryStatus::DRAFT,
                'expires_at'        => $expiresAt,
                'created_at'        => date('Y-m-d H:i:s'),
                'updated_at'        => date('Y-m-d H:i:s'),
            ]);

            $view = $this->tokenService->issue($galleryId, TokenType::VIEW, $expiresAt);
            $download = $this->tokenService->issue($galleryId, TokenType::DOWNLOAD, $expiresAt);

            return [
                'gallery_id'     => $galleryId,
                'view_token'     => $view['raw'],
                'download_token' => $download['raw'],
            ];
        });
    }

    /**
     * Update a gallery's settings.
     *
     * @param array<string, mixed> $attributes
     * @return array{watermark_changed: bool}
     */
    public function update(int $galleryId, array $attributes): array
    {
        $existing = $this->galleries->find($galleryId);

        if ($existing === null) {
            throw new \RuntimeException('Gallery not found.');
        }

        $changes = [
            'title'             => (string) $attributes['title'],
            'description'       => $attributes['description'] ?? null,
            'event_id'          => (int) $attributes['event_id'],
            'watermark_enabled' => !empty($attributes['watermark_enabled']) ? 1 : 0,
            'download_enabled'  => !empty($attributes['download_enabled']) ? 1 : 0,
            'selection_enabled' => !empty($attributes['selection_enabled']) ? 1 : 0,
            'status'            => $attributes['status'] ?? $existing['status'],
            'expires_at'        => $attributes['expires_at'] ?? null,
            'updated_at'        => date('Y-m-d H:i:s'),
        ];

        // Three distinct intents on the password field: leave it alone, set a
        // new one, or remove it. A blank field means "leave it alone", so that
        // saving the form does not silently unprotect a gallery.
        if (!empty($attributes['remove_password'])) {
            $changes['password_hash'] = null;
        } elseif (isset($attributes['password']) && trim((string) $attributes['password']) !== '') {
            $changes['password_hash'] = $this->hashPassword($attributes['password']);
        }

        $this->galleries->update($galleryId, $changes);

        // Token expiry follows the gallery's, so a link cannot outlive it.
        foreach ($this->tokens->forGallery($galleryId) as $token) {
            if ($token['revoked_at'] === null) {
                $this->tokens->setExpiry((int) $token['id'], $changes['expires_at']);
            }
        }

        $watermarkChanged = (int) $existing['watermark_enabled'] !== (int) $changes['watermark_enabled'];

        return ['watermark_changed' => $watermarkChanged];
    }

    /** @return array{view: ?array<string, mixed>, download: ?array<string, mixed>} */
    public function activeTokens(int $galleryId): array
    {
        return [
            'view'     => $this->tokens->activeFor($galleryId, TokenType::VIEW),
            'download' => $this->tokens->activeFor($galleryId, TokenType::DOWNLOAD),
        ];
    }

    /**
     * Issue a fresh link of one type, invalidating the previous one.
     *
     * @return array{id: int, raw: string, type: string, expires_at: ?string}
     */
    public function regenerateToken(int $galleryId, string $type): array
    {
        $gallery = $this->galleries->find($galleryId);
        $expiresAt = $gallery === null ? null : ($gallery['expires_at'] ?? null);

        return $this->tokenService->regenerate($galleryId, $type, $expiresAt === null ? null : (string) $expiresAt);
    }

    public function revokeToken(int $galleryId, string $type): int
    {
        return $this->tokenService->revokeGallery($galleryId, $type);
    }

    /** Close a gallery: every link stops working immediately. */
    public function disable(int $galleryId): void
    {
        Database::transaction(function () use ($galleryId): void {
            $this->galleries->setStatus($galleryId, GalleryStatus::DISABLED);
            $this->tokenService->revokeGallery($galleryId);
        });
    }

    public function activate(int $galleryId): void
    {
        $this->galleries->setStatus($galleryId, GalleryStatus::ACTIVE);

        // Re-issue any access type left without a live token, so that
        // re-activating a gallery produces working links again.
        foreach ([TokenType::VIEW, TokenType::DOWNLOAD] as $type) {
            if ($this->tokens->activeFor($galleryId, $type) === null) {
                $gallery = $this->galleries->find($galleryId);
                $expiresAt = $gallery['expires_at'] ?? null;
                $this->tokenService->issue($galleryId, $type, $expiresAt === null ? null : (string) $expiresAt);
            }
        }
    }

    public function archive(int $galleryId): void
    {
        Database::transaction(function () use ($galleryId): void {
            $this->galleries->setStatus($galleryId, GalleryStatus::ARCHIVED);
            $this->tokenService->revokeGallery($galleryId);
        });
    }

    /**
     * Delete a gallery, its photos, its links and every file behind them.
     *
     * Rows go first inside a transaction; files are removed afterwards. If
     * file deletion fails the database is still consistent, and the orphans
     * are visible in storage usage rather than the reverse — a row pointing
     * at a file that no longer exists would break every gallery page.
     */
    public function delete(int $galleryId): void
    {
        $paths = array_map(
            static fn (array $row): string => (string) $row['storage_path'],
            $this->photos->allPathsForGallery($galleryId)
        );

        Database::transaction(function () use ($galleryId): void {
            $this->galleries->delete($galleryId);
        });

        $this->storage->deleteMany($paths);
        $this->storage->removeGalleryDirectories($galleryId);
    }

    public function setCoverPhoto(int $galleryId, int $photoId): bool
    {
        $photo = $this->photos->findInGallery($photoId, $galleryId);

        if ($photo === null) {
            return false;
        }

        $this->galleries->setCoverPhoto($galleryId, $photoId);

        return true;
    }

    private function hashPassword(mixed $password): ?string
    {
        if (!is_string($password) || trim($password) === '') {
            return null;
        }

        return password_hash($password, PASSWORD_DEFAULT);
    }

    /** Share URLs for a gallery's current links. */
    public function shareLinks(int $galleryId): array
    {
        $tokens = $this->activeTokens($galleryId);

        return [
            'view'     => $tokens['view'],
            'download' => $tokens['download'],
        ];
    }
}
