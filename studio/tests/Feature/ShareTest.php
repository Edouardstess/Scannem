<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\Admin\ShareController;
use App\Core\Auth;
use App\Core\Request;
use App\Models\TokenType;
use App\Repositories\GalleryTokenRepository;
use App\Services\GalleryAccessService;
use App\Services\TokenService;
use Tests\Support\Factory;
use Tests\Support\TestCase;

/**
 * Link lifecycle, as the photographer drives it: copy, regenerate, revoke.
 */
final class ShareTest extends TestCase
{
    private ShareController $controller;

    public function setUp(): void
    {
        $this->controller = new ShareController();
        Auth::login(Factory::user('share@example.test', 'correct-horse-battery'), $this->request());
    }

    public function tearDown(): void
    {
        Auth::logout();
    }

    public function testBothLinksAreDisplayableAfterCreation(): void
    {
        $gallery = Factory::gallery();
        $links = $this->controller->links($gallery['gallery_id']);

        $this->assertNotNull($links['view']['url']);
        $this->assertNotNull($links['download']['url']);
        $this->assertStringContains('/gallery/' . $gallery['view_token'], (string) $links['view']['url']);
        $this->assertStringContains('/download/' . $gallery['download_token'], (string) $links['download']['url']);
    }

    public function testALinkIsNeverFakedWhenItCannotBeRecovered(): void
    {
        $gallery = Factory::gallery();

        // Simulate a lost or rotated APP_KEY: the ciphertext no longer opens.
        \App\Core\Database::connection()
            ->prepare('UPDATE gallery_tokens SET token_cipher = NULL WHERE gallery_id = :id')
            ->execute(['id' => $gallery['gallery_id']]);

        $links = $this->controller->links($gallery['gallery_id']);

        $this->assertNull($links['view']['url'], 'An unrecoverable link must be null, never invented.');
        $this->assertNotNull($links['view']['token'], 'The token row still exists and still works.');
    }

    public function testRegeneratingProducesAWorkingLinkAndKillsTheOldOne(): void
    {
        $gallery = Factory::gallery();
        $old = $gallery['view_token'];

        $response = $this->controller->regenerate($this->request(), [
            'id'   => (string) $gallery['gallery_id'],
            'type' => 'view',
        ]);

        $this->assertSame(302, $response->status());

        $links = $this->controller->links($gallery['gallery_id']);
        $tokens = new TokenService();
        $access = new GalleryAccessService();

        $this->assertNotNull($links['view']['url']);
        $this->assertFalse(str_contains((string) $links['view']['url'], $old));
        $this->assertSame(TokenService::RESULT_REVOKED, $tokens->verify($old)['status']);

        // The new link works end to end, not merely in the database.
        $raw = substr((string) $links['view']['url'], strrpos((string) $links['view']['url'], '/') + 1);
        $this->assertTrue($access->resolve($raw, TokenType::VIEW)->isGranted());
    }

    public function testRevokingClosesOnlyTheTargetedLink(): void
    {
        $gallery = Factory::gallery(['download_enabled' => true]);

        $this->controller->revoke($this->request(), [
            'id'   => (string) $gallery['gallery_id'],
            'type' => 'view',
        ]);

        $access = new GalleryAccessService();

        $this->assertFalse($access->resolve($gallery['view_token'], TokenType::VIEW)->isGranted());
        $this->assertTrue(
            $access->resolveForDownload($gallery['download_token'])->isGranted(),
            'Revoking the view link must not close the download link.'
        );
    }

    public function testAnUnknownLinkTypeIsRefused(): void
    {
        $gallery = Factory::gallery();
        $controller = $this->controller;
        $request = $this->request();

        $this->assertThrows(
            \App\Exceptions\HttpException::class,
            static function () use ($controller, $request, $gallery): void {
                $controller->revoke($request, ['id' => (string) $gallery['gallery_id'], 'type' => 'admin']);
            }
        );
    }

    public function testRegeneratingKeepsTheGalleryExpiry(): void
    {
        $expiry = date('Y-m-d H:i:s', strtotime('+7 days'));
        $gallery = Factory::gallery(['expires_at' => $expiry]);

        $this->controller->regenerate($this->request(), [
            'id'   => (string) $gallery['gallery_id'],
            'type' => 'view',
        ]);

        $token = (new GalleryTokenRepository())->activeFor($gallery['gallery_id'], TokenType::VIEW);

        $this->assertNotNull($token['expires_at'], 'A regenerated link must not outlive its gallery.');
    }

    public function testNotifyingWithoutAClientEmailIsRefusedNotSilentlySkipped(): void
    {
        $clientId = (new \App\Repositories\ClientRepository())->insert([
            'first_name' => 'Sans',
            'last_name'  => 'Email',
            'email'      => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $gallery = Factory::gallery(['event_id' => Factory::event($clientId)]);

        $response = $this->controller->notifyClient($this->request(), [
            'id' => (string) $gallery['gallery_id'],
        ]);

        // A redirect carrying a flash, rather than a silent success that
        // leaves the photographer thinking the client was told.
        $this->assertSame(302, $response->status());

        $flashes = \App\Core\Session::pullFlashes();
        $this->assertCount(1, $flashes);
        $this->assertSame('error', $flashes[0]['type']);
    }

    private function request(): Request
    {
        return new Request('POST', '/admin', [], [], ['HTTP_USER_AGENT' => 'PHPUnit'], [], []);
    }
}
