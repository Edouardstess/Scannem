<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\UploadException;
use App\Models\AuditAction;
use App\Models\VariantType;
use App\Repositories\GalleryRepository;
use App\Repositories\PhotoRepository;
use App\Services\AuditService;
use App\Services\DownloadService;
use App\Services\GalleryService;
use App\Services\PhotoUploadService;

/**
 * Photo management inside a gallery.
 *
 * Uploads arrive one file per request from the browser's drag-and-drop
 * uploader. One-at-a-time is deliberate: it gives per-file progress, lets a
 * single bad file be retried without resending the other 249, and keeps each
 * request well inside a shared host's max_execution_time.
 */
final class PhotoController extends Controller
{
    public function __construct(
        private PhotoRepository $photos = new PhotoRepository(),
        private GalleryRepository $galleries = new GalleryRepository(),
        private PhotoUploadService $uploads = new PhotoUploadService(),
        private GalleryService $galleryService = new GalleryService(),
        private DownloadService $downloads = new DownloadService(),
        private AuditService $audit = new AuditService()
    ) {
    }

    /** POST /admin/galleries/{id}/photos */
    public function store(Request $request, array $parameters): Response
    {
        $galleryId = (int) $parameters['id'];
        $gallery = $this->galleries->find($galleryId);

        if ($gallery === null) {
            return $this->json(['error' => 'Galerie introuvable.'], 404);
        }

        $files = PhotoUploadService::normaliseFilesArray($request->file('photo') ?? $request->file('photos') ?? []);

        if ($files === []) {
            return $this->json(['error' => 'Aucun fichier reçu.'], 422);
        }

        $stored = [];
        $failed = [];

        foreach ($files as $file) {
            try {
                $photo = $this->uploads->store($file, $gallery);

                $stored[] = [
                    'id'        => (int) $photo['id'],
                    'name'      => (string) $photo['original_filename'],
                    'size'      => format_bytes((int) $photo['file_size']),
                    'width'     => (int) ($photo['width'] ?? 0),
                    'height'    => (int) ($photo['height'] ?? 0),
                    'thumb_url' => url('admin/photos/' . (int) $photo['id'] . '/thumb'),
                ];

                $this->audit->record(
                    AuditAction::PHOTO_UPLOADED,
                    $request,
                    $galleryId,
                    (int) $photo['id'],
                    ['filename' => $photo['original_filename']]
                );
            } catch (UploadException $e) {
                $failed[] = ['name' => (string) ($file['name'] ?? '?'), 'error' => $e->getMessage()];
            } catch (\Throwable $e) {
                Logger::error('Unexpected upload failure', [
                    'gallery_id' => $galleryId,
                    'error'      => $e->getMessage(),
                ]);
                $failed[] = ['name' => (string) ($file['name'] ?? '?'), 'error' => 'Erreur interne pendant le traitement.'];
            }
        }

        // If a gallery has no cover yet, the first successful upload becomes it.
        if ($stored !== [] && ($gallery['cover_photo_id'] ?? null) === null) {
            $this->galleryService->setCoverPhoto($galleryId, (int) $stored[0]['id']);
        }

        $status = $stored === [] ? 422 : 201;

        return $this->json([
            'uploaded' => $stored,
            'failed'   => $failed,
            'total'    => $this->photos->countInGallery($galleryId),
        ], $status);
    }

    /**
     * GET /admin/photos/{id}/thumb
     *
     * The admin's own image route. It does not use media tokens: access here
     * is the admin session, checked by AuthMiddleware on the route.
     */
    public function thumbnail(Request $request, array $parameters): Response
    {
        $photo = $this->orFail($this->photos->find((int) $parameters['id']), 'Photo introuvable.');
        $variant = $this->photos->variant((int) $photo['id'], VariantType::THUMBNAIL);

        if ($variant === null) {
            $this->abort(404, 'Miniature indisponible.');
        }

        return $this->downloads->serve(
            (string) $variant['storage_path'],
            (string) $variant['mime_type'],
            (string) $photo['original_filename'],
            'inline',
            true,
            $request
        );
    }

