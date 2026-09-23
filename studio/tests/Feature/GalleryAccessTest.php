<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTO\GalleryAccess;
use App\Models\GalleryStatus;
use App\Models\TokenType;
use App\Repositories\GalleryRepository;
use App\Services\GalleryAccessService;
use App\Services\GalleryService;
use Tests\Support\Factory;
use Tests\Support\TestCase;

/**
 * The VIEW / DOWNLOAD separation, exercised through the single gate every
 * client request passes through.
 */
final class GalleryAccessTest extends TestCase
{
    private GalleryAccessService $access;

    public function setUp(): void
    {
        $this->access = new GalleryAccessService();
    }

    public function testEveryGalleryGetsBothLinksAtCreation(): void
    {
        $gallery = Factory::gallery();

        $this->assertSame(64, strlen($gallery['view_token']));
        $this->assertSame(64, strlen($gallery['download_token']));
        $this->assertNotSame($gallery['view_token'], $gallery['download_token']);
    }

    public function testViewTokenGrantsViewingButNotDownloading(): void
    {
        $gallery = Factory::gallery(['download_enabled' => true]);
        $access = $this->access->resolve($gallery['view_token'], TokenType::VIEW);

        $this->assertTrue($access->isGranted());
        $this->assertSame(TokenType::VIEW, $access->tokenType());
        $this->assertFalse(
            $access->allowsDownload(),
            'A VIEW token must never allow downloading, even when the gallery permits downloads.'
        );
    }

    public function testDownloadTokenGrantsDownloadingWhenEnabled(): void
    {
        $gallery = Factory::gallery(['download_enabled' => true]);
        $access = $this->access->resolveForDownload($gallery['download_token']);

        $this->assertTrue($access->isGranted());
        $this->assertTrue($access->allowsDownload());
    }

    public function testDownloadTokenIsInertWhileDownloadsAreDisabled(): void
    {
        $gallery = Factory::gallery(['download_enabled' => false]);
        $access = $this->access->resolveForDownload($gallery['download_token']);

        $this->assertFalse($access->isGranted());
        $this->assertSame(GalleryAccess::DOWNLOAD_DISABLED, $access->status);
    }

    public function testTokensCannotBeUsedOnTheOtherRoute(): void
    {
        $gallery = Factory::gallery();

        $this->assertSame(
            GalleryAccess::WRONG_TYPE,
            $this->access->resolve($gallery['view_token'], TokenType::DOWNLOAD)->status
        );
        $this->assertSame(
            GalleryAccess::WRONG_TYPE,
            $this->access->resolve($gallery['download_token'], TokenType::VIEW)->status
        );
    }

    public function testDisablingTheGalleryClosesBothLinksAtOnce(): void
    {
        $gallery = Factory::gallery();
        (new GalleryService())->disable($gallery['gallery_id']);

        $view = $this->access->resolve($gallery['view_token'], TokenType::VIEW);
        $download = $this->access->resolve($gallery['download_token'], TokenType::DOWNLOAD);

        $this->assertFalse($view->isGranted());
        $this->assertFalse($download->isGranted());
    }

    public function testADraftGalleryIsNotReachable(): void
    {
        $gallery = Factory::gallery(['status' => GalleryStatus::DRAFT]);
        $access = $this->access->resolve($gallery['view_token'], TokenType::VIEW);

        $this->assertFalse($access->isGranted());
        $this->assertSame(GalleryAccess::GALLERY_CLOSED, $access->status);
    }

    public function testAnExpiredGalleryIsRefusedEvenWithALiveToken(): void
    {
        // Only the gallery expires here; the tokens keep no expiry of their
        // own, so this proves the gallery-level check is enforced on its own
        // rather than riding on the token's.
        $gallery = Factory::gallery();
        (new GalleryRepository())->update($gallery['gallery_id'], [
            'expires_at' => date('Y-m-d H:i:s', time() - 3600),
        ]);

        $access = $this->access->resolve($gallery['view_token'], TokenType::VIEW);

        $this->assertFalse($access->isGranted());
        $this->assertSame(GalleryAccess::GALLERY_EXPIRED, $access->status);
        $this->assertSame(410, $access->httpStatus());
    }

