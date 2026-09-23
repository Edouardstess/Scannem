<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditAction;
use App\Models\GalleryStatus;
use App\Models\TokenType;
use App\Repositories\EventRepository;
use App\Repositories\GalleryRepository;
use App\Repositories\PhotoRepository;
use App\Repositories\PhotoSelectionRepository;
use App\Services\AuditService;
use App\Services\GalleryService;
use App\Services\PhotoUploadService;
use App\Services\StatisticsService;
use App\Validators\GalleryRequest;

final class GalleryController extends Controller
{
    private const PER_PAGE = 20;
    private const PHOTOS_PER_PAGE = 60;

    public function __construct(
        private GalleryRepository $galleries = new GalleryRepository(),
        private EventRepository $events = new EventRepository(),
        private PhotoRepository $photos = new PhotoRepository(),
        private PhotoSelectionRepository $selections = new PhotoSelectionRepository(),
        private GalleryService $service = new GalleryService(),
        private StatisticsService $statistics = new StatisticsService(),
        private AuditService $audit = new AuditService()
    ) {
    }

    /** GET /admin/galleries */
    public function index(Request $request): Response
    {
        $page = $this->page($request);
        $search = $request->string('q');
        $status = $request->string('status');
        $eventId = $request->int('event_id') ?: null;

        $result = $this->galleries->paginate($page, self::PER_PAGE, $search, $status, $eventId);

        return $this->view('admin.galleries.index', [
            'title'      => 'Galeries',
            'galleries'  => $result['rows'],
            'pagination' => $this->paginationMeta($result['total'], $page, self::PER_PAGE),
            'search'     => $search,
            'status'     => $status,
            'statuses'   => GalleryStatus::ALL,
        ]);
    }

    /** GET /admin/galleries/create */
    public function create(Request $request): Response
    {
        return $this->view('admin.galleries.form', [
            'title'    => 'Nouvelle galerie',
            'gallery'  => null,
            'events'   => $this->events->listForSelect(),
            'statuses' => GalleryStatus::ALL,
            'eventId'  => $request->int('event_id'),
        ]);
    }

    /** POST /admin/galleries */
    public function store(Request $request): Response
    {
        $form = (new GalleryRequest())->validate($request);

        if ($form->fails()) {
            return $this->redirectWithErrors($request, $form->errors(), 'admin/galleries/create');
        }

        $created = $this->service->create($form->data());

        $this->audit->record(AuditAction::GALLERY_CREATED, $request, $created['gallery_id']);
        $this->audit->record(AuditAction::TOKEN_GENERATED, $request, $created['gallery_id'], null, [
            'types' => [TokenType::VIEW, TokenType::DOWNLOAD],
        ]);

        $this->flashSuccess('Galerie créée. Les deux liens sont prêts dans l\'onglet Partage.');

        return $this->redirect('admin/galleries/' . $created['gallery_id']);
    }

    /** GET /admin/galleries/{id} */
    public function show(Request $request, array $parameters): Response
    {
        $id = (int) $parameters['id'];
        $gallery = $this->orFail($this->galleries->findDetailed($id), 'Galerie introuvable.');

        $page = $this->page($request);
        $total = $this->photos->countInGallery($id);
        $photos = $this->photos->forGalleryWithVariants($id, self::PHOTOS_PER_PAGE, ($page - 1) * self::PHOTOS_PER_PAGE);

        return $this->view('admin.galleries.show', [
            'title'       => (string) $gallery['title'],
            'gallery'     => $gallery,
            'photos'      => $photos,
            'pagination'  => $this->paginationMeta($total, $page, self::PHOTOS_PER_PAGE),
            'links'       => (new ShareController())->links($id),
            'stats'       => $this->statistics->forGallery($id),
            'selections'  => $this->selections->selectedPhotos($id),
            'auditTrail'  => (new \App\Repositories\AuditLogRepository())->forGallery($id, 15),
        ]);
    }

    /** GET /admin/galleries/{id}/edit */
    public function edit(Request $request, array $parameters): Response
    {
        $gallery = $this->orFail($this->galleries->find((int) $parameters['id']), 'Galerie introuvable.');

        return $this->view('admin.galleries.form', [
            'title'    => 'Modifier la galerie',
            'gallery'  => $gallery,
            'events'   => $this->events->listForSelect(),
            'statuses' => GalleryStatus::ALL,
            'eventId'  => (int) $gallery['event_id'],
        ]);
    }

    /** PUT /admin/galleries/{id} */
    public function update(Request $request, array $parameters): Response
    {
        $id = (int) $parameters['id'];
        $gallery = $this->orFail($this->galleries->find($id), 'Galerie introuvable.');

        $form = (new GalleryRequest())->validate($request);

        if ($form->fails()) {
            return $this->redirectWithErrors($request, $form->errors(), 'admin/galleries/' . $id . '/edit');
        }

        $result = $this->service->update($id, $form->data());

        $this->audit->record(AuditAction::GALLERY_UPDATED, $request, $id);

        // Existing previews carry the old watermark state, so they have to be
        // rebuilt from the untouched originals for the change to mean anything.
        if ($result['watermark_changed']) {
            $updated = $this->galleries->find($id);

            if ($updated !== null) {
                $outcome = (new PhotoUploadService())->regenerateGalleryVariants($updated);

                $this->flashInfo(sprintf(
                    'Filigrane appliqué : %d aperçu(s) régénéré(s)%s.',
                    $outcome['processed'],
                    $outcome['failed'] > 0 ? sprintf(', %d échec(s)', $outcome['failed']) : ''
                ));
            }
        }

        $this->flashSuccess('Galerie mise à jour.');

        return $this->redirect('admin/galleries/' . $id);
    }

    /** DELETE /admin/galleries/{id} */
    public function destroy(Request $request, array $parameters): Response
    {
        $id = (int) $parameters['id'];
        $gallery = $this->orFail($this->galleries->find($id), 'Galerie introuvable.');

        $this->service->delete($id);
        $this->audit->record(AuditAction::GALLERY_DELETED, $request, null, null, [
            'gallery_id' => $id,
            'title'      => $gallery['title'],
        ]);
        $this->flashSuccess('Galerie supprimée, avec ses photos et ses fichiers.');

        return $this->redirect('admin/galleries');
    }




    /** POST /admin/galleries/{id}/status */
    public function changeStatus(Request $request, array $parameters): Response
    {
        $id = (int) $parameters['id'];
        $this->orFail($this->galleries->find($id), 'Galerie introuvable.');

        $status = $request->string('status');

        match ($status) {
            GalleryStatus::ACTIVE   => $this->service->activate($id),
            GalleryStatus::DISABLED => $this->service->disable($id),
            GalleryStatus::ARCHIVED => $this->service->archive($id),
            GalleryStatus::DRAFT    => $this->galleries->setStatus($id, GalleryStatus::DRAFT),
            default                 => $this->abort(422, 'Statut inconnu.'),
        };

        $this->audit->record(
            $status === GalleryStatus::ARCHIVED ? AuditAction::GALLERY_ARCHIVED : AuditAction::GALLERY_UPDATED,
            $request,
            $id,
            null,
            ['status' => $status]
        );

        $this->flashSuccess('Statut mis à jour : ' . GalleryStatus::label($status) . '.');

        return $this->back($request, 'admin/galleries/' . $id);
    }


}
