<?php

declare(strict_types=1);

namespace App\Repositories;

final class UserRepository extends Repository
{
    protected string $table = 'users';

    protected array $fillable = [
        'name', 'email', 'password_hash', 'role', 'status',
        'last_login_at', 'created_at', 'updated_at',
    ];

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        return $this->selectOne(
            'SELECT * FROM users WHERE email = :email LIMIT 1',
            ['email' => strtolower(trim($email))]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function allActive(): array
    {
        return $this->select("SELECT * FROM users WHERE status = 'active' ORDER BY name ASC");
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE email = :email';
        $bindings = ['email' => strtolower(trim($email))];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $bindings['id'] = $exceptId;
        }

        return (int) $this->scalar($sql, $bindings) > 0;
    }

    public function create(string $name, string $email, string $plainPassword, string $role): int
    {
        return $this->insert([
            'name'          => $name,
            'email'         => strtolower(trim($email)),
            'password_hash' => password_hash($plainPassword, PASSWORD_DEFAULT),
            'role'          => $role,
            'status'        => 'active',
            'created_at'    => $this->now(),
            'updated_at'    => $this->now(),
        ]);
    }

    public function updatePassword(int $id, string $plainPassword): void
    {
        $this->update($id, [
            'password_hash' => password_hash($plainPassword, PASSWORD_DEFAULT),
            'updated_at'    => $this->now(),
        ]);
    }

    public function touchLogin(int $id): void
    {
        $this->update($id, ['last_login_at' => $this->now()]);
    }

    /** Re-hash after a PHP default algorithm upgrade, on a successful login. */
    public function rehashIfNeeded(int $id, string $hash, string $plainPassword): void
    {
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $this->updatePassword($id, $plainPassword);
        }
    }
}
