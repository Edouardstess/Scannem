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

    /** GET / */
    public function index(Request $request): Response
    {
        $featured = $this->portfolio->featured(9);

        // A brand new install has nothing marked as featured; showing the
        // most recent published work is better than an empty homepage.
        if ($featured === []) {
            $featured = $this->portfolio->publishedItems(null, 9);
        }

        return $this->view('public.home', [
            'title'      => null,
            'featured'   => $featured,
            'services'   => $this->services->published(),
            'categories' => $this->portfolio->categoriesWithItems(),
        ]);
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
