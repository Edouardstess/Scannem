<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Persistent attempt counters.
 *
 * The store is the database rather than the session, because an attacker
 * controls their own cookies: a session-based counter is bypassed by deleting
 * a cookie.
 */
final class RateLimitRepository extends Repository
{
    protected string $table = 'rate_limits';

    protected array $fillable = ['rate_key', 'attempts', 'expires_at', 'created_at'];

    public function attempts(string $key): int
    {
        $row = $this->selectOne(
            'SELECT attempts, expires_at FROM rate_limits WHERE rate_key = :key',
            ['key' => $key]
        );

        if ($row === null) {
            return 0;
        }

        if (strtotime((string) $row['expires_at']) < time()) {
            $this->clear($key);

            return 0;
        }

        return (int) $row['attempts'];
    }

    public function hit(string $key, int $decaySeconds): int
    {
        return Database::transaction(function () use ($key, $decaySeconds): int {
            $row = $this->selectOne(
                'SELECT id, attempts, expires_at FROM rate_limits WHERE rate_key = :key',
                ['key' => $key]
            );

            $now = time();

            if ($row === null || strtotime((string) $row['expires_at']) < $now) {
                $expires = date('Y-m-d H:i:s', $now + $decaySeconds);

                if ($row !== null) {
                    $this->run(
                        'UPDATE rate_limits SET attempts = 1, expires_at = :expires WHERE id = :id',
                        ['expires' => $expires, 'id' => (int) $row['id']]
                    );

                    return 1;
                }

                $this->run(
                    'INSERT INTO rate_limits (rate_key, attempts, expires_at, created_at)
                     VALUES (:key, 1, :expires, :now)',
                    ['key' => $key, 'expires' => $expires, 'now' => date('Y-m-d H:i:s', $now)]
                );

                return 1;
            }

            $this->run(
                'UPDATE rate_limits SET attempts = attempts + 1 WHERE id = :id',
                ['id' => (int) $row['id']]
            );

            return (int) $row['attempts'] + 1;
        });
    }

    public function clear(string $key): void
    {
        $this->run('DELETE FROM rate_limits WHERE rate_key = :key', ['key' => $key]);
    }

    /** Seconds until the window resets, for the "try again in N minutes" message. */
    public function availableIn(string $key): int
    {
        $value = $this->scalar('SELECT expires_at FROM rate_limits WHERE rate_key = :key', ['key' => $key]);

        if ($value === null) {
            return 0;
        }

        return max(0, (int) strtotime((string) $value) - time());
    }

    public function purgeExpired(): int
    {
        return $this->run(
            'DELETE FROM rate_limits WHERE expires_at < :now',
            ['now' => date('Y-m-d H:i:s')]
        )->rowCount();
    }
}
