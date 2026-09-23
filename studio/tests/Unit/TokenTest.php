<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\TokenType;
use App\Repositories\GalleryTokenRepository;
use App\Services\TokenService;
use Tests\Support\Factory;
use Tests\Support\TestCase;

final class TokenTest extends TestCase
{
    private TokenService $tokens;

    public function setUp(): void
    {
        $this->tokens = new TokenService();
    }

    public function testTokensAreLongAndRandom(): void
    {
        $first = $this->tokens->generateRawToken();
        $second = $this->tokens->generateRawToken();

        $this->assertSame(64, strlen($first), 'A token must be 32 random bytes, hex encoded.');
        $this->assertTrue(ctype_xdigit($first));
        $this->assertNotSame($first, $second, 'Two generated tokens must never collide.');
    }

    public function testRawTokenIsNeverStored(): void
    {
        $gallery = Factory::gallery();
        $raw = $gallery['view_token'];

        $stored = (new GalleryTokenRepository())->findByHash(hash('sha256', $raw));

        $this->assertNotNull($stored);
        $this->assertSame(hash('sha256', $raw), (string) $stored['token_hash']);
        $this->assertNotSame($raw, (string) $stored['token_hash']);
        $this->assertNotSame($raw, (string) ($stored['token_cipher'] ?? ''));
    }

    public function testValidTokenVerifies(): void
    {
        $gallery = Factory::gallery();
        $result = $this->tokens->verify($gallery['view_token'], TokenType::VIEW);

        $this->assertSame(TokenService::RESULT_VALID, $result['status']);
        $this->assertNotNull($result['token']);
    }

    public function testUnknownTokenIsRejected(): void
    {
        $result = $this->tokens->verify(str_repeat('a', 64));

        $this->assertSame(TokenService::RESULT_NOT_FOUND, $result['status']);
    }

    public function testMalformedTokenIsRejectedWithoutQuery(): void
    {
        foreach (['', 'short', 'gg' . str_repeat('a', 62), str_repeat('a', 128)] as $candidate) {
            $result = $this->tokens->verify($candidate);
            $this->assertSame(TokenService::RESULT_NOT_FOUND, $result['status']);
        }
    }

    public function testExpiredTokenIsRejected(): void
    {
        $gallery = Factory::gallery();
        $repository = new GalleryTokenRepository();
        $stored = $repository->findByHash(hash('sha256', $gallery['view_token']));

        $repository->setExpiry((int) $stored['id'], date('Y-m-d H:i:s', time() - 60));

        $result = $this->tokens->verify($gallery['view_token']);

        $this->assertSame(TokenService::RESULT_EXPIRED, $result['status']);
    }

    public function testRevokedTokenIsRejected(): void
    {
        $gallery = Factory::gallery();
        $repository = new GalleryTokenRepository();
        $stored = $repository->findByHash(hash('sha256', $gallery['view_token']));

        $repository->revoke((int) $stored['id']);

        $result = $this->tokens->verify($gallery['view_token']);

        $this->assertSame(TokenService::RESULT_REVOKED, $result['status']);
    }

    public function testTokenTypeIsEnforced(): void
    {
        $gallery = Factory::gallery();

        $viewOnDownload = $this->tokens->verify($gallery['view_token'], TokenType::DOWNLOAD);
        $downloadOnView = $this->tokens->verify($gallery['download_token'], TokenType::VIEW);

        $this->assertSame(TokenService::RESULT_WRONG_TYPE, $viewOnDownload['status']);
        $this->assertSame(TokenService::RESULT_WRONG_TYPE, $downloadOnView['status']);
    }

    public function testRegeneratingInvalidatesThePreviousToken(): void
    {
        $gallery = Factory::gallery();
        $old = $gallery['view_token'];

        $new = $this->tokens->regenerate($gallery['gallery_id'], TokenType::VIEW);

        $this->assertNotSame($old, $new['raw']);
        $this->assertSame(TokenService::RESULT_REVOKED, $this->tokens->verify($old)['status']);
        $this->assertSame(TokenService::RESULT_VALID, $this->tokens->verify($new['raw'])['status']);
    }

    public function testRawTokenCanBeRecoveredForSharing(): void
    {
        $gallery = Factory::gallery();
        $stored = (new GalleryTokenRepository())->activeFor($gallery['gallery_id'], TokenType::VIEW);

        $this->assertSame($gallery['view_token'], $this->tokens->revealRawToken($stored));
    }

    public function testRecoveryRefusesATamperedCipher(): void
    {
        $gallery = Factory::gallery();
        $repository = new GalleryTokenRepository();
        $view = $repository->activeFor($gallery['gallery_id'], TokenType::VIEW);
        $download = $repository->activeFor($gallery['gallery_id'], TokenType::DOWNLOAD);

        // Swap one row's ciphertext for another's: it still decrypts, but no
        // longer hashes to this row's token_hash.
        $view['token_cipher'] = $download['token_cipher'];

        $this->assertNull($this->tokens->revealRawToken($view));
    }

    public function testExpiryOptionsResolveToDates(): void
    {
        $this->assertNull($this->tokens->resolveExpiry('never'));

        $in24h = $this->tokens->resolveExpiry('24h');
        $this->assertNotNull($in24h);
        $this->assertGreaterThan(time(), (int) strtotime((string) $in24h));

        $custom = $this->tokens->resolveExpiry('custom', '2030-06-15');
        $this->assertSame('2030-06-15 23:59:59', $custom, 'A bare date must mean end of day.');

        $this->assertNull($this->tokens->resolveExpiry('custom', 'not a date'));
    }
}
