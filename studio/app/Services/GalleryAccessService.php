<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\DTO\GalleryAccess;
use App\Models\TokenType;
use App\Repositories\GalleryRepository;

/**
 * The single gate every client-facing gallery request passes through.
 *
 * Nothing about a gallery is served without a GalleryAccess granted by this
 * class: the gallery page, the download page, each individual image, each
 * ZIP. Concentrating the decision here is what makes the VIEW/DOWNLOAD
 * separation enforceable — there is one place to read, and one place to get
 * right.
 */
final class GalleryAccessService
{
    private const SESSION_UNLOCK_PREFIX = '_gallery_unlocked_';

    public function __construct(
        private ?TokenService $tokens = null,
        private ?GalleryRepository $galleries = null
    ) {
        $this->tokens = $tokens ?? new TokenService();
        $this->galleries = $galleries ?? new GalleryRepository();
    }

    /**
     * Resolve a raw token from a URL into an access decision.
     *
     * @param string|null $expectedType Require VIEW or DOWNLOAD.
     * @param bool $requireUnlock Enforce the gallery password (false when the
     *        caller is about to render the password form itself).
     */
    public function resolve(string $rawToken, ?string $expectedType = null, bool $requireUnlock = true): GalleryAccess
    {
        $verification = $this->tokens->verify($rawToken, $expectedType);
        $token = $verification['token'];

        $status = match ($verification['status']) {
            TokenService::RESULT_VALID      => null,
            TokenService::RESULT_REVOKED    => GalleryAccess::REVOKED,
            TokenService::RESULT_EXPIRED    => GalleryAccess::EXPIRED,
            TokenService::RESULT_WRONG_TYPE => GalleryAccess::WRONG_TYPE,
            default                         => GalleryAccess::NOT_FOUND,
        };

        if ($status !== null) {
            return GalleryAccess::denied($status, null, $token);
        }

        /** @var array<string, mixed> $token */
        $gallery = $this->galleries->findDetailed((int) $token['gallery_id']);

        if ($gallery === null) {
            return GalleryAccess::denied(GalleryAccess::NOT_FOUND, null, $token);
        }

        // Gallery status and gallery expiry are checked in addition to the
        // token's own: disabling a gallery must close every link at once,
        // without having to revoke each token individually.
        if (GalleryRepository::hasExpired($gallery)) {
            return GalleryAccess::denied(GalleryAccess::GALLERY_EXPIRED, $gallery, $token);
        }

        if (!GalleryRepository::isReachable($gallery)) {
            return GalleryAccess::denied(GalleryAccess::GALLERY_CLOSED, $gallery, $token);
        }

        if ($requireUnlock && $this->isPasswordProtected($gallery) && !$this->isUnlocked((int) $gallery['id'])) {
            return GalleryAccess::denied(GalleryAccess::PASSWORD_REQUIRED, $gallery, $token);
        }

        return GalleryAccess::granted($gallery, $token);
    }

    /** Resolve a link that must permit downloading. */
    public function resolveForDownload(string $rawToken): GalleryAccess
    {
        $access = $this->resolve($rawToken, TokenType::DOWNLOAD);

        if (!$access->isGranted()) {
            return $access;
        }

        if (!$access->allowsDownload()) {
            return GalleryAccess::denied(GalleryAccess::DOWNLOAD_DISABLED, $access->gallery, $access->token);
        }

        return $access;
    }

    /** @param array<string, mixed> $gallery */
    public function isPasswordProtected(array $gallery): bool
    {
        $hash = $gallery['password_hash'] ?? null;

        return is_string($hash) && $hash !== '';
    }

    /**
     * Check a gallery password and open a temporary session on success.
     *
     * @param array<string, mixed> $gallery
     */
    public function unlock(array $gallery, string $password): bool
    {
        $hash = (string) ($gallery['password_hash'] ?? '');

        if ($hash === '' || !password_verify($password, $hash)) {
            return false;
        }

        // A new session id at the moment access is granted, for the same
        // reason the admin login regenerates: the pre-unlock id may be known.
        Session::regenerate();
        Session::put(self::SESSION_UNLOCK_PREFIX . (int) $gallery['id'], time());

        return true;
    }

    public function isUnlocked(int $galleryId): bool
    {
        return Session::get(self::SESSION_UNLOCK_PREFIX . $galleryId) !== null;
    }

    public function lock(int $galleryId): void
    {
        Session::forget(self::SESSION_UNLOCK_PREFIX . $galleryId);
    }
}
