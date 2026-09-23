<?php

declare(strict_types=1);

namespace App\Repositories;

final class MessageRepository extends Repository
{
    protected string $table = 'messages';

    protected array $fillable = [
        'name', 'email', 'phone', 'subject', 'preferred_date', 'message',
        'status', 'ip_address', 'created_at', 'read_at',
    ];

    /** @return array{rows: array<int, array<string, mixed>>, total: int} */
    public function paginate(int $page, int $perPage, string $status = ''): array
    {
        $where = '';
        $bindings = [];

        if ($status !== '') {
            $where = ' WHERE status = :status';
            $bindings['status'] = $status;
        }

        $total = (int) $this->scalar('SELECT COUNT(*) FROM messages' . $where, $bindings);

        $bindings['limit'] = $perPage;
        $bindings['offset'] = max(0, ($page - 1) * $perPage);

        $rows = $this->select(
            'SELECT * FROM messages' . $where . ' ORDER BY id DESC LIMIT :limit OFFSET :offset',
            $bindings
        );

        return ['rows' => $rows, 'total' => $total];
    }

    public function countUnread(): int
    {
        return $this->count("status = 'new'");
    }

    public function markRead(int $id): void
    {
        $this->run(
            "UPDATE messages SET status = 'read', read_at = :now WHERE id = :id AND status = 'new'",
            ['now' => $this->now(), 'id' => $id]
        );
    }

    public function setStatus(int $id, string $status): void
    {
        $this->run('UPDATE messages SET status = :status WHERE id = :id', ['status' => $status, 'id' => $id]);
    }

    /** Requests received from one IP in the last hour, for spam throttling. */
    public function countRecentFromIp(string $ip, int $seconds = 3600): int
    {
        return $this->count(
            'ip_address = :ip AND created_at >= :since',
            ['ip' => $ip, 'since' => date('Y-m-d H:i:s', time() - $seconds)]
        );
    }
}
