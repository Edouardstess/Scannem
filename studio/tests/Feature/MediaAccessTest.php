<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\MediaController;
use App\Core\Request;
use App\Exceptions\HttpException;
use App\Models\TokenType;
use App\Repositories\GalleryTokenRepository;
use App\Services\GalleryAccessService;
use App\Services\GalleryService;
use App\Services\MediaTokenService;
use Tests\Support\Factory;
use Tests\Support\TestCase;

/**
 * The most important guarantee in the product: a VIEW link cannot reach an
 * original file, by any route, however the URL is edited.
 */
final class MediaAccessTest extends TestCase
{
    private MediaTokenService $media;
    private MediaController $controller;
    private GalleryAccessService $access;

    public function setUp(): void
    {
        $this->media = new MediaTokenService();
        $this->controller = new MediaController();
        $this->access = new GalleryAccessService();
    }

    public function testMediaTokensAreSignedAndDecodeBack(): void
    {
        $token = $this->media->mint(12, 34, MediaTokenService::VARIANT_PREVIEW);
        $payload = $this->media->parse($token);

        $this->assertNotNull($payload);
        $this->assertSame(12, $payload['photo_id']);
        $this->assertSame(34, $payload['token_id']);
        $this->assertSame(MediaTokenService::VARIANT_PREVIEW, $payload['variant']);
    }

    public function testATamperedMediaTokenIsRejected(): void
    {
        $token = $this->media->mint(12, 34, MediaTokenService::VARIANT_PREVIEW);
        [$payload, $signature] = explode('.', $token);

        // Re-point the token at another photo, keeping the signature.
        $decoded = base64_decode(strtr($payload, '-_', '+/') . str_repeat('=', (4 - strlen($payload) % 4) % 4));
        $fields = explode('.', (string) $decoded);
        $fields[0] = '999';
        $forged = rtrim(strtr(base64_encode(implode('.', $fields)), '+/', '-_'), '=');

        $this->assertNull($this->media->parse($forged . '.' . $signature));
    }

    public function testEscalatingTheVariantToOriginalBreaksTheSignature(): void
    {
        $token = $this->media->mint(12, 34, MediaTokenService::VARIANT_PREVIEW);
        [$payload, $signature] = explode('.', $token);

        $decoded = base64_decode(strtr($payload, '-_', '+/') . str_repeat('=', (4 - strlen($payload) % 4) % 4));
        $fields = explode('.', (string) $decoded);
        $fields[2] = MediaTokenService::VARIANT_ORIGINAL;
        $forged = rtrim(strtr(base64_encode(implode('.', $fields)), '+/', '-_'), '=');

        $this->assertNull($this->media->parse($forged . '.' . $signature));
    }

    public function testAnExpiredMediaTokenIsRejected(): void
    {
        $token = $this->media->mint(12, 34, MediaTokenService::VARIANT_PREVIEW, 60);
        $payload = $this->media->parse($token);

        $this->assertNotNull($payload);

        // Re-sign a payload that has already expired: the signature is valid,
        // but the expiry must still be enforced.
        $expired = implode('.', [12, 34, MediaTokenService::VARIANT_PREVIEW, time() - 10]);
        $encoded = rtrim(strtr(base64_encode($expired), '+/', '-_'), '=');
        $signature = rtrim(strtr(base64_encode(hash_hmac(
            'sha256',
            $encoded,
            (string) config('app.key'),
            true
        )), '+/', '-_'), '=');

        $this->assertNull($this->media->parse($encoded . '.' . $signature));
    }

    public function testGarbageMediaTokensAreRejected(): void
    {
        foreach (['', 'garbage', 'a.b.c', str_repeat('x', 300), '.'] as $candidate) {
            $this->assertNull($this->media->parse($candidate));
        }
    }

    public function testViewGalleryNeverMintsADownloadUrl(): void
    {
        $gallery = Factory::gallery(['download_enabled' => true]);
        $photo = Factory::photo($gallery['gallery_id']);

        $access = $this->access->resolve($gallery['view_token'], TokenType::VIEW);
        $decorated = MediaController::decorateWithMediaUrls([$photo + ['variants' => []]], $access);

        $this->assertNotNull($decorated[0]['thumb_url']);
        $this->assertNotNull($decorated[0]['preview_url']);
        $this->assertNull($decorated[0]['download_url'], 'A VIEW gallery must expose no download URL.');
    }

    public function testDownloadGalleryMintsDownloadUrls(): void
    {
        $gallery = Factory::gallery(['download_enabled' => true]);
        $photo = Factory::photo($gallery['gallery_id']);

        $access = $this->access->resolveForDownload($gallery['download_token']);
        $decorated = MediaController::decorateWithMediaUrls([$photo + ['variants' => []]], $access);

        $this->assertNotNull($decorated[0]['download_url']);
    }

    public function testTheOriginalRouteRefusesAViewToken(): void
    {
        $gallery = Factory::gallery(['download_enabled' => true]);
        $photo = Factory::photo($gallery['gallery_id']);

        $viewToken = (new GalleryTokenRepository())->activeFor($gallery['gallery_id'], TokenType::VIEW);

        // Mint an original-variant media token bound to the VIEW gallery token.
        // The signature is genuine; only the server-side re-check stands in
        // the way, which is exactly what this asserts.
        $forged = $this->media->mint(
            (int) $photo['id'],
            (int) $viewToken['id'],
            MediaTokenService::VARIANT_ORIGINAL
        );

        $controller = $this->controller;

        $this->assertThrows(HttpException::class, static function () use ($controller, $forged): void {
            $controller->download(self::request(), ['token' => $forged]);
        });
    }

