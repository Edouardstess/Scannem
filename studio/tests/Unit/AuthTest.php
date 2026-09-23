<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Auth;
use App\Core\Request;
use App\Models\Role;
use App\Repositories\UserRepository;
use App\Services\RateLimiter;
use Tests\Support\Factory;
use Tests\Support\TestCase;

final class AuthTest extends TestCase
{
    private const PASSWORD = 'correct-horse-battery';

    public function testPasswordsAreStoredHashed(): void
    {
        $user = Factory::user('hash@example.test', self::PASSWORD);
        $hash = (string) $user['password_hash'];

        $this->assertNotSame(self::PASSWORD, $hash, 'A password must never be stored in clear text.');
        $this->assertTrue(password_verify(self::PASSWORD, $hash));
        $this->assertTrue(str_starts_with($hash, '$2y$') || str_starts_with($hash, '$argon'));
    }

    public function testCorrectCredentialsAuthenticate(): void
    {
        Factory::user('good@example.test', self::PASSWORD);

        $this->assertNotNull(Auth::attempt('good@example.test', self::PASSWORD));
    }

    public function testEmailComparisonIsCaseInsensitive(): void
    {
        Factory::user('case@example.test', self::PASSWORD);

        $this->assertNotNull(Auth::attempt('CASE@Example.TEST', self::PASSWORD));
    }

    public function testWrongPasswordIsRefused(): void
    {
        Factory::user('bad@example.test', self::PASSWORD);

        $this->assertNull(Auth::attempt('bad@example.test', 'wrong-password'));
    }

    public function testUnknownAccountIsRefused(): void
    {
        $this->assertNull(Auth::attempt('nobody@example.test', self::PASSWORD));
    }

    public function testSuspendedAccountCannotAuthenticate(): void
    {
        $user = Factory::user('suspended@example.test', self::PASSWORD);
        (new UserRepository())->update((int) $user['id'], ['status' => 'suspended']);

        $this->assertNull(Auth::attempt('suspended@example.test', self::PASSWORD));
    }

    public function testSessionIdentifiesTheUser(): void
    {
        $user = Factory::user('session@example.test', self::PASSWORD);

        $this->assertFalse(Auth::check());

        Auth::login($user, $this->request());

        $this->assertTrue(Auth::check());
        $this->assertSame((int) $user['id'], Auth::id());

        Auth::logout();

        $this->assertFalse(Auth::check());
    }

    public function testSuspendingAnAccountEndsItsSessionImmediately(): void
    {
        $user = Factory::user('revoked@example.test', self::PASSWORD);
        Auth::login($user, $this->request());

        $this->assertTrue(Auth::check());

        (new UserRepository())->update((int) $user['id'], ['status' => 'suspended']);
        Auth::forgetCache();

        $this->assertFalse(Auth::check(), 'A suspended account must lose access without waiting for expiry.');
    }

    public function testRateLimiterBlocksAfterRepeatedFailures(): void
    {
        $limiter = new RateLimiter();
        $key = 'login|email|brute@example.test';

        $this->assertFalse($limiter->tooManyAttempts($key, 5));

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $limiter->hit($key, 900);
        }

        $this->assertTrue($limiter->tooManyAttempts($key, 5));
        $this->assertGreaterThan(0, $limiter->availableIn($key));

        $limiter->clear($key);

        $this->assertFalse($limiter->tooManyAttempts($key, 5));
    }

    public function testRateLimitKeysAreHashedInStorage(): void
    {
        $limiter = new RateLimiter();
        $limiter->hit('login|email|private@example.test', 900);

        $rows = \App\Core\Database::connection()->query('SELECT rate_key FROM rate_limits')->fetchAll();
        $stored = (string) $rows[0]['rate_key'];

        $this->assertSame(64, strlen($stored));
        $this->assertFalse(str_contains($stored, 'private@example.test'));
    }

    public function testPasswordChangeInvalidatesTheOldPassword(): void
    {
        $user = Factory::user('change@example.test', self::PASSWORD);

        (new UserRepository())->updatePassword((int) $user['id'], 'a-brand-new-passphrase');

        $this->assertNull(Auth::attempt('change@example.test', self::PASSWORD));
        $this->assertNotNull(Auth::attempt('change@example.test', 'a-brand-new-passphrase'));
    }

    private function request(): Request
    {
        return new Request('GET', '/admin', [], [], ['HTTP_USER_AGENT' => 'PHPUnit'], [], []);
    }
}
