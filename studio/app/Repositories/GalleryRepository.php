<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\GalleryStatus;

final class GalleryRepository extends Repository
{
    protected string $table = 'galleries';

    protected array $fillable = [
        'event_id', 'title', 'description', 'cover_photo_id', 'password_hash',
        'watermark_enabled', 'download_enabled', 'selection_enabled',
        'status', 'expires_at', 'created_at', 'updated_at',
    ];

    /** @return array{rows: array<int, array<string, mixed>>, total: int} */
    public function paginate(int $page, int $perPage, string $search = '', string $status = '', ?int $eventId = null): array
    {
        $conditions = [];
        $bindings = [];

        if ($search !== '') {
            $conditions[] = '(g.title LIKE :search OR e.title LIKE :search OR c.last_name LIKE :search)';
            $bindings['search'] = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
        }

        if ($status !== '') {
            $conditions[] = 'g.status = :status';
            $bindings['status'] = $status;
        }

        if ($eventId !== null) {
            $conditions[] = 'g.event_id = :event_id';
            $bindings['event_id'] = $eventId;
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        $total = (int) $this->scalar(
            'SELECT COUNT(*) FROM galleries g
               JOIN events e ON e.id = g.event_id
               JOIN clients c ON c.id = e.client_id' . $where,
            $bindings
        );

        $sql = 'SELECT g.*, e.title AS event_title, e.event_date, e.client_id,
                       c.first_name, c.last_name,
                       (SELECT COUNT(*) FROM photos p WHERE p.gallery_id = g.id) AS photos_count,
                       (SELECT COUNT(*) FROM gallery_views v WHERE v.gallery_id = g.id) AS views_count,
                       (SELECT COUNT(*) FROM download_logs d WHERE d.gallery_id = g.id) AS downloads_count
                  FROM galleries g
                  JOIN events e ON e.id = g.event_id
                  JOIN clients c ON c.id = e.client_id' . $where . '
                 ORDER BY g.created_at DESC, g.id DESC
                 LIMIT :limit OFFSET :offset';

        $bindings['limit'] = $perPage;
        $bindings['offset'] = max(0, ($page - 1) * $perPage);

        return ['rows' => $this->select($sql, $bindings), 'total' => $total];
    }

    /**
     * Full gallery record with its event, client and counters.
     *
     * This is the shape every gallery screen — admin and client — works from.
     *
     * @return array<string, mixed>|null
     */
    public function findDetailed(int $id): ?array
    {
        return $this->selectOne(
            'SELECT g.*, e.title AS event_title, e.event_date, e.location, e.event_type, e.client_id,
                    c.first_name, c.last_name, c.email AS client_email, c.company,
                    (SELECT COUNT(*) FROM photos p WHERE p.gallery_id = g.id) AS photos_count,
                    (SELECT COALESCE(SUM(p.file_size), 0) FROM photos p WHERE p.gallery_id = g.id) AS total_bytes,
                    (SELECT COUNT(*) FROM gallery_views v WHERE v.gallery_id = g.id) AS views_count,
                    (SELECT COUNT(*) FROM download_logs d WHERE d.gallery_id = g.id) AS downloads_count,
                    (SELECT MAX(v2.created_at) FROM gallery_views v2 WHERE v2.gallery_id = g.id) AS last_viewed_at,
                    (SELECT MAX(d2.created_at) FROM download_logs d2 WHERE d2.gallery_id = g.id) AS last_downloaded_at
               FROM galleries g
               JOIN events e ON e.id = g.event_id
               JOIN clients c ON c.id = e.client_id
              WHERE g.id = :id',
            ['id' => $id]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function recent(int $limit = 5): array
    {
        return $this->select(
            'SELECT g.*, e.title AS event_title, c.first_name, c.last_name,
                    (SELECT COUNT(*) FROM photos p WHERE p.gallery_id = g.id) AS photos_count
               FROM galleries g
               JOIN events e ON e.id = g.event_id
               JOIN clients c ON c.id = e.client_id
              ORDER BY g.created_at DESC, g.id DESC LIMIT :limit',
            ['limit' => $limit]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function forEvent(int $eventId): array
    {
        return $this->select(
            'SELECT g.*, (SELECT COUNT(*) FROM photos p WHERE p.gallery_id = g.id) AS photos_count
               FROM galleries g WHERE g.event_id = :event_id ORDER BY g.created_at DESC',
            ['event_id' => $eventId]
        );
    }

    /** @return array<int, array<string, mixed>> Most viewed galleries. */
    public function mostViewed(int $limit = 5): array
    {
        return $this->select(
            'SELECT g.id, g.title,
                    (SELECT COUNT(*) FROM gallery_views v WHERE v.gallery_id = g.id) AS views_count,
                    (SELECT COUNT(*) FROM download_logs d WHERE d.gallery_id = g.id) AS downloads_count
               FROM galleries g
              ORDER BY views_count DESC, g.id DESC LIMIT :limit',
            ['limit' => $limit]
        );
    }

    public function setStatus(int $id, string $status): void
    {
        $this->update($id, ['status' => $status, 'updated_at' => $this->now()]);
    }

    public function setCoverPhoto(int $id, ?int $photoId): void
    {
        $this->update($id, ['cover_photo_id' => $photoId, 'updated_at' => $this->now()]);
    }

    /** Clear a cover reference when the underlying photo is deleted. */
    public function clearCoverPhoto(int $photoId): void
    {
        $this->run(
            'UPDATE galleries SET cover_photo_id = NULL WHERE cover_photo_id = :photo_id',
            ['photo_id' => $photoId]
        );
    }

    /**
     * Has this gallery passed its expiry date?
     *
     * Expiry is evaluated at read time rather than by a cron job: a scheduled
     * task that fails to run would silently leave galleries reachable past
     * their date, which is exactly the failure this must not have.
     */
    public static function hasExpired(array $gallery): bool
    {
        $expiresAt = $gallery['expires_at'] ?? null;

        if ($expiresAt === null || $expiresAt === '') {
            return false;
        }

        $timestamp = strtotime((string) $expiresAt);

        return $timestamp !== false && $timestamp < time();
    }

    public static function isReachable(array $gallery): bool
    {
        return GalleryStatus::isReachable((string) ($gallery['status'] ?? ''))
            && !self::hasExpired($gallery);
    }

    public function countByStatus(string $status): int
    {
        return $this->count('status = :status', ['status' => $status]);
    }
}
