<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\DTO\GalleryAccess;
use App\Models\AuditAction;
use App\Models\TokenType;
use App\Repositories\GalleryTokenRepository;
use App\Repositories\GalleryViewRepository;
use App\Repositories\PhotoRepository;
use App\Repositories\PhotoSelectionRepository;
use App\Services\AuditService;
use App\Services\GalleryAccessService;
use App\Services\RateLimiter;

/**
 * The client-facing gallery: /gallery/{token}.
 *
 * View-only by construction. The page is built from preview URLs; no original
 * is reachable from it, and no download route accepts a VIEW token.
 */
final class GalleryController extends Controller
{
    private const PHOTOS_PER_PAGE = 60;

    public function __construct(
        private GalleryAccessService $access = new GalleryAccessService(),
        private PhotoRepository $photos = new PhotoRepository(),
        private GalleryViewRepository $views = new GalleryViewRepository(),
        private GalleryTokenRepository $tokens = new GalleryTokenRepository(),
        private PhotoSelectionRepository $selections = new PhotoSelectionRepository(),
        private AuditService $audit = new AuditService(),
        private RateLimiter $limiter = new RateLimiter()
    ) {
    }

    /** GET /gallery/{token} */
    public function show(Request $request, array $parameters): Response
    {
        $rawToken = (string) $parameters['token'];
        $access = $this->access->resolve($rawToken, TokenType::VIEW);

        if ($access->needsPassword()) {
            return $this->passwordForm($rawToken, $access);
        }

        if (!$access->isGranted()) {
            return $this->unavailable($request, $access);
        }

        return $this->renderGallery($request, $rawToken, $access);
    }

    /** POST /gallery/{token}/unlock */
    public function unlock(Request $request, array $parameters): Response
    {
        $rawToken = (string) $parameters['token'];

        // Resolve without enforcing the password, so the gallery row is
        // available to verify against.
        $access = $this->access->resolve($rawToken, TokenType::VIEW, false);

        if (!$access->isGranted() || $access->gallery === null) {
            return $this->unavailable($request, $access);
        }

        $limiterKey = 'gallery-unlock|' . $access->galleryId() . '|' . $request->ip();
        $maxAttempts = (int) config('security.gallery_max_attempts', 10);

        if ($this->limiter->tooManyAttempts($limiterKey, $maxAttempts)) {
            return $this->passwordForm(
                $rawToken,
                $access,
                'Trop de tentatives. ' . $this->limiter->retryMessage($limiterKey)
            );
        }

        $password = (string) $request->input('password', '');

        if (!$this->access->unlock($access->gallery, $password)) {
            $this->limiter->hit($limiterKey, (int) config('security.login_decay_seconds', 900));

            $this->audit->record(
                AuditAction::GALLERY_UNLOCK_FAILED,
                $request,
                $access->galleryId()
            );

            return $this->passwordForm($rawToken, $access, 'Mot de passe incorrect.');
        }

        $this->limiter->clear($limiterKey);
        $this->audit->record(AuditAction::GALLERY_UNLOCKED, $request, $access->galleryId());

        return $this->redirect('gallery/' . $rawToken);
    }

    /**
     * GET /gallery/{token}/photos
     *
     * Paginated JSON for infinite scroll. A 250-photo gallery must not put
     * 250 image requests in flight on first paint.
     */
    public function photos(Request $request, array $parameters): Response
    {
        $rawToken = (string) $parameters['token'];
        $access = $this->access->resolve($rawToken, TokenType::VIEW);

        if (!$access->isGranted()) {
            return $this->json(['error' => $access->message()], $access->httpStatus());
        }

        return $this->json($this->photoPage($request, $access));
    }

    /** POST /gallery/{token}/select — toggle a client favourite. */
    public function toggleSelection(Request $request, array $parameters): Response
    {
        $access = $this->access->resolve((string) $parameters['token'], TokenType::VIEW);

        if (!$access->isGranted()) {
            return $this->json(['error' => $access->message()], $access->httpStatus());
        }

        if (!$access->allowsSelection()) {
            return $this->json(['error' => "La sélection n'est pas activée pour cette galerie."], 403);
        }

        $photoId = $request->int('photo_id');
        $photo = $this->photos->findInGallery($photoId, (int) $access->galleryId());

        if ($photo === null) {
            return $this->json(['error' => 'Photo introuvable.'], 404);
        }

        $selected = $this->selections->toggle((int) $access->galleryId(), $photoId, $access->tokenId());

        $this->audit->record(
            AuditAction::PHOTO_SELECTED,
            $request,
            $access->galleryId(),
            $photoId,
            ['selected' => $selected]
        );

        return $this->json([
            'selected' => $selected,
            'total'    => count($this->selections->photoIdsFor((int) $access->galleryId(), $access->tokenId())),
        ]);
    }

