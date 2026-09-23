<?php

declare(strict_types=1);

namespace App\Repositories;

final class GalleryViewRepository extends Repository
{
    protected string $table = 'gallery_views';

    protected array $fillable = [
        'gallery_id', 'token_id', 'ip_address', 'user_agent', 'created_at',
    ];

    public function countForGallery(int $galleryId): int
    {
        return $this->count('gallery_id = :gallery_id', ['gallery_id' => $galleryId]);
    }

    public function lastForGallery(int $galleryId): ?string
    {
        $value = $this->scalar(
            'SELECT MAX(created_at) FROM gallery_views WHERE gallery_id = :gallery_id',
            ['gallery_id' => $galleryId]
        );

        return $value === null ? null : (string) $value;
    }

    /** @return array<int, array{day: string, total: int}> */
    public function dailyTotals(int $days = 30): array
    {
        $since = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));

        $rows = $this->select(
            'SELECT substr(created_at, 1, 10) AS day, COUNT(*) AS total
               FROM gallery_views
              WHERE created_at >= :since
              GROUP BY substr(created_at, 1, 10)
              ORDER BY day ASC',
            ['since' => $since]
        );

        return array_map(
            static fn (array $row): array => ['day' => (string) $row['day'], 'total' => (int) $row['total']],
            $rows
        );
    }

    /**
     * Was this visitor already counted recently?
     *
     * A refresh should not inflate the view count, so a gallery/IP pair is
     * counted at most once per window.
     */
    public function seenRecently(int $galleryId, string $ip, int $windowSeconds = 1800): bool
    {
        $count = (int) $this->scalar(
            'SELECT COUNT(*) FROM gallery_views
              WHERE gallery_id = :gallery_id AND ip_address = :ip AND created_at >= :since',
            [
                'gallery_id' => $galleryId,
                'ip'         => $ip,
                'since'      => date('Y-m-d H:i:s', time() - $windowSeconds),
            ]
        );

        return $count > 0;
    }
}