    public function testAMediaTokenStopsWorkingOnceItsGalleryTokenIsRevoked(): void
    {
        $gallery = Factory::gallery(['download_enabled' => true]);
        $photo = Factory::photo($gallery['gallery_id']);

        $downloadToken = (new GalleryTokenRepository())->activeFor($gallery['gallery_id'], TokenType::DOWNLOAD);
        $mediaToken = $this->media->mint(
            (int) $photo['id'],
            (int) $downloadToken['id'],
            MediaTokenService::VARIANT_ORIGINAL
        );

        $response = $this->controller->download(self::request(), ['token' => $mediaToken]);
        $this->assertSame(200, $response->status());

        (new GalleryService())->revokeToken($gallery['gallery_id'], TokenType::DOWNLOAD);

        $controller = $this->controller;

        $this->assertThrows(HttpException::class, static function () use ($controller, $mediaToken): void {
            $controller->download(self::request(), ['token' => $mediaToken]);
        }, 'Revoking a link must invalidate every URL already minted from it.');
    }

    public function testAMediaTokenCannotReachAnotherGallerysPhoto(): void
    {
        $first = Factory::gallery(['download_enabled' => true]);
        $second = Factory::gallery(['download_enabled' => true]);

        $stranger = Factory::photo($second['gallery_id']);
        $firstToken = (new GalleryTokenRepository())->activeFor($first['gallery_id'], TokenType::DOWNLOAD);

        $crossToken = $this->media->mint(
            (int) $stranger['id'],
            (int) $firstToken['id'],
            MediaTokenService::VARIANT_ORIGINAL
        );

        $controller = $this->controller;

        $this->assertThrows(HttpException::class, static function () use ($controller, $crossToken): void {
            $controller->download(self::request(), ['token' => $crossToken]);
        }, "A gallery's link must not address another gallery's photographs.");
    }

    public function testPreviewsAreServedToAValidViewLink(): void
    {
        $gallery = Factory::gallery();
        $photo = Factory::photo($gallery['gallery_id']);

        $viewToken = (new GalleryTokenRepository())->activeFor($gallery['gallery_id'], TokenType::VIEW);
        $token = $this->media->mint((int) $photo['id'], (int) $viewToken['id'], MediaTokenService::VARIANT_PREVIEW);

        $response = $this->controller->preview(self::request(), ['token' => $token]);

        $this->assertSame(200, $response->status());
        $this->assertSame('image/jpeg', $response->getHeader('Content-Type'));
        $this->assertStringContains('inline', (string) $response->getHeader('Content-Disposition'));
    }

    public function testOriginalsAreSentAsAttachmentsAndNeverCached(): void
    {
        $gallery = Factory::gallery(['download_enabled' => true]);
        $photo = Factory::photo($gallery['gallery_id'], 'Photo de mariage.jpg');

        $downloadToken = (new GalleryTokenRepository())->activeFor($gallery['gallery_id'], TokenType::DOWNLOAD);
        $token = $this->media->mint((int) $photo['id'], (int) $downloadToken['id'], MediaTokenService::VARIANT_ORIGINAL);

        $response = $this->controller->download(self::request(), ['token' => $token]);

        $this->assertSame(200, $response->status());
        $this->assertStringContains('attachment', (string) $response->getHeader('Content-Disposition'));
        $this->assertStringContains('Photo de mariage.jpg', (string) $response->getHeader('Content-Disposition'));
        $this->assertStringContains('no-store', (string) $response->getHeader('Cache-Control'));
        $this->assertSame('nosniff', $response->getHeader('X-Content-Type-Options'));
    }

    public function testAPhotoMarkedNotDownloadableIsRefused(): void
    {
        $gallery = Factory::gallery(['download_enabled' => true]);
        $photo = Factory::photo($gallery['gallery_id']);

        (new \App\Repositories\PhotoRepository())->setDownloadable(
            (int) $photo['id'],
            $gallery['gallery_id'],
            false
        );

        $downloadToken = (new GalleryTokenRepository())->activeFor($gallery['gallery_id'], TokenType::DOWNLOAD);
        $token = $this->media->mint((int) $photo['id'], (int) $downloadToken['id'], MediaTokenService::VARIANT_ORIGINAL);

        $controller = $this->controller;

        $this->assertThrows(HttpException::class, static function () use ($controller, $token): void {
            $controller->download(self::request(), ['token' => $token]);
        });
    }

    public function testMediaIsRefusedWhileAGalleryPasswordIsUnentered(): void
    {
        $gallery = Factory::gallery(['password' => 'ouvre-toi']);
        $photo = Factory::photo($gallery['gallery_id']);

        $viewToken = (new GalleryTokenRepository())->activeFor($gallery['gallery_id'], TokenType::VIEW);
        $token = $this->media->mint((int) $photo['id'], (int) $viewToken['id'], MediaTokenService::VARIANT_PREVIEW);

        $controller = $this->controller;

        $this->assertThrows(HttpException::class, static function () use ($controller, $token): void {
            $controller->preview(self::request(), ['token' => $token]);
        }, 'Knowing an image URL must not bypass the gallery password.');
    }

    private static function request(): Request
    {
        return new Request('GET', '/media', [], [], ['REMOTE_ADDR' => '203.0.113.9'], [], []);
    }
}