    /** GET /admin/photos/{id}/preview */
    public function preview(Request $request, array $parameters): Response
    {
        $photo = $this->orFail($this->photos->find((int) $parameters['id']), 'Photo introuvable.');
        $variant = $this->photos->variant((int) $photo['id'], VariantType::PREVIEW);

        if ($variant === null) {
            $this->abort(404, 'Aperçu indisponible.');
        }

        return $this->downloads->serve(
            (string) $variant['storage_path'],
            (string) $variant['mime_type'],
            (string) $photo['original_filename'],
            'inline',
            true,
            $request
        );
    }

    /** GET /admin/photos/{id}/original — the photographer's own download. */
    public function original(Request $request, array $parameters): Response
    {
        $photo = $this->orFail($this->photos->find((int) $parameters['id']), 'Photo introuvable.');

        return $this->downloads->serve(
            (string) $photo['storage_path'],
            (string) $photo['mime_type'],
            (string) $photo['original_filename'],
            'attachment',
            false,
            $request
        );
    }

    /** DELETE /admin/photos/{id} */
    public function destroy(Request $request, array $parameters): Response
    {
        $photoId = (int) $parameters['id'];
        $photo = $this->photos->find($photoId);

        if ($photo === null) {
            return $request->isAjax()
                ? $this->json(['error' => 'Photo introuvable.'], 404)
                : $this->back($request, 'admin/galleries');
        }

        $galleryId = (int) $photo['gallery_id'];
        $this->uploads->deletePhoto($photoId);

        $this->audit->record(AuditAction::PHOTO_DELETED, $request, $galleryId, null, [
            'photo_id' => $photoId,
            'filename' => $photo['original_filename'],
        ]);

        if ($request->isAjax()) {
            return $this->json(['deleted' => true, 'total' => $this->photos->countInGallery($galleryId)]);
        }

        $this->flashSuccess('Photo supprimée.');

        return $this->redirect('admin/galleries/' . $galleryId);
    }

    /** POST /admin/photos/{id}/downloadable — toggle per-photo download. */
    public function toggleDownloadable(Request $request, array $parameters): Response
    {
        $photoId = (int) $parameters['id'];
        $photo = $this->orFail($this->photos->find($photoId), 'Photo introuvable.');
        $downloadable = !((bool) $photo['downloadable']);

        $this->photos->setDownloadable($photoId, (int) $photo['gallery_id'], $downloadable);

        if ($request->isAjax()) {
            return $this->json(['downloadable' => $downloadable]);
        }

        return $this->back($request, 'admin/galleries/' . (int) $photo['gallery_id']);
    }

    /** POST /admin/galleries/{id}/photos/reorder */
    public function reorder(Request $request, array $parameters): Response
    {
        $galleryId = (int) $parameters['id'];
        $this->orFail($this->galleries->find($galleryId), 'Galerie introuvable.');

        $ids = array_map('intval', $request->array('order'));

        if ($ids === []) {
            return $this->json(['error' => 'Ordre vide.'], 422);
        }

        // reorder() scopes each UPDATE by gallery id, so ids from another
        // gallery are silently ignored rather than moved.
        $this->photos->reorder($ids, $galleryId);

        return $this->json(['reordered' => count($ids)]);
    }

    /** POST /admin/galleries/{id}/cover */
    public function setCover(Request $request, array $parameters): Response
    {
        $galleryId = (int) $parameters['id'];
        $this->orFail($this->galleries->find($galleryId), 'Galerie introuvable.');

        if (!$this->galleryService->setCoverPhoto($galleryId, $request->int('photo_id'))) {
            return $this->json(['error' => 'Photo introuvable dans cette galerie.'], 422);
        }

        if ($request->isAjax()) {
            return $this->json(['cover_photo_id' => $request->int('photo_id')]);
        }

        $this->flashSuccess('Photo de couverture mise à jour.');

        return $this->back($request, 'admin/galleries/' . $galleryId);
    }
}
