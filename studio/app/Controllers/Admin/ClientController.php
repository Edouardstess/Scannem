<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditAction;
use App\Repositories\ClientRepository;
use App\Repositories\EventRepository;
use App\Services\AuditService;
use App\Services\ClientService;
use App\Validators\ClientRequest;

final class ClientController extends Controller
{
    private const PER_PAGE = 25;

    public function __construct(
        private ClientRepository $clients = new ClientRepository(),
        private EventRepository $events = new EventRepository(),
        private ClientService $service = new ClientService(),
        private AuditService $audit = new AuditService()
    ) {
    }

    /** GET /admin/clients */
    public function index(Request $request): Response
    {
        $page = $this->page($request);
        $search = $request->string('q');
        $result = $this->clients->paginate($page, self::PER_PAGE, $search);

        return $this->view('admin.clients.index', [
            'title'      => 'Clients',
            'clients'    => $result['rows'],
            'pagination' => $this->paginationMeta($result['total'], $page, self::PER_PAGE),
            'search'     => $search,
        ]);
    }

    /** GET /admin/clients/create */
    public function create(Request $request): Response
    {
        return $this->view('admin.clients.form', [
            'title'  => 'Nouveau client',
            'client' => null,
        ]);
    }

    /** POST /admin/clients */
    public function store(Request $request): Response
    {
        $form = (new ClientRequest())->validate($request);

        if ($form->fails()) {
            return $this->redirectWithErrors($request, $form->errors(), 'admin/clients/create');
        }

        $id = $this->service->create($form->data());
        $this->audit->record(AuditAction::CLIENT_CREATED, $request, null, null, ['client_id' => $id]);
        $this->flashSuccess('Client créé.');

        return $this->redirect('admin/clients/' . $id);
    }

    /** GET /admin/clients/{id} */
    public function show(Request $request, array $parameters): Response
    {
        $id = (int) $parameters['id'];
        $client = $this->orFail($this->clients->findWithCounters($id), 'Client introuvable.');

        return $this->view('admin.clients.show', [
            'title'  => $this->service->fullName($client),
            'client' => $client,
            'events' => $this->events->forClient($id),
        ]);
    }

    /** GET /admin/clients/{id}/edit */
    public function edit(Request $request, array $parameters): Response
    {
        $client = $this->orFail($this->clients->find((int) $parameters['id']), 'Client introuvable.');

        return $this->view('admin.clients.form', [
            'title'  => 'Modifier le client',
            'client' => $client,
        ]);
    }

    /** PUT /admin/clients/{id} */
    public function update(Request $request, array $parameters): Response
    {
        $id = (int) $parameters['id'];
        $this->orFail($this->clients->find($id), 'Client introuvable.');

        $form = (new ClientRequest($id))->validate($request);

        if ($form->fails()) {
            return $this->redirectWithErrors($request, $form->errors(), 'admin/clients/' . $id . '/edit');
        }

        $this->service->update($id, $form->data());
        $this->audit->record(AuditAction::CLIENT_UPDATED, $request, null, null, ['client_id' => $id]);
        $this->flashSuccess('Client mis à jour.');

        return $this->redirect('admin/clients/' . $id);
    }

    /** DELETE /admin/clients/{id} */
    public function destroy(Request $request, array $parameters): Response
    {
        $id = (int) $parameters['id'];
        $client = $this->orFail($this->clients->find($id), 'Client introuvable.');

        // Deleting a client cascades to events, galleries and photo rows.
        // The files on disk do not cascade, so each gallery is deleted
        // through GalleryService first — otherwise storage keeps every
        // original of a client who no longer exists.
        $galleryService = new \App\Services\GalleryService();

        foreach ($this->events->forClient($id) as $event) {
            foreach ((new \App\Repositories\GalleryRepository())->forEvent((int) $event['id']) as $gallery) {
                $galleryService->delete((int) $gallery['id']);
            }
        }

        $this->service->delete($id);

        $this->audit->record(AuditAction::CLIENT_DELETED, $request, null, null, [
            'client_id' => $id,
            'name'      => $this->service->fullName($client),
        ]);
        $this->flashSuccess('Client supprimé, avec ses événements, galeries et photos.');

        return $this->redirect('admin/clients');
    }

}
