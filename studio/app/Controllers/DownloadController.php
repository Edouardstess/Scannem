<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\DTO\GalleryAccess;
use App\Exceptions\StorageException;
use App\Models\AuditAction;
use App\Models\TokenType;
use App\Repositories\GalleryTokenRepository;
use App\Repositories\PhotoRepository;
use App\Services\AuditService;
use App\Services\DownloadService;
use App\Services\GalleryAccessService;
use App\Services\RateLimiter;
use App\Services\ZipService;

/**
 * The client-facing download area: /download/{token}.
 *
 * Reachable only with a DOWNLOAD token on a gallery whose downloads are
 * enabled. Every photo is re-checked against the gallery at the moment the
 * archive is built, so a crafted list of photo ids cannot pull in anything
 * from elsewhere.
 */
final class DownloadController extends Controller
{
    private const ARCHIVE_SESSION_KEY = '_archives';
    private const ARCHIVE_TTL = 1800;

    public function __construct(
        private GalleryAccessService $access = new GalleryAccessService(),
        private PhotoRepository $photos = new PhotoRepository(),
        private ZipService $zip = new ZipService(),
        private DownloadService $downloads = new DownloadService(),
        private GalleryTokenRepository $tokens = new GalleryTokenRepository(),
        private AuditService $audit = new AuditService(),
        private RateLimiter $limiter = new RateLimiter()
    ) {
    }

    /** GET /download/{token} */
    public function show(Request $request, array $parameters): Response
    {
        $rawToken = (string) $parameters['token'];
        $access = $this->access->resolve($rawToken, TokenType::DOWNLOAD);

        if ($access->needsPassword()) {
            return $this->view('client.password', [
                'gallery'    => $access->gallery,
                'rawToken'   => $rawToken,
                'error'      => null,
                'actionPath' => 'download/' . $rawToken . '/unlock',
            ]);
        }

        if (!$access->isGranted()) {
            return $this->unavailable($request, $access);
        }

        if (!$access->allowsDownload()) {
            return $this->unavailable(
                $request,
                GalleryAccess::denied(GalleryAccess::DOWNLOAD_DISABLED, $access->gallery, $access->token)
            );
        }

        $this->tokens->touch((int) $access->tokenId());

        $galleryController = new GalleryController();
        $page = $galleryController->photoPage($request, $access);

        $downloadable = $this->photos->downloadableInGallery((int) $access->galleryId());
        $totalBytes = array_sum(array_map(static fn (array $p): int => (int) $p['file_size'], $downloadable));

        return $this->view('client.download', [
            'gallery'         => $access->gallery,
            'access'          => $access,
            'rawToken'        => $rawToken,
            'photos'          => $page['photos'],
            'pagination'      => $page['pagination'],
            'downloadableCount' => count($downloadable),
            'totalBytes'      => $totalBytes,
            'zipAvailable'    => $this->zip->isAvailable(),
            'photosEndpoint'  => url('download/' . $rawToken . '/photos'),
            'archiveEndpoint' => url('download/' . $rawToken . '/archive'),
        ]);
    }

    /** POST /download/{token}/unlock */
    public function unlock(Request $request, array $parameters): Response
    {
        $rawToken = (string) $parameters['token'];
        $access = $this->access->resolve($rawToken, TokenType::DOWNLOAD, false);

        if (!$access->isGranted() || $access->gallery === null) {
            return $this->unavailable($request, $access);
        }

        $limiterKey = 'gallery-unlock|' . $access->galleryId() . '|' . $request->ip();

        if ($this->limiter->tooManyAttempts($limiterKey, (int) config('security.gallery_max_attempts', 10))) {
            return $this->view('client.password', [
                'gallery'    => $access->gallery,
                'rawToken'   => $rawToken,
                'error'      => 'Trop de tentatives. ' . $this->limiter->retryMessage($limiterKey),
                'actionPath' => 'download/' . $rawToken . '/unlock',
            ], 429);
        }

        if (!$this->access->unlock($access->gallery, (string) $request->input('password', ''))) {
            $this->limiter->hit($limiterKey, (int) config('security.login_decay_seconds', 900));
            $this->audit->record(AuditAction::GALLERY_UNLOCK_FAILED, $request, $access->galleryId());

            return $this->view('client.password', [
                'gallery'    => $access->gallery,
                'rawToken'   => $rawToken,
                'error'      => 'Mot de passe incorrect.',
                'actionPath' => 'download/' . $rawToken . '/unlock',
            ], 422);
        }

        $this->limiter->clear($limiterKey);
        $this->audit->record(AuditAction::GALLERY_UNLOCKED, $request, $access->galleryId());

        return $this->redirect('download/' . $rawToken);
    }

    /** GET /download/{token}/photos — paginated JSON. */
    public function photos(Request $request, array $parameters): Response
    {
        $access = $this->access->resolveForDownload((string) $parameters['token']);

        if (!$access->isGranted()) {
            return $this->json(['error' => $access->message()], $access->httpStatus());
        }

        return $this->json((new GalleryController())->photoPage($request, $access));
    }

