<?php

declare(strict_types=1);

namespace App\Repositories;

final class ServiceRepository extends Repository
{
    protected string $table = 'services';

    protected array $fillable = [
        'title', 'slug', 'summary', 'description', 'price_from', 'currency',
        'duration', 'deliverables', 'image_path', 'sort_order', 'status',
        'created_at', 'updated_at',
    ];

    /** @return array<int, array<string, mixed>> */
    public function published(): array
    {
        return $this->select(
            "SELECT * FROM services WHERE status = 'published' ORDER BY sort_order ASC, id ASC"
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function allOrdered(): array
    {
        return $this->select('SELECT * FROM services ORDER BY sort_order ASC, id ASC');
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        return $this->selectOne('SELECT * FROM services WHERE slug = :slug', ['slug' => $slug]);
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM services WHERE slug = :slug';
        $bindings = ['slug' => $slug];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $bindings['id'] = $exceptId;
        }

        return (int) $this->scalar($sql, $bindings) > 0;
    }

    public function nextSortOrder(): int
    {
        return (int) $this->scalar('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM services');
    }
}
