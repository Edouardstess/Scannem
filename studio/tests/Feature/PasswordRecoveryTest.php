<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth;
use App\Core\Request;
use App\Repositories\UserRepository;
use App\Services\PasswordRecoveryService;
use App\Services\RateLimiter;
use Tests\Support\TestCase;

/**
 * Getting back into the admin without SSH or e-mail: a reset file dropped by
 * FTP into storage/private, applied once, then destroyed.
 */
final class PasswordRecoveryTest extends TestCase
{
    private string $directory;

    public function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/recovery-' . bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    public function tearDown(): void
    {
        @unlink($this->directory . '/' . PasswordRecoveryService::FILENAME);
        @rmdir($this->directory);
    }

    public function testTheFileResetsThePasswordLiftsTheLockoutAndDisappears(): void
    {
        $email = 'recovery-' . bin2hex(random_bytes(3)) . '@example.test';
        (new UserRepository())->create('Photographe', $email, 'ancien-mot-de-passe', 'SUPER_ADMIN');

        $limiter = new RateLimiter();
        for ($i = 0; $i < 6; $i++) {
            $limiter->hit('login|email|' . $email, 900);
        }
        $this->assertTrue($limiter->tooManyAttempts('login|email|' . $email, 5));

        // As typed in Windows Notepad: BOM, CRLF, a comment, capitals in the address.
        $this->write("\xEF\xBB\xBF# nouveau mot de passe\r\nemail = " . strtoupper($email) . "\r\npassword=Nouveau-Secret-2026\r\n");

        $result = $this->service()->apply($this->request());

        $this->assertTrue($result['ok'], $result['message']);
        $this->assertFalse(is_file($this->directory . '/' . PasswordRecoveryService::FILENAME), 'The clear-text password must not stay on disk.');
        $this->assertFalse($limiter->tooManyAttempts('login|email|' . $email, 5), 'The lockout is lifted.');
        $this->assertNotNull(Auth::attempt($email, 'Nouveau-Secret-2026'));
        $this->assertNull(Auth::attempt($email, 'ancien-mot-de-passe'));
    }

    public function testAWrongFileChangesNothingAndIsStillDeleted(): void
    {
        $email = 'recovery-' . bin2hex(random_bytes(3)) . '@example.test';
        (new UserRepository())->create('Photographe', $email, 'ancien-mot-de-passe', 'SUPER_ADMIN');

        foreach ([
            "email=$email\npassword=court",
            "email=inconnu@example.test\npassword=assez-long-2026",
            "password=assez-long-2026",
        ] as $content) {
            $this->write($content);
            $result = $this->service()->apply($this->request());

            $this->assertFalse($result['ok']);
            $this->assertFalse(is_file($this->directory . '/' . PasswordRecoveryService::FILENAME));
        }

        $this->assertNotNull(Auth::attempt($email, 'ancien-mot-de-passe'));
    }

    public function testNothingHappensWithoutAFile(): void
    {
        $this->assertNull($this->service()->apply($this->request()));
    }

    public function testFrenchKeysAreUnderstood(): void
    {
        $this->assertSame(
            ['email' => 'a@b.c', 'password' => 'x y z'],
            PasswordRecoveryService::parse("Email: a@b.c\nMot de passe : x y z\n")
        );
    }

    private function service(): PasswordRecoveryService
    {
        return new PasswordRecoveryService($this->directory);
    }

    private function write(string $content): void
    {
        file_put_contents($this->directory . '/' . PasswordRecoveryService::FILENAME, $content);
    }

    private function request(): Request
    {
        return new Request('GET', '/admin/login', [], [], ['REMOTE_ADDR' => '203.0.113.9'], [], []);
    }
}
