<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditAction;
use App\Models\EventStatus;
use App\Repositories\ClientRepository;
use App\Repositories\EventRepository;
use App\Repositories\GalleryRepository;
use App\Services\AuditService;
use App\Services\EventService;
use App\Services\GalleryService;
use App\Validators\EventRequest;

final class EventController extends Controller
{
    private const PER_PAGE = 25;

    public function __construct(
        private EventRepository $events = new EventRepository(),
        private ClientRepository $clients = new ClientRepository(),
        private GalleryRepository $galleries = new GalleryRepository(),
        private EventService $service = new EventService(),
        private AuditService $audit = new AuditService()
    ) {
    }

    /** GET /admin/events */
    public function index(Request $request): Response
    {
        $page = $this->page($request);
        $search = $request->string('q');
        $status = $request->string('status');
        $clientId = $request->int('client_id') ?: null;

        $result = $this->events->paginate($page, self::PER_PAGE, $search, $status, $clientId);

        return $this->view('admin.events.index', [
            'title'      => 'Événements',
            'events'     => $result['rows'],
            'pagination' => $this->paginationMeta($result['total'], $page, self::PER_PAGE),
            'search'     => $search,
            'status'     => $status,
            'statuses'   => EventStatus::ALL,
        ]);
    }

    /** GET /admin/events/create */
    public function create(Request $request): Response
    {
        return $this->view('admin.events.form', [
            'title'    => 'Nouvel événement',
            'event'    => null,
            'clients'  => $this->clients->listForSelect(),
            'types'    => EventService::TYPES,
            'statuses' => EventStatus::ALL,
            'clientId' => $request->int('client_id'),
        ]);
    }

    /** POST /admin/events */
    public function store(Request $request): Response
    {
        $form = (new EventRequest())->validate($request);

        if ($form->fails()) {
            return $this->redirectWithErrors($request, $form->errors(), 'admin/events/create');
        }

        $id = $this->service->create($form->data());
        $this->audit->record(AuditAction::EVENT_CREATED, $request, null, null, ['event_id' => $id]);
        $this->flashSuccess('Événement créé.');

        return $this->redirect('admin/events/' . $id);
    }

    /** GET /admin/events/{id} */
    public function show(Request $request, array $parameters): Response
    {
        $id = (int) $parameters['id'];
        $event = $this->orFail($this->events->findWithClient($id), 'Événement introuvable.');

        return $this->view('admin.events.show', [
            'title'     => (string) $event['title'],
            'event'     => $event,
            'galleries' => $this->galleries->forEvent($id),
        ]);
    }

    /** GET /admin/events/{id}/edit */
    public function edit(Request $request, array $parameters): Response
    {
        $event = $this->orFail($this->events->find((int) $parameters['id']), 'Événement introuvable.');

        return $this->view('admin.events.form', [
            'title'    => "Modifier l'événement",
            'event'    => $event,
            'clients'  => $this->clients->listForSelect(),
            'types'    => EventService::TYPES,
            'statuses' => EventStatus::ALL,
            'clientId' => (int) $event['client_id'],
        ]);
    }

    /** PUT /admin/events/{id} */
    public function update(Request $request, array $parameters): Response
    {
        $id = (int) $parameters['id'];
        $this->orFail($this->events->find($id), 'Événement introuvable.');

        $form = (new EventRequest())->validate($request);

        if ($form->fails()) {
            return $this->redirectWithErrors($request, $form->errors(), 'admin/events/' . $id . '/edit');
        }

        $this->service->update($id, $form->data());
        $this->audit->record(AuditAction::EVENT_UPDATED, $request, null, null, ['event_id' => $id]);
        $this->flashSuccess('Événement mis à jour.');

        return $this->redirect('admin/events/' . $id);
    }

    /** DELETE /admin/events/{id} */
    public function destroy(Request $request, array $parameters): Response
    {
        $id = (int) $parameters['id'];
        $this->orFail($this->events->find($id), 'Événement introuvable.');

        // Files first, for the same reason as in ClientController::destroy.
        $galleryService = new GalleryService();

        foreach ($this->galleries->forEvent($id) as $gallery) {
            $galleryService->delete((int) $gallery['id']);
        }

        $this->service->delete($id);
        $this->audit->record(AuditAction::EVENT_DELETED, $request, null, null, ['event_id' => $id]);
        $this->flashSuccess('Événement supprimé, avec ses galeries et ses photos.');

        return $this->redirect('admin/events');
    }

}
