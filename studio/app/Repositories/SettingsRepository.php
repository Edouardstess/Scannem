<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class SettingsRepository extends Repository
{
    protected string $table = 'settings';

    protected array $fillable = ['setting_key', 'setting_value', 'setting_group', 'setting_type', 'updated_at'];

    /**
     * Every setting as key => cast value.
     *
     * Named allValues() rather than all() because the base repository's all()
     * returns rows; this returns a key/value map.
     *
     * @return array<string, mixed>
     */
    public function allValues(): array
    {
        $result = [];

        foreach ($this->select('SELECT setting_key, setting_value, setting_type FROM settings') as $row) {
            $result[(string) $row['setting_key']] = $this->cast($row['setting_value'], (string) $row['setting_type']);
        }

        return $result;
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    public function grouped(): array
    {
        $grouped = [];

        foreach ($this->select('SELECT * FROM settings ORDER BY setting_group ASC, id ASC') as $row) {
            $grouped[(string) $row['setting_group']][] = $row;
        }

        return $grouped;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $row = $this->selectOne(
            'SELECT setting_value, setting_type FROM settings WHERE setting_key = :key',
            ['key' => $key]
        );

        return $row === null ? $default : $this->cast($row['setting_value'], (string) $row['setting_type']);
    }

    /**
     * Upsert a setting.
     *
     * Written as a read-then-write inside a transaction rather than with
     * INSERT ... ON DUPLICATE KEY, which SQLite does not understand; the
     * unique index on setting_key is what actually prevents duplicates.
     */
    public function set(string $key, mixed $value, string $group = 'general', string $type = 'string'): void
    {
        Database::transaction(function () use ($key, $value, $group, $type): void {
            $exists = (int) $this->scalar(
                'SELECT COUNT(*) FROM settings WHERE setting_key = :key',
                ['key' => $key]
            ) > 0;

            $stored = $this->serialize($value, $type);

            if ($exists) {
                $this->run(
                    'UPDATE settings SET setting_value = :value, setting_type = :type, updated_at = :now
                      WHERE setting_key = :key',
                    ['value' => $stored, 'type' => $type, 'now' => $this->now(), 'key' => $key]
                );

                return;
            }

            $this->run(
                'INSERT INTO settings (setting_key, setting_value, setting_group, setting_type, updated_at)
                 VALUES (:key, :value, :group, :type, :now)',
                [
                    'key'   => $key,
                    'value' => $stored,
                    'group' => $group,
                    'type'  => $type,
                    'now'   => $this->now(),
                ]
            );
        });
    }

    /** @param array<string, mixed> $values */
    public function setMany(array $values, string $group = 'general'): void
    {
        Database::transaction(function () use ($values, $group): void {
            foreach ($values as $key => $value) {
                $type = match (true) {
                    is_bool($value)  => 'boolean',
                    is_int($value)   => 'integer',
                    is_array($value) => 'json',
                    default          => 'string',
                };

                $this->set((string) $key, $value, $group, $type);
            }
        });
    }

    private function cast(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => in_array((string) $value, ['1', 'true', 'yes', 'on'], true),
            'integer' => (int) $value,
            'json'    => json_decode((string) $value, true) ?? [],
            default   => (string) $value,
        };
    }

    private function serialize(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) (int) $value,
            'json'    => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
            default   => (string) $value,
        };
    }
}
