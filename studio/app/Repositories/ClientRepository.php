<?php

declare(strict_types=1);

namespace App\Repositories;

final class ClientRepository extends Repository
{
    protected string $table = 'clients';

    protected array $fillable = [
        'first_name', 'last_name', 'email', 'phone', 'company', 'notes',
        'created_at', 'updated_at',
    ];

    /**
     * Paginated, searchable client list with the counters the index needs.
     *
     * The event, gallery and last-activity counters are computed as correlated
     * sub-selects in one round trip; issuing them per row would be an N+1 on a
     * page that routinely shows hundreds of clients.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function paginate(int $page, int $perPage, string $search = '', string $orderBy = 'created_at DESC'): array
    {
        $where = '';
        $bindings = [];

        if ($search !== '') {
            $where = ' WHERE (c.first_name LIKE :search OR c.last_name LIKE :search
                       OR c.email LIKE :search OR c.company LIKE :search OR c.phone LIKE :search)';
            $bindings['search'] = '%' . $this->escapeLike($search) . '%';
        }

        $total = (int) $this->scalar('SELECT COUNT(*) FROM clients c' . $where, $bindings);

        $offset = max(0, ($page - 1) * $perPage);
        $sort = $this->safeOrderBy($orderBy, ['id', 'first_name', 'last_name', 'email', 'company', 'created_at']);

        $sql = 'SELECT c.*,
                       (SELECT COUNT(*) FROM events e WHERE e.client_id = c.id) AS events_count,
                       (SELECT COUNT(*) FROM galleries g
                          JOIN events e2 ON e2.id = g.event_id
                         WHERE e2.client_id = c.id) AS galleries_count,
                       (SELECT MAX(gv.created_at) FROM gallery_views gv
                          JOIN galleries g2 ON g2.id = gv.gallery_id
                          JOIN events e3 ON e3.id = g2.event_id
                         WHERE e3.client_id = c.id) AS last_activity_at
                  FROM clients c' . $where . '
                 ORDER BY c.' . $sort . '
                 LIMIT :limit OFFSET :offset';

        $bindings['limit'] = $perPage;
        $bindings['offset'] = $offset;

        return ['rows' => $this->select($sql, $bindings), 'total' => $total];
    }

    /** @return array<string, mixed>|null */
    public function findWithCounters(int $id): ?array
    {
        return $this->selectOne(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM events e WHERE e.client_id = c.id) AS events_count,
                    (SELECT COUNT(*) FROM galleries g
                       JOIN events e2 ON e2.id = g.event_id
                      WHERE e2.client_id = c.id) AS galleries_count
               FROM clients c WHERE c.id = :id',
            ['id' => $id]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function listForSelect(): array
    {
        return $this->select(
            "SELECT id, first_name, last_name, company FROM clients ORDER BY last_name ASC, first_name ASC"
        );
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        if (trim($email) === '') {
            return false;
        }

        $sql = 'SELECT COUNT(*) FROM clients WHERE email = :email';
        $bindings = ['email' => strtolower(trim($email))];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $bindings['id'] = $exceptId;
        }

        return (int) $this->scalar($sql, $bindings) > 0;
    }

    /** Escape LIKE wildcards so a search for "100%" does not match everything. */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
