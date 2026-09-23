<?php

declare(strict_types=1);

namespace App\Repositories;

final class GalleryTokenRepository extends Repository
{
    protected string $table = 'gallery_tokens';

    protected array $fillable = [
        'gallery_id', 'token_hash', 'token_cipher', 'token_type', 'expires_at',
        'revoked_at', 'last_used_at', 'use_count', 'created_at',
    ];

    /**
     * Look a token up by its hash.
     *
     * The caller hashes the raw token; the raw value never touches SQL, so a
     * query log or a slow-query dump cannot leak a working link.
     *
     * @return array<string, mixed>|null
     */
    public function findByHash(string $hash): ?array
    {
        return $this->selectOne(
            'SELECT * FROM gallery_tokens WHERE token_hash = :hash LIMIT 1',
            ['hash' => $hash]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function forGallery(int $galleryId): array
    {
        return $this->select(
            'SELECT * FROM gallery_tokens WHERE gallery_id = :gallery_id ORDER BY token_type ASC, id DESC',
            ['gallery_id' => $galleryId]
        );
    }

    /**
     * The token currently in force for a gallery and access type.
     *
     * Revoked rows are kept for the audit trail, so "current" means the newest
     * row that has not been revoked.
     *
     * @return array<string, mixed>|null
     */
    public function activeFor(int $galleryId, string $type): ?array
    {
        return $this->selectOne(
            'SELECT * FROM gallery_tokens
              WHERE gallery_id = :gallery_id AND token_type = :type AND revoked_at IS NULL
              ORDER BY id DESC LIMIT 1',
            ['gallery_id' => $galleryId, 'type' => $type]
        );
    }

    public function revoke(int $id): void
    {
        $this->run(
            'UPDATE gallery_tokens SET revoked_at = :now WHERE id = :id AND revoked_at IS NULL',
            ['now' => $this->now(), 'id' => $id]
        );
    }

    public function revokeAllFor(int $galleryId, ?string $type = null): int
    {
        $sql = 'UPDATE gallery_tokens SET revoked_at = :now WHERE gallery_id = :gallery_id AND revoked_at IS NULL';
        $bindings = ['now' => $this->now(), 'gallery_id' => $galleryId];

        if ($type !== null) {
            $sql .= ' AND token_type = :type';
            $bindings['type'] = $type;
        }

        return $this->run($sql, $bindings)->rowCount();
    }

    /** Record a successful use. Counters are advisory, never a security control. */
    public function touch(int $id): void
    {
        $this->run(
            'UPDATE gallery_tokens SET last_used_at = :now, use_count = use_count + 1 WHERE id = :id',
            ['now' => $this->now(), 'id' => $id]
        );
    }

    public function setExpiry(int $id, ?string $expiresAt): void
    {
        $this->run(
            'UPDATE gallery_tokens SET expires_at = :expires_at WHERE id = :id',
            ['expires_at' => $expiresAt, 'id' => $id]
        );
    }

    /** @return array<int, array<string, mixed>> Tokens expiring within N days. */
    public function expiringSoon(int $days = 3): array
    {
        return $this->select(
            'SELECT t.*, g.title AS gallery_title
               FROM gallery_tokens t
               JOIN galleries g ON g.id = t.gallery_id
              WHERE t.revoked_at IS NULL
                AND t.expires_at IS NOT NULL
                AND t.expires_at > :now
                AND t.expires_at <= :limit
              ORDER BY t.expires_at ASC',
            ['now' => $this->now(), 'limit' => date('Y-m-d H:i:s', time() + ($days * 86400))]
        );
    }

    public static function isRevoked(array $token): bool
    {
        return ($token['revoked_at'] ?? null) !== null && $token['revoked_at'] !== '';
    }

    public static function hasExpired(array $token): bool
    {
        $expiresAt = $token['expires_at'] ?? null;

        if ($expiresAt === null || $expiresAt === '') {
            return false;
        }

        $timestamp = strtotime((string) $expiresAt);

        return $timestamp !== false && $timestamp < time();
    }
}
