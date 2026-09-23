<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Request;

final class ContactRequest extends FormRequest
{
    protected function rules(): array
    {
        return [
            'name'           => 'required|string|min:2|max:150',
            'email'          => 'required|email|max:190',
            'phone'          => 'nullable|phone',
            'subject'        => 'nullable|string|max:190',
            'preferred_date' => 'nullable|date',
            'message'        => 'required|string|min:10|max:5000',
        ];
    }

    protected function labels(): array
    {
        return [
            'name'           => 'nom',
            'email'          => 'e-mail',
            'phone'          => 'téléphone',
            'subject'        => 'type de séance',
            'preferred_date' => 'date souhaitée',
            'message'        => 'message',
        ];
    }

    protected function transform(array $validated, Request $request): array
    {
        return [
            'name'           => trim((string) $validated['name']),
            'email'          => (string) self::normaliseEmail($validated['email']),
            'phone'          => self::nullIfBlank($validated['phone'] ?? null),
            'subject'        => self::nullIfBlank($validated['subject'] ?? null),
            'preferred_date' => self::normaliseDate($validated['preferred_date'] ?? null),
            'message'        => trim((string) $validated['message']),
        ];
    }
}
