<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Request;

final class ServiceRequest extends FormRequest
{
    protected function rules(): array
    {
        return [
            'title'        => 'required|string|min:2|max:190',
            'summary'      => 'nullable|string|max:255',
            'description'  => 'nullable|string|max:5000',
            'price_from'   => 'nullable|numeric|min:0',
            'duration'     => 'nullable|string|max:80',
            'deliverables' => 'nullable|string|max:2000',
            'status'       => 'required|in:published,draft',
        ];
    }

    protected function labels(): array
    {
        return [
            'title'      => 'titre',
            'summary'    => 'résumé',
            'price_from' => 'prix',
            'duration'   => 'durée',
            'status'     => 'statut',
        ];
    }

    protected function transform(array $validated, Request $request): array
    {
        $price = trim((string) ($validated['price_from'] ?? ''));

        return [
            'title'        => trim((string) $validated['title']),
            'summary'      => self::nullIfBlank($validated['summary'] ?? null),
            'description'  => self::nullIfBlank($validated['description'] ?? null),
            // A French keyboard produces "1 200,50"; both separators are accepted.
            'price_from'   => $price === '' ? null : (float) str_replace([' ', ','], ['', '.'], $price),
            'currency'     => $request->string('currency', 'EUR'),
            'duration'     => self::nullIfBlank($validated['duration'] ?? null),
            'deliverables' => self::nullIfBlank($validated['deliverables'] ?? null),
            'status'       => (string) $validated['status'],
        ];
    }
}