    public function testCreatingAGalleryWithAnExpiryAlsoExpiresItsLinks(): void
    {
        $gallery = Factory::gallery(['expires_at' => date('Y-m-d H:i:s', time() - 3600)]);

        $access = $this->access->resolve($gallery['view_token'], TokenType::VIEW);

        $this->assertFalse($access->isGranted());
        $this->assertSame(GalleryAccess::EXPIRED, $access->status);
        $this->assertSame(410, $access->httpStatus());
    }

    public function testPasswordProtectedGalleryRequiresUnlocking(): void
    {
        $gallery = Factory::gallery(['password' => 'ouvre-toi']);

        $locked = $this->access->resolve($gallery['view_token'], TokenType::VIEW);

        $this->assertFalse($locked->isGranted());
        $this->assertTrue($locked->needsPassword());

        $row = (new GalleryRepository())->find($gallery['gallery_id']);

        $this->assertFalse($this->access->unlock($row, 'mauvais'), 'A wrong password must not unlock.');
        $this->assertTrue($this->access->unlock($row, 'ouvre-toi'));

        $unlocked = $this->access->resolve($gallery['view_token'], TokenType::VIEW);

        $this->assertTrue($unlocked->isGranted());
    }

    public function testUnlockingOneGalleryDoesNotUnlockAnother(): void
    {
        $first = Factory::gallery(['password' => 'premier-mot-de-passe']);
        $second = Factory::gallery(['password' => 'second-mot-de-passe']);

        $row = (new GalleryRepository())->find($first['gallery_id']);
        $this->access->unlock($row, 'premier-mot-de-passe');

        $this->assertTrue($this->access->isUnlocked($first['gallery_id']));
        $this->assertFalse($this->access->isUnlocked($second['gallery_id']));
        $this->assertTrue($this->access->resolve($second['view_token'], TokenType::VIEW)->needsPassword());
    }

    public function testGalleryPasswordIsStoredHashed(): void
    {
        $gallery = Factory::gallery(['password' => 'ouvre-toi']);
        $row = (new GalleryRepository())->find($gallery['gallery_id']);

        $this->assertNotSame('ouvre-toi', (string) $row['password_hash']);
        $this->assertTrue(password_verify('ouvre-toi', (string) $row['password_hash']));
    }

    public function testUpdatingAGalleryKeepsItsPasswordWhenTheFieldIsBlank(): void
    {
        $gallery = Factory::gallery(['password' => 'ouvre-toi']);
        $service = new GalleryService();

        $service->update($gallery['gallery_id'], [
            'title'    => 'Titre modifié',
            'event_id' => (new GalleryRepository())->find($gallery['gallery_id'])['event_id'],
            'password' => '',
            'status'   => GalleryStatus::ACTIVE,
        ]);

        $row = (new GalleryRepository())->find($gallery['gallery_id']);

        $this->assertTrue(
            password_verify('ouvre-toi', (string) $row['password_hash']),
            'Saving the form with a blank password field must not unprotect the gallery.'
        );
    }

    public function testPasswordCanBeRemovedExplicitly(): void
    {
        $gallery = Factory::gallery(['password' => 'ouvre-toi']);
        $service = new GalleryService();

        $service->update($gallery['gallery_id'], [
            'title'           => 'Sans mot de passe',
            'event_id'        => (new GalleryRepository())->find($gallery['gallery_id'])['event_id'],
            'remove_password' => true,
            'status'          => GalleryStatus::ACTIVE,
        ]);

        $row = (new GalleryRepository())->find($gallery['gallery_id']);

        $this->assertNull($row['password_hash']);
    }

    public function testDeletingAGalleryRemovesItsTokensAndFiles(): void
    {
        $gallery = Factory::gallery();
        Factory::photo($gallery['gallery_id']);

        $storage = new \App\Services\StorageService();
        $paths = array_map(
            static fn (array $row): string => (string) $row['storage_path'],
            (new \App\Repositories\PhotoRepository())->allPathsForGallery($gallery['gallery_id'])
        );

        $this->assertGreaterThan(0, count($paths));

        (new GalleryService())->delete($gallery['gallery_id']);

        $this->assertNull((new GalleryRepository())->find($gallery['gallery_id']));
        $this->assertSame(
            \App\Services\TokenService::RESULT_NOT_FOUND,
            (new \App\Services\TokenService())->verify($gallery['view_token'])['status']
        );

        foreach ($paths as $path) {
            $this->assertFalse($storage->exists($path));
        }
    }
}
