<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EventStatus;
use App\Repositories\EventRepository;

final class EventService
{
    public const TYPES = [
        'wedding'    => 'Mariage',
        'portrait'   => 'Portrait',
        'graduation' => 'Remise de diplôme',
        'birthday'   => 'Anniversaire',
        'corporate'  => 'Entreprise',
        'event'      => 'Événement',
        'lifestyle'  => 'Lifestyle',
        'family'     => 'Famille',
        'fashion'    => 'Mode',
        'product'    => 'Produit',
        'other'      => 'Autre',
    ];

    public function __construct(private ?EventRepository $events = null)
    {
        $this->events = $events ?? new EventRepository();
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): int
    {
        return $this->events->insert($this->normalise($attributes) + [
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @param array<string, mixed> $attributes */
    public function update(int $id, array $attributes): bool
    {
        return $this->events->update($id, $this->normalise($attributes) + [
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function delete(int $id): bool
    {
        return $this->events->delete($id);
    }

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    private function normalise(array $attributes): array
    {
        $status = (string) ($attributes['status'] ?? EventStatus::DRAFT);
        $date = trim((string) ($attributes['event_date'] ?? ''));

        return [
            'client_id'   => (int) ($attributes['client_id'] ?? 0),
            'title'       => trim((string) ($attributes['title'] ?? '')),
            'description' => $this->nullIfBlank($attributes['description'] ?? null),
            'event_type'  => $this->nullIfBlank($attributes['event_type'] ?? null),
            'event_date'  => $date === '' ? null : date('Y-m-d', (int) strtotime($date)),
            'location'    => $this->nullIfBlank($attributes['location'] ?? null),
            'status'      => in_array($status, EventStatus::ALL, true) ? $status : EventStatus::DRAFT,
        ];
    }

    private function nullIfBlank(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
    }

    public static function typeLabel(?string $type): string
    {
        return self::TYPES[$type ?? ''] ?? ($type === null || $type === '' ? '—' : $type);
    }
}
