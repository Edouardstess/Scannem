<?php

declare(strict_types=1);

namespace App\Repositories;

final class BookingRepository extends Repository
{
    protected string $table = 'bookings';

    protected array $fillable = [
        'name', 'email', 'phone', 'service_id', 'service_label', 'preferred_date',
        'location', 'message', 'status', 'ip_address', 'created_at', 'handled_at',
    ];

    /** @return array{rows: array<int, array<string, mixed>>, total: int} */
    public function paginate(int $page, int $perPage, string $status = ''): array
    {
        $where = '';
        $bindings = [];

        if ($status !== '') {
            $where = ' WHERE b.status = :status';
            $bindings['status'] = $status;
        }

        $total = (int) $this->scalar('SELECT COUNT(*) FROM bookings b' . $where, $bindings);

        $bindings['limit'] = $perPage;
        $bindings['offset'] = max(0, ($page - 1) * $perPage);

        $rows = $this->select(
            'SELECT b.*, s.title AS service_title
               FROM bookings b
               LEFT JOIN services s ON s.id = b.service_id' . $where . '
              ORDER BY b.id DESC LIMIT :limit OFFSET :offset',
            $bindings
        );

        return ['rows' => $rows, 'total' => $total];
    }

    public function countPending(): int
    {
        return $this->count("status = 'pending'");
    }

    public function setStatus(int $id, string $status): void
    {
        $this->run(
            'UPDATE bookings SET status = :status, handled_at = :now WHERE id = :id',
            ['status' => $status, 'now' => $this->now(), 'id' => $id]
        );
    }

    public function countRecentFromIp(string $ip, int $seconds = 3600): int
    {
        return $this->count(
            'ip_address = :ip AND created_at >= :since',
            ['ip' => $ip, 'since' => date('Y-m-d H:i:s', time() - $seconds)]
        );
    }
}
