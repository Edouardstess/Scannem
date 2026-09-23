<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\PortfolioRepository;
use App\Repositories\ServiceRepository;

final class HomeController extends Controller
{
    public function __construct(
        private PortfolioRepository $portfolio = new PortfolioRepository(),
        private ServiceRepository $services = new ServiceRepository()
    ) {
    }

    /** Images shown in the homepage selection: two full rows of three. */
    private const SELECTION_SIZE = 6;

    /** GET / */
    public function index(Request $request): Response
    {
        $featured = $this->portfolio->featured(self::SELECTION_SIZE);

        // The selection is a designed block, so a half-empty last row reads
        // as a broken grid. Anything the photographer has not marked as
        // featured tops it up, and if there is still not enough the grid is
        // trimmed to whole rows rather than left ragged.
        if (count($featured) < self::SELECTION_SIZE) {
            $featured = $this->topUpSelection($featured);
        }

        return $this->view('public.home', [
            'title'      => null,
            'featured'   => $featured,
            'services'   => $this->services->published(),
            'categories' => $this->portfolio->categoriesWithItems(),
        ]);
    }

    /**
     * Fill the selection with recent work, then trim to complete rows.
     *
     * @param array<int, array<string, mixed>> $featured
     * @return array<int, array<string, mixed>>
     */
    private function topUpSelection(array $featured): array
    {
        $seen = array_map(static fn (array $item): int => (int) $item['id'], $featured);

        foreach ($this->portfolio->publishedItems(null, self::SELECTION_SIZE * 2) as $item) {
            if (count($featured) >= self::SELECTION_SIZE) {
                break;
            }

            if (!in_array((int) $item['id'], $seen, true)) {
                $featured[] = $item;
            }
        }

        // 1 or 2 images still make a deliberate row; 4 or 5 leave a hole.
        $rows = (int) floor(count($featured) / 3);

        return $rows >= 1 ? array_slice($featured, 0, $rows * 3) : $featured;
    }

    /** GET /about */
    public function about(Request $request): Response
    {
        return $this->view('public.about', [
            'title'    => 'À propos',
            'services' => $this->services->published(),
        ]);
    }
}
