<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\ServiceRepository;

final class ServiceController extends Controller
{
    public function __construct(private ServiceRepository $services = new ServiceRepository())
    {
    }

    /** GET /services */
    public function index(Request $request): Response
    {
        return $this->view('public.services', [
            'title'    => 'Prestations',
            'services' => $this->services->published(),
        ]);
    }

    /** GET /services/{slug} */
    public function show(Request $request, array $parameters): Response
    {
        $service = $this->services->findBySlug((string) $parameters['slug']);

        if ($service === null || (string) $service['status'] !== 'published') {
            $this->abort(404, 'Prestation introuvable.');
        }

        return $this->view('public.service', [
            'title'   => (string) $service['title'],
            'service' => $service,
            'others'  => array_values(array_filter(
                $this->services->published(),
                static fn (array $row): bool => (int) $row['id'] !== (int) $service['id']
            )),
        ]);
    }
}
