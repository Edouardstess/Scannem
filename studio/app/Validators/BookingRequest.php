<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Request;

use App\Repositories\ServiceRepository;

final class BookingRequest extends FormRequest
{
    public function __construct(private ?ServiceRepository $services = null)
    {
        $this->services = $services ?? new ServiceRepository();
    }

    protected function rules(): array
    {
        return [
            'name'           => 'required|string|min:2|max:150',
            'email'          => 'required|email|max:190',
            'phone'          => 'nullable|phone',
            'service_id'     => 'nullable|integer',
            'preferred_date' => 'nullable|date',
            'location'       => 'nullable|string|max:190',
            'message'        => 'nullable|string|max:5000',
        ];
    }

    protected function labels(): array
    {
        return [
            'name'           => 'nom',
            'email'          => 'e-mail',
            'phone'          => 'téléphone',
            'service_id'     => 'prestation',
            'preferred_date' => 'date souhaitée',
            'location'       => 'lieu',
            'message'        => 'message',
        ];
    }

    protected function transform(array $validated, Request $request): array
    {
        // The prestation is resolved from the database, never trusted from
        // the form: a tampered id must not invent a service.
        $serviceId = isset($validated['service_id']) ? (int) $validated['service_id'] : 0;
        $service = $serviceId > 0 ? $this->services->find($serviceId) : null;

        return [
            'name'           => trim((string) $validated['name']),
            'email'          => (string) self::normaliseEmail($validated['email']),
            'phone'          => self::nullIfBlank($validated['phone'] ?? null),
            'service_id'     => $service === null ? null : (int) $service['id'],
            'service_label'  => $service === null ? null : (string) $service['title'],
            'preferred_date' => self::normaliseDate($validated['preferred_date'] ?? null),
            'location'       => self::nullIfBlank($validated['location'] ?? null),
            'message'        => self::nullIfBlank($validated['message'] ?? null),
        ];
    }
}
