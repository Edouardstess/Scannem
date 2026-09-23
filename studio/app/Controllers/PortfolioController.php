<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\PortfolioRepository;

final class PortfolioController extends Controller
{
    public function __construct(private PortfolioRepository $portfolio = new PortfolioRepository())
    {
    }

    /** GET /portfolio and GET /portfolio/{slug} */
    public function index(Request $request, array $parameters = []): Response
    {
        $slug = isset($parameters['slug']) ? (string) $parameters['slug'] : '';
        $category = null;

        if ($slug !== '') {
            $category = $this->portfolio->findCategoryBySlug($slug);

            if ($category === null || (string) $category['status'] !== 'published') {
                $this->abort(404, 'Catégorie introuvable.');
            }
        }

        return $this->view('public.portfolio', [
            'title'      => $category === null ? 'Portfolio' : (string) $category['name'],
            'items'      => $this->portfolio->publishedItems($slug === '' ? null : $slug),
            'categories' => $this->portfolio->categoriesWithItems(),
            'active'     => $slug,
            'category'   => $category,
        ]);
    }
}
