<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Request;

final class PortfolioCategoryRequest extends FormRequest
{
    protected function rules(): array
    {
        return [
            'name'        => 'required|string|min:2|max:120',
            'description' => 'nullable|string|max:1000',
            'status'      => 'required|in:published,draft',
        ];
    }

    protected function labels(): array
    {
        return ['name' => 'nom', 'status' => 'statut'];
    }

    protected function transform(array $validated, Request $request): array
    {
        return [
            'name'        => trim((string) $validated['name']),
            'description' => self::nullIfBlank($validated['description'] ?? null),
            'status'      => (string) $validated['status'],
        ];
    }
}
