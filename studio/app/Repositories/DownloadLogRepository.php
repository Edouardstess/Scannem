<?php

declare(strict_types=1);

namespace App\Repositories;

final class DownloadLogRepository extends Repository
{
    protected string $table = 'download_logs';

    protected array $fillable = [
        'gallery_id', 'photo_id', 'token_id', 'kind', 'photo_count',
        'bytes', 'ip_address', 'user_agent', 'created_at',
    ];

    public function countForGallery(int $galleryId): int
    {
        return $this->count('gallery_id = :gallery_id', ['gallery_id' => $galleryId]);
    }

    public function photosDownloadedForGallery(int $galleryId): int
    {
        return (int) $this->scalar(
            'SELECT COALESCE(SUM(photo_count), 0) FROM download_logs WHERE gallery_id = :gallery_id',
            ['gallery_id' => $galleryId]
        );
    }

    public function lastForGallery(int $galleryId): ?string
    {
        $value = $this->scalar(
            'SELECT MAX(created_at) FROM download_logs WHERE gallery_id = :gallery_id',
            ['gallery_id' => $galleryId]
        );

        return $value === null ? null : (string) $value;
    }

    /**
     * Daily totals for the activity chart.
     *
     * substr() on the datetime rather than a driver-specific date function:
     * the expression is identical on MySQL and SQLite, and the column is
     * stored in a fixed 'Y-m-d H:i:s' shape by every writer.
     *
     * @return array<int, array{day: string, total: int}>
     */
    public function dailyTotals(int $days = 30): array
    {
        $since = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));

        $rows = $this->select(
            'SELECT substr(created_at, 1, 10) AS day, COUNT(*) AS total
               FROM download_logs
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

    public function totalBytes(): int
    {
        return (int) $this->scalar('SELECT COALESCE(SUM(bytes), 0) FROM download_logs');
    }

    /** @return array<int, array<string, mixed>> */
    public function recentForGallery(int $galleryId, int $limit = 20): array
    {
        return $this->select(
            'SELECT d.*, p.original_filename
               FROM download_logs d
               LEFT JOIN photos p ON p.id = d.photo_id
              WHERE d.gallery_id = :gallery_id
              ORDER BY d.id DESC LIMIT :limit',
            ['gallery_id' => $galleryId, 'limit' => $limit]
        );
    }
}
