<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Request;
use App\Core\Validator;
use App\Models\EventStatus;
use App\Repositories\ClientRepository;

final class EventRequest extends FormRequest
{
    public function __construct(private ?ClientRepository $clients = null)
    {
        $this->clients = $clients ?? new ClientRepository();
    }

    protected function rules(): array
    {
        return [
            'client_id'   => 'required|integer',
            'title'       => 'required|string|min:2|max:190',
            'description' => 'nullable|string|max:5000',
            'event_type'  => 'nullable|string|max:50',
            'event_date'  => 'nullable|date',
            'location'    => 'nullable|string|max:190',
            'status'      => 'required|in:' . implode(',', EventStatus::ALL),
        ];
    }

    protected function labels(): array
    {
        return [
            'client_id'  => 'client',
            'title'      => 'titre',
            'event_date' => 'date',
            'location'   => 'lieu',
            'status'     => 'statut',
        ];
    }

    protected function after(Validator $validator, Request $request): void
    {
        // The foreign key is resolved against the database rather than
        // trusted: a tampered select must not create an orphan event.
        if ($this->clients->find($request->int('client_id')) === null) {
            $validator->addError('client_id', "Ce client n'existe pas.");
        }
    }

    protected function transform(array $validated, Request $request): array
    {
        $status = (string) $validated['status'];

        return [
            'client_id'   => (int) $validated['client_id'],
            'title'       => trim((string) $validated['title']),
            'description' => self::nullIfBlank($validated['description'] ?? null),
            'event_type'  => self::nullIfBlank($validated['event_type'] ?? null),
            'event_date'  => self::normaliseDate($validated['event_date'] ?? null),
            'location'    => self::nullIfBlank($validated['location'] ?? null),
            'status'      => in_array($status, EventStatus::ALL, true) ? $status : EventStatus::DRAFT,
        ];
    }
}
