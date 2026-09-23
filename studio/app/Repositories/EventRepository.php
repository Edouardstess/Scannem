<?php

declare(strict_types=1);

namespace App\Repositories;

final class EventRepository extends Repository
{
    protected string $table = 'events';

    protected array $fillable = [
        'client_id', 'title', 'description', 'event_type', 'event_date',
        'location', 'status', 'created_at', 'updated_at',
    ];

    /** @return array{rows: array<int, array<string, mixed>>, total: int} */
    public function paginate(int $page, int $perPage, string $search = '', string $status = '', ?int $clientId = null): array
    {
        $conditions = [];
        $bindings = [];

        if ($search !== '') {
            $conditions[] = '(e.title LIKE :search OR e.location LIKE :search)';
            $bindings['search'] = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
        }

        if ($status !== '') {
            $conditions[] = 'e.status = :status';
            $bindings['status'] = $status;
        }

        if ($clientId !== null) {
            $conditions[] = 'e.client_id = :client_id';
            $bindings['client_id'] = $clientId;
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        $total = (int) $this->scalar('SELECT COUNT(*) FROM events e' . $where, $bindings);

        $sql = 'SELECT e.*, c.first_name, c.last_name, c.company,
                       (SELECT COUNT(*) FROM galleries g WHERE g.event_id = e.id) AS galleries_count
                  FROM events e
                  JOIN clients c ON c.id = e.client_id' . $where . '
                 ORDER BY e.event_date IS NULL, e.event_date DESC, e.id DESC
                 LIMIT :limit OFFSET :offset';

        $bindings['limit'] = $perPage;
        $bindings['offset'] = max(0, ($page - 1) * $perPage);

        return ['rows' => $this->select($sql, $bindings), 'total' => $total];
    }

    /** @return array<string, mixed>|null */
    public function findWithClient(int $id): ?array
    {
        return $this->selectOne(
            'SELECT e.*, c.first_name, c.last_name, c.email AS client_email, c.company
               FROM events e JOIN clients c ON c.id = e.client_id
              WHERE e.id = :id',
            ['id' => $id]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function forClient(int $clientId): array
    {
        return $this->select(
            'SELECT e.*, (SELECT COUNT(*) FROM galleries g WHERE g.event_id = e.id) AS galleries_count
               FROM events e WHERE e.client_id = :client_id
              ORDER BY e.event_date IS NULL, e.event_date DESC, e.id DESC',
            ['client_id' => $clientId]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function listForSelect(): array
    {
        return $this->select(
            'SELECT e.id, e.title, e.event_date, c.first_name, c.last_name
               FROM events e JOIN clients c ON c.id = e.client_id
              ORDER BY e.event_date IS NULL, e.event_date DESC, e.id DESC'
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function upcoming(int $limit = 5): array
    {
        return $this->select(
            "SELECT e.*, c.first_name, c.last_name
               FROM events e JOIN clients c ON c.id = e.client_id
              WHERE e.event_date >= :today AND e.status IN ('draft', 'active')
              ORDER BY e.event_date ASC LIMIT :limit",
            ['today' => date('Y-m-d'), 'limit' => $limit]
        );
    }
}
