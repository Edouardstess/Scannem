<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\AuditAction;
use App\Models\GalleryStatus;
use App\Models\TokenType;
use App\Repositories\EventRepository;
use App\Repositories\GalleryRepository;
use App\Repositories\PhotoRepository;
use App\Repositories\PhotoSelectionRepository;
use App\Services\AuditService;
use App\Services\GalleryService;
use App\Services\MailService;
use App\Services\PhotoUploadService;
use App\Services\StatisticsService;
use App\Services\TokenService;

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
        private TokenService $tokens = new TokenService(),
        private StatisticsService $statistics = new StatisticsService(),
        private AuditService $audit = new AuditService(),
        private MailService $mail = new MailService()
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
        $validator = $this->validator($request);

        if ($validator->fails()) {
            return $this->redirectWithErrors($request, $validator->errors(), 'admin/galleries/create');
        }

        $attributes = $validator->validated();
        $attributes['password'] = $request->input('password');
        $attributes['watermark_enabled'] = $request->bool('watermark_enabled');
        $attributes['download_enabled'] = $request->bool('download_enabled');
        $attributes['selection_enabled'] = $request->bool('selection_enabled');
        $attributes['expires_at'] = $this->tokens->resolveExpiry(
            $request->string('expiry_option', 'never'),
            $request->string('expiry_date')
        );

        $created = $this->service->create($attributes);

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
            'links'       => $this->shareLinks($id),
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

        $validator = $this->validator($request);

        if ($validator->fails()) {
            return $this->redirectWithErrors($request, $validator->errors(), 'admin/galleries/' . $id . '/edit');
        }

        $attributes = $validator->validated();
        $attributes['password'] = $request->input('password');
        $attributes['remove_password'] = $request->bool('remove_password');
        $attributes['watermark_enabled'] = $request->bool('watermark_enabled');
        $attributes['download_enabled'] = $request->bool('download_enabled');
        $attributes['selection_enabled'] = $request->bool('selection_enabled');
        $attributes['expires_at'] = $this->tokens->resolveExpiry(
            $request->string('expiry_option', 'never'),
            $request->string('expiry_date')
        );

        $result = $this->service->update($id, $attributes);

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

    /** GET /admin/galleries/{id}/share */
    public function share(Request $request, array $parameters): Response
    {
        $id = (int) $parameters['id'];
        $gallery = $this->orFail($this->galleries->findDetailed($id), 'Galerie introuvable.');

        return $this->view('admin.galleries.share', [
            'title'   => 'Partager — ' . (string) $gallery['title'],
            'gallery' => $gallery,
            'links'   => $this->shareLinks($id),
        ]);
    }

    /** POST /admin/galleries/{id}/tokens/{type}/regenerate */
    public function regenerateToken(Request $request, array $parameters): Response
    {
        $id = (int) $parameters['id'];
        $type = strtoupper((string) $parameters['type']);

        $this->orFail($this->galleries->find($id), 'Galerie introuvable.');

        if (!in_array($type, TokenType::ALL, true)) {
            $this->abort(404, 'Type de lien inconnu.');
        }

        $this->service->regenerateToken($id, $type);
        $this->audit->record(AuditAction::TOKEN_REGENERATED, $request, $id, null, ['type' => $type]);

        $this->flashSuccess(sprintf(
            'Nouveau lien de %s généré. L\'ancien lien ne fonctionne plus.',
            TokenType::label($type)
        ));

        return $this->redirect('admin/galleries/' . $id . '/share');
    }

    /** POST /admin/galleries/{id}/tokens/{type}/revoke */
    public function revokeToken(Request $request, array $parameters): Response
    {
        $id = (int) $parameters['id'];
        $type = strtoupper((string) $parameters['type']);

        $this->orFail($this->galleries->find($id), 'Galerie introuvable.');

        if (!in_array($type, TokenType::ALL, true)) {
            $this->abort(404, 'Type de lien inconnu.');
        }

        $this->service->revokeToken($id, $type);
        $this->audit->record(AuditAction::TOKEN_REVOKED, $request, $id, null, ['type' => $type]);
        $this->flashSuccess(sprintf('Lien de %s désactivé.', TokenType::label($type)));

        return $this->redirect('admin/galleries/' . $id . '/share');
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

    /** POST /admin/galleries/{id}/notify */
    public function notifyClient(Request $request, array $parameters): Response
    {
        $id = (int) $parameters['id'];
        $gallery = $this->orFail($this->galleries->findDetailed($id), 'Galerie introuvable.');

        $recipient = trim((string) ($gallery['client_email'] ?? ''));

        if ($recipient === '') {
            $this->flashError("Ce client n'a pas d'adresse e-mail enregistrée.");

            return $this->back($request, 'admin/galleries/' . $id . '/share');
        }

        $links = $this->shareLinks($id);
        $kind = $request->string('kind', 'view');

        if ($kind === 'download') {
            if ($links['download']['url'] === null) {
                $this->flashError('Aucun lien de téléchargement actif à envoyer.');

                return $this->back($request, 'admin/galleries/' . $id . '/share');
            }

            $sent = $this->mail->notifyDownloadAvailable($recipient, $gallery, $links['download']['url']);
        } else {
            if ($links['view']['url'] === null) {
                $this->flashError('Aucun lien de consultation actif à envoyer.');

                return $this->back($request, 'admin/galleries/' . $id . '/share');
            }

            $sent = $this->mail->notifyGalleryReady($recipient, $gallery, $links['view']['url']);
        }

        $sent
            ? $this->flashSuccess('E-mail envoyé à ' . $recipient . '.')
            : $this->flashError("L'e-mail n'a pas pu être envoyé. Vérifiez la configuration SMTP.");

        return $this->back($request, 'admin/galleries/' . $id . '/share');
    }

    /**
     * Build the shareable URLs for a gallery.
     *
     * The raw token is recovered from its encrypted copy. When APP_KEY is
     * missing or has changed, the URL is null and the view offers to
     * regenerate — a link that cannot be displayed is never faked.
     *
     * @return array{view: array<string, mixed>, download: array<string, mixed>}
     */
    private function shareLinks(int $galleryId): array
    {
        $tokens = $this->service->activeTokens($galleryId);
        $links = [];

        foreach (['view' => TokenType::VIEW, 'download' => TokenType::DOWNLOAD] as $key => $type) {
            $token = $tokens[$key];
            $raw = $token === null ? null : $this->tokens->revealRawToken($token);

            $links[$key] = [
                'token'      => $token,
                'raw'        => $raw,
                'url'        => $raw === null ? null : $this->tokens->urlFor($type, $raw),
                'expires_at' => $token === null ? null : ($token['expires_at'] ?? null),
                'last_used_at' => $token === null ? null : ($token['last_used_at'] ?? null),
                'use_count'  => $token === null ? 0 : (int) $token['use_count'],
            ];
        }

        return $links;
    }

    private function validator(Request $request): Validator
    {
        $validator = Validator::make($request->all(), [
            'event_id'    => 'required|integer',
            'title'       => 'required|string|min:2|max:190',
            'description' => 'nullable|string|max:5000',
            'status'      => 'required|in:' . implode(',', GalleryStatus::ALL),
            'password'    => 'nullable|string|min:6|max:255',
        ], [
            'password.min' => 'Le mot de passe de galerie doit contenir au moins 6 caractères.',
        ], [
            'event_id' => 'événement',
            'title'    => 'titre',
            'status'   => 'statut',
            'password' => 'mot de passe',
        ]);

        if ($validator->passes() && $this->events->find($request->int('event_id')) === null) {
            $validator->addError('event_id', "Cet événement n'existe pas.");
        }

        if ($request->string('expiry_option') === 'custom') {
            $date = $request->string('expiry_date');

            if ($date === '' || strtotime($date) === false) {
                $validator->addError('expiry_date', 'Indiquez une date d\'expiration valide.');
            } elseif (strtotime($date) < time()) {
                $validator->addError('expiry_date', 'La date d\'expiration doit être dans le futur.');
            }
        }

        return $validator;
    }
}
