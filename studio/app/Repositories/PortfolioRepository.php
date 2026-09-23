<?php

declare(strict_types=1);

namespace App\Repositories;

final class PortfolioRepository extends Repository
{
    protected string $table = 'portfolio_items';

    protected array $fillable = [
        'category_id', 'title', 'description', 'image_path', 'thumbnail_path',
        'width', 'height', 'featured', 'sort_order', 'status', 'created_at', 'updated_at',
    ];

    /** @return array<int, array<string, mixed>> */
    public function publishedItems(?string $categorySlug = null, int $limit = 0): array
    {
        $sql = "SELECT i.*, c.name AS category_name, c.slug AS category_slug
                  FROM portfolio_items i
                  LEFT JOIN portfolio_categories c ON c.id = i.category_id
                 WHERE i.status = 'published'";
        $bindings = [];

        if ($categorySlug !== null && $categorySlug !== '') {
            $sql .= ' AND c.slug = :slug';
            $bindings['slug'] = $categorySlug;
        }

        $sql .= ' ORDER BY i.sort_order ASC, i.id DESC';

        if ($limit > 0) {
            $sql .= ' LIMIT :limit';
            $bindings['limit'] = $limit;
        }

        return $this->select($sql, $bindings);
    }

    /** @return array<int, array<string, mixed>> */
    public function featured(int $limit = 8): array
    {
        return $this->select(
            "SELECT i.*, c.name AS category_name, c.slug AS category_slug
               FROM portfolio_items i
               LEFT JOIN portfolio_categories c ON c.id = i.category_id
              WHERE i.status = 'published' AND i.featured = 1
              ORDER BY i.sort_order ASC, i.id DESC LIMIT :limit",
            ['limit' => $limit]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function allItems(): array
    {
        return $this->select(
            'SELECT i.*, c.name AS category_name
               FROM portfolio_items i
               LEFT JOIN portfolio_categories c ON c.id = i.category_id
              ORDER BY i.sort_order ASC, i.id DESC'
        );
    }

    /** @return array<string, mixed>|null */
    public function findItem(int $id): ?array
    {
        return $this->find($id);
    }

    public function nextSortOrder(): int
    {
        return (int) $this->scalar('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM portfolio_items');
    }

    // --- Categories --------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    public function categories(bool $publishedOnly = false): array
    {
        $sql = "SELECT c.*, (SELECT COUNT(*) FROM portfolio_items i
                              WHERE i.category_id = c.id AND i.status = 'published') AS items_count
                  FROM portfolio_categories c";

        if ($publishedOnly) {
            $sql .= " WHERE c.status = 'published'";
        }

        return $this->select($sql . ' ORDER BY c.sort_order ASC, c.name ASC');
    }

    /** @return array<int, array<string, mixed>> Categories that actually have images. */
    public function categoriesWithItems(): array
    {
        return array_values(array_filter(
            $this->categories(true),
            static fn (array $category): bool => (int) $category['items_count'] > 0
        ));
    }

    /** @return array<string, mixed>|null */
    public function findCategory(int $id): ?array
    {
        return $this->selectOne('SELECT * FROM portfolio_categories WHERE id = :id', ['id' => $id]);
    }

    /** @return array<string, mixed>|null */
    public function findCategoryBySlug(string $slug): ?array
    {
        return $this->selectOne('SELECT * FROM portfolio_categories WHERE slug = :slug', ['slug' => $slug]);
    }

    public function createCategory(array $attributes): int
    {
        $attributes['created_at'] ??= $this->now();
        $columns = ['name', 'slug', 'description', 'sort_order', 'status', 'created_at', 'updated_at'];
        $attributes = array_intersect_key($attributes, array_flip($columns));

        $names = array_keys($attributes);

        $this->run(
            sprintf(
                'INSERT INTO portfolio_categories (%s) VALUES (%s)',
                implode(', ', $names),
                implode(', ', array_map(static fn (string $c): string => ':' . $c, $names))
            ),
            $attributes
        );

        return (int) $this->pdo()->lastInsertId();
    }

    public function updateCategory(int $id, array $attributes): void
    {
        $columns = ['name', 'slug', 'description', 'sort_order', 'status'];
        $attributes = array_intersect_key($attributes, array_flip($columns));

        if ($attributes === []) {
            return;
        }

        $assignments = array_map(static fn (string $c): string => $c . ' = :' . $c, array_keys($attributes));
        $assignments[] = 'updated_at = :updated_at';
        $attributes['updated_at'] = $this->now();
        $attributes['id'] = $id;

        $this->run(
            'UPDATE portfolio_categories SET ' . implode(', ', $assignments) . ' WHERE id = :id',
            $attributes
        );
    }

    public function deleteCategory(int $id): bool
    {
        return $this->run('DELETE FROM portfolio_categories WHERE id = :id', ['id' => $id])->rowCount() > 0;
    }

    public function categorySlugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM portfolio_categories WHERE slug = :slug';
        $bindings = ['slug' => $slug];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $bindings['id'] = $exceptId;
        }

        return (int) $this->scalar($sql, $bindings) > 0;
    }

    public function nextCategorySortOrder(): int
    {
        return (int) $this->scalar('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM portfolio_categories');
    }
}
