<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\DTO\GalleryAccess;
use App\Models\TokenType;
use App\Models\VariantType;
use App\Repositories\GalleryRepository;
use App\Repositories\GalleryTokenRepository;
use App\Repositories\PhotoRepository;
use App\Services\DownloadService;
use App\Services\GalleryAccessService;
use App\Services\MediaTokenService;

/**
 * Serves image bytes.
 *
 * This is the only route that can reach a file in private storage, and it is
 * the place the VIEW/DOWNLOAD distinction is actually enforced. Every request
 * is re-authorised from scratch:
 *
 *   1. The media token's HMAC must verify (it was minted by this app).
 *   2. The gallery token it names must still exist, be unrevoked, unexpired.
 *   3. The gallery must still be active and unexpired.
 *   4. A password-protected gallery must be unlocked in this session.
 *   5. The photo must belong to that gallery.
 *   6. For an original: the gallery token must be DOWNLOAD, the gallery must
 *      have downloads enabled, and the photo must be individually downloadable.
 *
 * A media URL minted for a VIEW token therefore cannot be edited into an
 * original download, and a revoked link stops working immediately even if the
 * client still has a page full of signed URLs open.
 */
final class MediaController extends Controller
{
    public function __construct(
        private MediaTokenService $mediaTokens = new MediaTokenService(),
        private GalleryTokenRepository $tokens = new GalleryTokenRepository(),
        private GalleryRepository $galleries = new GalleryRepository(),
        private PhotoRepository $photos = new PhotoRepository(),
        private GalleryAccessService $access = new GalleryAccessService(),
        private DownloadService $downloads = new DownloadService()
    ) {
    }

    /** GET /media/thumb/{token} */
    public function thumbnail(Request $request, array $parameters): Response
    {
        return $this->deliver($request, (string) $parameters['token'], MediaTokenService::VARIANT_THUMBNAIL);
    }

    /** GET /media/preview/{token} */
    public function preview(Request $request, array $parameters): Response
    {
        return $this->deliver($request, (string) $parameters['token'], MediaTokenService::VARIANT_PREVIEW);
    }

    /** GET /media/download/{token} */
    public function download(Request $request, array $parameters): Response
    {
        return $this->deliver($request, (string) $parameters['token'], MediaTokenService::VARIANT_ORIGINAL);
    }

    private function deliver(Request $request, string $mediaToken, string $expectedVariant): Response
    {
        $payload = $this->mediaTokens->parse($mediaToken);

        if ($payload === null || $payload['variant'] !== $expectedVariant) {
            $this->abort(404, 'Média introuvable.');
        }

        $galleryToken = $this->tokens->find($payload['token_id']);

        if ($galleryToken === null
            || GalleryTokenRepository::isRevoked($galleryToken)
            || GalleryTokenRepository::hasExpired($galleryToken)) {
            $this->abort(410, "Ce lien n'est plus valide.");
        }

        $gallery = $this->galleries->find((int) $galleryToken['gallery_id']);

        if ($gallery === null || !GalleryRepository::isReachable($gallery)) {
            $this->abort(410, "Cette galerie n'est plus disponible.");
        }

        if ($this->access->isPasswordProtected($gallery) && !$this->access->isUnlocked((int) $gallery['id'])) {
            $this->abort(403, 'Galerie protégée par mot de passe.');
        }

        // Scoping the lookup by gallery id is what stops a valid token for
        // gallery A from addressing a photo in gallery B.
        $photo = $this->photos->findInGallery($payload['photo_id'], (int) $gallery['id']);

        if ($photo === null || (string) $photo['status'] !== 'ready') {
            $this->abort(404, 'Photo introuvable.');
        }

        return $expectedVariant === MediaTokenService::VARIANT_ORIGINAL
            ? $this->serveOriginal($request, $gallery, $galleryToken, $photo)
            : $this->serveVariant($request, $photo, $expectedVariant);
    }

    /** @param array<string, mixed> $photo */
    private function serveVariant(Request $request, array $photo, string $variantCode): Response
    {
        $variantType = $variantCode === MediaTokenService::VARIANT_THUMBNAIL
            ? VariantType::THUMBNAIL
            : VariantType::PREVIEW;

        $variant = $this->photos->variant((int) $photo['id'], $variantType);

        if ($variant === null) {
            // No fallback to the original. A missing derivative is a
            // processing failure to fix, not a reason to hand out a master.
            $this->abort(404, 'Aperçu indisponible pour cette photo.');
        }

        return $this->downloads->serve(
            (string) $variant['storage_path'],
            (string) $variant['mime_type'],
            $this->displayName($photo, 'jpg'),
            'inline',
            true,
            $request
        );
    }

    /**
     * @param array<string, mixed> $gallery
     * @param array<string, mixed> $galleryToken
     * @param array<string, mixed> $photo
     */
    private function serveOriginal(Request $request, array $gallery, array $galleryToken, array $photo): Response
    {
        if ((string) $galleryToken['token_type'] !== TokenType::DOWNLOAD) {
            $this->abort(403, 'Ce lien ne permet pas le téléchargement.');
        }

        if (!(bool) $gallery['download_enabled']) {
            $this->abort(403, "Le téléchargement n'est pas activé pour cette galerie.");
        }

        if (!(bool) $photo['downloadable']) {
            $this->abort(403, 'Cette photo n\'est pas téléchargeable.');
        }

        $bytes = (int) $photo['file_size'];

        $this->downloads->logPhotoDownload(
            (int) $gallery['id'],
            (int) $photo['id'],
            (int) $galleryToken['id'],
            $bytes,
            $request
        );

        $this->tokens->touch((int) $galleryToken['id']);

        return $this->downloads->serve(
            (string) $photo['storage_path'],
            (string) $photo['mime_type'],
            $this->displayName($photo, null),
            'attachment',
            false,
            $request
        );
    }

    /** @param array<string, mixed> $photo */
    private function displayName(array $photo, ?string $forceExtension): string
    {
        $name = (string) $photo['original_filename'];

        if ($forceExtension === null) {
            return $name;
        }

        return (string) pathinfo($name, PATHINFO_FILENAME) . '.' . $forceExtension;
    }

    /**
     * Mint the media URLs for a set of photos.
     *
     * Used by the gallery and download controllers so that URL shapes live in
     * one place.
     *
     * @param array<int, array<string, mixed>> $photos
     * @return array<int, array<string, mixed>>
     */
    public static function decorateWithMediaUrls(array $photos, GalleryAccess $access): array
    {
        $mediaTokens = new MediaTokenService();
        $tokenId = $access->tokenId() ?? 0;
        $canDownload = $access->allowsDownload();

        foreach ($photos as $index => $photo) {
            $photoId = (int) $photo['id'];

            $photos[$index]['thumb_url'] = url('media/thumb/' . $mediaTokens->mint(
                $photoId,
                $tokenId,
                MediaTokenService::VARIANT_THUMBNAIL
            ));

            $photos[$index]['preview_url'] = url('media/preview/' . $mediaTokens->mint(
                $photoId,
                $tokenId,
                MediaTokenService::VARIANT_PREVIEW
            ));

            // A download URL is only minted when the link actually permits it.
            // The absence of the URL is convenience, not the control: the
            // control is the re-check in serveOriginal().
            $photos[$index]['download_url'] = $canDownload && (bool) $photo['downloadable']
                ? url('media/download/' . $mediaTokens->mint(
                    $photoId,
                    $tokenId,
                    MediaTokenService::VARIANT_ORIGINAL
                ))
                : null;
        }

        return $photos;
    }
}