    /**
     * POST /download/{token}/archive
     *
     * Builds a ZIP and returns a handle to it. The archive is not streamed
     * from this request: building a multi-gigabyte file and streaming it in
     * one response gives the browser no way to show progress and no way to
     * retry. The handle lives in the visitor's own session, so it cannot be
     * replayed by anyone else.
     */
    public function createArchive(Request $request, array $parameters): Response
    {
        $rawToken = (string) $parameters['token'];
        $access = $this->access->resolveForDownload($rawToken);

        if (!$access->isGranted()) {
            return $this->json(['error' => $access->message()], $access->httpStatus());
        }

        if (!$this->zip->isAvailable()) {
            return $this->json(['error' => "L'archive ZIP n'est pas disponible sur ce serveur."], 501);
        }

        $galleryId = (int) $access->galleryId();
        $requestedIds = $request->array('photo_ids');

        // An empty selection means "everything downloadable in this gallery".
        $photos = $requestedIds === []
            ? $this->photos->downloadableInGallery($galleryId)
            : $this->photos->findManyInGallery(
                array_map('intval', $requestedIds),
                $galleryId,
                true
            );

        if ($photos === []) {
            return $this->json(['error' => 'Aucune photo téléchargeable dans cette sélection.'], 422);
        }

        // Archive building is expensive; cap how often one visitor can ask.
        $limiterKey = 'zip|' . $galleryId . '|' . $request->ip();

        if ($this->limiter->tooManyAttempts($limiterKey, 20)) {
            return $this->json([
                'error' => 'Trop de demandes d\'archive. ' . $this->limiter->retryMessage($limiterKey),
            ], 429);
        }

        $this->limiter->hit($limiterKey, 900);

        try {
            $archive = $this->zip->createArchive($photos, (string) $access->gallery['title']);
        } catch (StorageException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Logger::error('ZIP creation failed', ['gallery_id' => $galleryId, 'error' => $e->getMessage()]);

            return $this->json(['error' => "L'archive n'a pas pu être créée."], 500);
        }

        $handle = $this->rememberArchive($archive, $galleryId, $access->tokenId());

        $this->audit->record(AuditAction::ZIP_GENERATED, $request, $galleryId, null, [
            'photos' => $archive['count'],
            'bytes'  => $archive['bytes'],
        ]);

        return $this->json([
            'handle'   => $handle,
            'url'      => url('download/' . $rawToken . '/archive/' . $handle),
            'filename' => $archive['filename'],
            'count'    => $archive['count'],
            'bytes'    => $archive['bytes'],
            'size'     => format_bytes($archive['bytes']),
        ]);
    }

    /** GET /download/{token}/archive/{handle} — stream a prepared archive. */
    public function fetchArchive(Request $request, array $parameters): Response
    {
        $rawToken = (string) $parameters['token'];
        $handle = (string) $parameters['handle'];
        $access = $this->access->resolveForDownload($rawToken);

        if (!$access->isGranted()) {
            return $this->unavailable($request, $access);
        }

        $archive = $this->recallArchive($handle);

        if ($archive === null || $archive['gallery_id'] !== (int) $access->galleryId()) {
            $this->abort(404, "Cette archive n'est plus disponible. Relancez le téléchargement.");
        }

        if (!$this->zipStorageHas($archive['path'])) {
            $this->forgetArchive($handle);
            $this->abort(410, "Cette archive a expiré. Relancez le téléchargement.");
        }

        $this->downloads->logArchiveDownload(
            (int) $access->galleryId(),
            $access->tokenId(),
            $archive['count'],
            $archive['bytes'],
            $request
        );

        $this->tokens->touch((int) $access->tokenId());

        return $this->downloads->serve(
            $archive['path'],
            'application/zip',
            $archive['filename'],
            'attachment',
            false,
            $request
        );
    }

    /**
     * @param array{relative: string, filename: string, bytes: int, count: int} $archive
     */
    private function rememberArchive(array $archive, int $galleryId, ?int $tokenId): string
    {
        $handle = bin2hex(random_bytes(16));
        $archives = Session::get(self::ARCHIVE_SESSION_KEY, []);
        $archives = is_array($archives) ? $archives : [];

        // Drop stale entries so the session does not grow without bound.
        $now = time();
        $archives = array_filter(
            $archives,
            static fn (array $entry): bool => ($entry['expires'] ?? 0) > $now
        );

        $archives[$handle] = [
            'path'       => $archive['relative'],
            'filename'   => $archive['filename'],
            'bytes'      => $archive['bytes'],
            'count'      => $archive['count'],
            'gallery_id' => $galleryId,
            'token_id'   => $tokenId,
            'expires'    => $now + self::ARCHIVE_TTL,
        ];

        Session::put(self::ARCHIVE_SESSION_KEY, $archives);

        return $handle;
    }

    /** @return array{path: string, filename: string, bytes: int, count: int, gallery_id: int}|null */
    private function recallArchive(string $handle): ?array
    {
        $archives = Session::get(self::ARCHIVE_SESSION_KEY, []);

        if (!is_array($archives) || !isset($archives[$handle]) || !is_array($archives[$handle])) {
            return null;
        }

        $entry = $archives[$handle];

        if (($entry['expires'] ?? 0) < time()) {
            $this->forgetArchive($handle);

            return null;
        }

        return [
            'path'       => (string) $entry['path'],
            'filename'   => (string) $entry['filename'],
            'bytes'      => (int) $entry['bytes'],
            'count'      => (int) $entry['count'],
            'gallery_id' => (int) $entry['gallery_id'],
        ];
    }

    private function forgetArchive(string $handle): void
    {
        $archives = Session::get(self::ARCHIVE_SESSION_KEY, []);

        if (is_array($archives)) {
            unset($archives[$handle]);
            Session::put(self::ARCHIVE_SESSION_KEY, $archives);
        }
    }

    private function zipStorageHas(string $relative): bool
    {
        return (new \App\Services\StorageService())->exists($relative);
    }

    private function unavailable(Request $request, GalleryAccess $access): Response
    {
        $this->audit->record(
            AuditAction::TOKEN_REJECTED,
            $request,
            $access->galleryId(),
            null,
            ['reason' => $access->status, 'route' => 'download']
        );

        return $this->view('client.unavailable', ['message' => $access->message()], $access->httpStatus());
    }
}
