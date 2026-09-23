<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use PDOStatement;

/**
 * Base repository.
 *
 * Provides the small set of query helpers the concrete repositories need.
 * Every helper binds values through prepared statements; identifiers (table
 * and column names) are never taken from caller input, only from the
 * constants each repository declares.
 */
abstract class Repository
{
    protected string $table = '';

    /** Columns that may be written through fill/create/update. */
    protected array $fillable = [];

    protected function pdo(): PDO
    {
        return Database::connection();
    }

    protected function run(string $sql, array $bindings = []): PDOStatement
    {
        $statement = $this->pdo()->prepare($sql);

        foreach ($bindings as $key => $value) {
            $parameter = is_int($key) ? $key + 1 : ':' . ltrim((string) $key, ':');

            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_INT,
                $value === null => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };

            $statement->bindValue($parameter, is_bool($value) ? (int) $value : $value, $type);
        }

        $statement->execute();

        return $statement;
    }

    /** @return array<int, array<string, mixed>> */
    protected function select(string $sql, array $bindings = []): array
    {
        return $this->run($sql, $bindings)->fetchAll();
    }

    /** @return array<string, mixed>|null */
    protected function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = $this->run($sql, $bindings)->fetch();

        return $row === false ? null : $row;
    }

    protected function scalar(string $sql, array $bindings = []): mixed
    {
        $value = $this->run($sql, $bindings)->fetchColumn();

        return $value === false ? null : $value;
    }

    public function count(string $where = '', array $bindings = []): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . $this->table . ($where !== '' ? ' WHERE ' . $where : '');

        return (int) $this->scalar($sql, $bindings);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->selectOne('SELECT * FROM ' . $this->table . ' WHERE id = :id', ['id' => $id]);
    }

    /** @return array<int, array<string, mixed>> */
    public function all(string $orderBy = 'id DESC'): array
    {
        return $this->select('SELECT * FROM ' . $this->table . ' ORDER BY ' . $this->safeOrderBy($orderBy));
    }

    /** @param array<string, mixed> $attributes */
    public function insert(array $attributes): int
    {
        $attributes = $this->onlyFillable($attributes);

        if ($attributes === []) {
            throw new \InvalidArgumentException('No writable attributes supplied for ' . static::class . '.');
        }

        $columns = array_keys($attributes);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $this->run($sql, $attributes);

        return (int) $this->pdo()->lastInsertId();
    }

    /** @param array<string, mixed> $attributes */
    public function update(int $id, array $attributes): bool
    {
        $attributes = $this->onlyFillable($attributes);

        if ($attributes === []) {
            return false;
        }

        $assignments = [];

        foreach (array_keys($attributes) as $column) {
            $assignments[] = $column . ' = :' . $column;
        }

        if (in_array('updated_at', $this->fillable, true) && !isset($attributes['updated_at'])) {
            $assignments[] = 'updated_at = :updated_at';
            $attributes['updated_at'] = date('Y-m-d H:i:s');
        }

        $attributes['id'] = $id;

        $sql = sprintf('UPDATE %s SET %s WHERE id = :id', $this->table, implode(', ', $assignments));

        return $this->run($sql, $attributes)->rowCount() >= 0;
    }

    public function delete(int $id): bool
    {
        return $this->run('DELETE FROM ' . $this->table . ' WHERE id = :id', ['id' => $id])->rowCount() > 0;
    }

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    protected function onlyFillable(array $attributes): array
    {
        if ($this->fillable === []) {
            return $attributes;
        }

        return array_intersect_key($attributes, array_flip($this->fillable));
    }

    /**
     * Whitelist an ORDER BY clause.
     *
     * ORDER BY cannot be parameterised, so the clause is matched against a
     * strict pattern and the column must be one the repository declares.
     */
    protected function safeOrderBy(string $orderBy, array $allowedColumns = []): string
    {
        $allowedColumns = $allowedColumns === [] ? $this->sortableColumns() : $allowedColumns;
        $parts = preg_split('/\s+/', trim($orderBy)) ?: [];
        $column = $parts[0] ?? 'id';
        $direction = strtoupper($parts[1] ?? 'DESC');

        if (!in_array($column, $allowedColumns, true)) {
            $column = 'id';
        }

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'DESC';
        }

        return $column . ' ' . $direction;
    }

    /** @return array<int, string> */
    protected function sortableColumns(): array
    {
        return array_merge(['id', 'created_at', 'updated_at'], $this->fillable);
    }

    protected function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /** Placeholder list for a WHERE ... IN (...) clause. */
    protected function inPlaceholders(array $values, string $prefix = 'p'): array
    {
        $placeholders = [];
        $bindings = [];

        foreach (array_values($values) as $index => $value) {
            $key = $prefix . $index;
            $placeholders[] = ':' . $key;
            $bindings[$key] = $value;
        }

        return [implode(', ', $placeholders), $bindings];
    }
}
