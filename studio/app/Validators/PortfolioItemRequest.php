<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Request;

use App\Repositories\PortfolioRepository;

final class PortfolioItemRequest extends FormRequest
{
    public function __construct(private ?PortfolioRepository $portfolio = null)
    {
        $this->portfolio = $portfolio ?? new PortfolioRepository();
    }

    protected function rules(): array
    {
        return [
            'title'       => 'required|string|min:2|max:190',
            'description' => 'nullable|string|max:2000',
            'category_id' => 'nullable|integer',
            'status'      => 'required|in:published,draft',
        ];
    }

    protected function labels(): array
    {
        return [
            'title'       => 'titre',
            'status'      => 'statut',
            'category_id' => 'catégorie',
        ];
    }

    protected function transform(array $validated, Request $request): array
    {
        $categoryId = (int) ($validated['category_id'] ?? 0);

        return [
            'title'       => trim((string) $validated['title']),
            'description' => self::nullIfBlank($validated['description'] ?? null),
            'category_id' => $categoryId > 0 && $this->portfolio->findCategory($categoryId) !== null
                ? $categoryId
                : null,
            'featured'    => $request->bool('featured') ? 1 : 0,
            'status'      => (string) $validated['status'],
        ];
    }
}
