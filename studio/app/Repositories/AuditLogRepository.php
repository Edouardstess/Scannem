<?php

declare(strict_types=1);

namespace App\Repositories;

final class AuditLogRepository extends Repository
{
    protected string $table = 'audit_logs';

    protected array $fillable = [
        'user_id', 'gallery_id', 'photo_id', 'action', 'context',
        'ip_address', 'user_agent', 'created_at',
    ];

    /** @return array{rows: array<int, array<string, mixed>>, total: int} */
    public function paginate(int $page, int $perPage, string $action = '', ?int $galleryId = null): array
    {
        $conditions = [];
        $bindings = [];

        if ($action !== '') {
            $conditions[] = 'a.action = :action';
            $bindings['action'] = $action;
        }

        if ($galleryId !== null) {
            $conditions[] = 'a.gallery_id = :gallery_id';
            $bindings['gallery_id'] = $galleryId;
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        $total = (int) $this->scalar('SELECT COUNT(*) FROM audit_logs a' . $where, $bindings);

        $bindings['limit'] = $perPage;
        $bindings['offset'] = max(0, ($page - 1) * $perPage);

        $rows = $this->select(
            'SELECT a.*, u.name AS user_name, g.title AS gallery_title
               FROM audit_logs a
               LEFT JOIN users u ON u.id = a.user_id
               LEFT JOIN galleries g ON g.id = a.gallery_id' . $where . '
              ORDER BY a.id DESC LIMIT :limit OFFSET :offset',
            $bindings
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<int, array<string, mixed>> */
    public function recent(int $limit = 10): array
    {
        return $this->select(
            'SELECT a.*, u.name AS user_name, g.title AS gallery_title
               FROM audit_logs a
               LEFT JOIN users u ON u.id = a.user_id
               LEFT JOIN galleries g ON g.id = a.gallery_id
              ORDER BY a.id DESC LIMIT :limit',
            ['limit' => $limit]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function forGallery(int $galleryId, int $limit = 20): array
    {
        return $this->select(
            'SELECT a.*, u.name AS user_name FROM audit_logs a
               LEFT JOIN users u ON u.id = a.user_id
              WHERE a.gallery_id = :gallery_id ORDER BY a.id DESC LIMIT :limit',
            ['gallery_id' => $galleryId, 'limit' => $limit]
        );
    }

    /** @return array<int, string> Distinct actions, for the filter dropdown. */
    public function distinctActions(): array
    {
        return array_map(
            static fn (array $row): string => (string) $row['action'],
            $this->select('SELECT DISTINCT action FROM audit_logs ORDER BY action ASC')
        );
    }

    /** Housekeeping: audit rows are not kept forever. */
    public function purgeOlderThan(int $days): int
    {
        return $this->run(
            'DELETE FROM audit_logs WHERE created_at < :threshold',
            ['threshold' => date('Y-m-d H:i:s', time() - ($days * 86400))]
        )->rowCount();
    }
}