    /**
     * Render the gallery page.
     *
     * Shared by the view gallery and, with downloads enabled, the download
     * gallery, so both present the same grid and lightbox.
     */
    private function renderGallery(Request $request, string $rawToken, GalleryAccess $access): Response
    {
        $this->recordView($request, $access);

        $page = $this->photoPage($request, $access);
        $gallery = $access->gallery;

        return $this->view('client.gallery', [
            'gallery'        => $gallery,
            'access'         => $access,
            'rawToken'       => $rawToken,
            'photos'         => $page['photos'],
            'pagination'     => $page['pagination'],
            'canDownload'    => $access->allowsDownload(),
            'canSelect'      => $access->allowsSelection(),
            'selectedIds'    => $access->allowsSelection()
                ? $this->selections->photoIdsFor((int) $access->galleryId(), $access->tokenId())
                : [],
            'downloadUrl'    => null,
            'photosEndpoint' => url('gallery/' . $rawToken . '/photos'),
            'selectEndpoint' => url('gallery/' . $rawToken . '/select'),
        ]);
    }

    /**
     * One page of photos, decorated with signed media URLs.
     *
     * @return array{photos: array<int, array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function photoPage(Request $request, GalleryAccess $access): array
    {
        $page = $this->page($request);
        $perPage = self::PHOTOS_PER_PAGE;
        $galleryId = (int) $access->galleryId();

        $total = $this->photos->countInGallery($galleryId);
        $rows = $this->photos->forGalleryWithVariants($galleryId, $perPage, ($page - 1) * $perPage);
        $rows = MediaController::decorateWithMediaUrls($rows, $access);

        return [
            'photos'     => array_map([$this, 'presentPhoto'], $rows),
            'pagination' => $this->paginationMeta($total, $page, $perPage) + [
                'has_more' => $page * $perPage < $total,
            ],
        ];
    }

    /**
     * Shape a photo row for the client.
     *
     * Only what the page needs is exposed. Storage paths, checksums, file
     * sizes of originals and EXIF beyond the capture date stay server-side;
     * GPS coordinates are never read into the row in the first place.
     *
     * @param array<string, mixed> $photo
     * @return array<string, mixed>
     */
    private function presentPhoto(array $photo): array
    {
        $preview = $photo['variants'][\App\Models\VariantType::PREVIEW] ?? null;

        return [
            'id'           => (int) $photo['id'],
            'name'         => (string) $photo['original_filename'],
            'thumb_url'    => $photo['thumb_url'],
            'preview_url'  => $photo['preview_url'],
            'download_url' => $photo['download_url'],
            'width'        => $preview === null ? (int) ($photo['width'] ?? 0) : (int) $preview['width'],
            'height'       => $preview === null ? (int) ($photo['height'] ?? 0) : (int) $preview['height'],
            'orientation'  => (string) ($photo['orientation'] ?? 'landscape'),
            'downloadable' => (bool) $photo['downloadable'],
        ];
    }

    private function recordView(Request $request, GalleryAccess $access): void
    {
        $galleryId = (int) $access->galleryId();

        $this->tokens->touch((int) $access->tokenId());

        // A refresh should not inflate the count the photographer reads.
        if ($this->views->seenRecently($galleryId, $request->ip())) {
            return;
        }

        $this->views->insert([
            'gallery_id' => $galleryId,
            'token_id'   => $access->tokenId(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->audit->record(AuditAction::GALLERY_VIEWED, $request, $galleryId);
    }

    private function passwordForm(string $rawToken, GalleryAccess $access, ?string $error = null): Response
    {
        return $this->view('client.password', [
            'gallery'    => $access->gallery,
            'rawToken'   => $rawToken,
            'error'      => $error,
            'actionPath' => 'gallery/' . $rawToken . '/unlock',
        ], $error === null ? 200 : 422);
    }

    private function unavailable(Request $request, GalleryAccess $access): Response
    {
        $this->audit->record(
            AuditAction::TOKEN_REJECTED,
            $request,
            $access->galleryId(),
            null,
            ['reason' => $access->status]
        );

        return $this->view('client.unavailable', [
            'message' => $access->message(),
        ], $access->httpStatus());
    }
}
