<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ClientRepository;

final class ClientService
{
    public function __construct(private ?ClientRepository $clients = null)
    {
        $this->clients = $clients ?? new ClientRepository();
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): int
    {
        return $this->clients->insert($this->normalise($attributes) + [
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @param array<string, mixed> $attributes */
    public function update(int $id, array $attributes): bool
    {
        return $this->clients->update($id, $this->normalise($attributes) + [
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function delete(int $id): bool
    {
        // Events, galleries, photos and their logs go with the client through
        // ON DELETE CASCADE. The files on disk are removed by GalleryService
        // before this is called; see ClientController::destroy.
        return $this->clients->delete($id);
    }

    public function fullName(array $client): string
    {
        return trim(((string) ($client['first_name'] ?? '')) . ' ' . ((string) ($client['last_name'] ?? '')));
    }

    /**
     * Normalise the attributes a caller supplied.
     *
     * Duplicates what the matching FormRequest already does, deliberately:
     * this service is also called from the seeder and the command line, where
     * no form ran. Normalising twice is a no-op; assuming it happened is not.
     *
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function normalise(array $attributes): array
    {
        $email = isset($attributes['email']) ? strtolower(trim((string) $attributes['email'])) : null;

        return [
            'first_name' => trim((string) ($attributes['first_name'] ?? '')),
            'last_name'  => trim((string) ($attributes['last_name'] ?? '')),
            'email'      => $email === '' ? null : $email,
            'phone'      => $this->nullIfBlank($attributes['phone'] ?? null),
            'company'    => $this->nullIfBlank($attributes['company'] ?? null),
            'notes'      => $this->nullIfBlank($attributes['notes'] ?? null),
        ];
    }

    private function nullIfBlank(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
    }
}
