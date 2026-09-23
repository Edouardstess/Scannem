<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\Factory;
use Tests\Support\TestCase;

/**
 * The web installer is the single most dangerous file in the project: left
 * reachable, it can rewrite the database credentials and create an
 * administrator. These tests pin the properties that stop that.
 */
final class InstallerTest extends TestCase
{
    private string $installer;

    public function setUp(): void
    {
        $this->installer = dirname(__DIR__, 2) . '/public/install.php';
    }

    public function testTheInstallerRefusesToRunWhenAnAccountExists(): void
    {
        Factory::user('owner@example.test', 'correct-horse-battery');

        $source = (string) file_get_contents($this->installer);

        // The guard is a database check, not only a lock file: deleting the
        // lock must not be enough to reopen the installer.
        $this->assertStringContains('SELECT COUNT(*) FROM users', $source);
        $this->assertStringContains("\$step = 'locked'", $source);
    }

    public function testTheLockGuardRunsBeforeAnyPostIsProcessed(): void
    {
        $source = (string) file_get_contents($this->installer);

        $guard = strpos($source, 'if (alreadyInstalled($lockFile))');
        $post = strpos($source, "\$_SERVER['REQUEST_METHOD'] === 'POST'");

        $this->assertNotNull($guard === false ? null : $guard);
        $this->assertNotNull($post === false ? null : $post);
        $this->assertTrue(
            $guard < $post,
            'The already-installed guard must be evaluated before any POST branch.'
        );
    }

    public function testTheInstallerIsCsrfProtected(): void
    {
        $source = (string) file_get_contents($this->installer);

        $this->assertStringContains('Csrf::validate', $source);
        $this->assertStringContains('name="_token"', $source);
    }

    public function testTheInstallerNeverEchoesTheDatabasePassword(): void
    {
        $source = (string) file_get_contents($this->installer);

        // A password rendered back into the form would end up in browser
        // history, in a screenshot, or in a support ticket.
        $this->assertFalse(
            str_contains($source, '$_POST[\'db_password\']) ?>'),
            'The database password must never be echoed back into the form.'
        );
        $this->assertStringContains('id="db_password" name="db_password" autocomplete="off"', $source);
    }

    public function testTheInstallerIsNotIndexable(): void
    {
        $source = (string) file_get_contents($this->installer);

        $this->assertStringContains('noindex, nofollow', $source);
    }

    public function testTheInstallerWritesAnEnvThatIsSecureByDefault(): void
    {
        $source = (string) file_get_contents($this->installer);

        $this->assertStringContains("'APP_ENV=production'", $source);
        $this->assertStringContains("'APP_DEBUG=false'", $source);
        $this->assertStringContains("'HSTS_ENABLED=false'", $source);
        $this->assertStringContains('Encrypter::generateAppKey()', $source);
    }

    public function testTheApplicationWarnsWhileTheInstallerIsStillPresent(): void
    {
        $settings = (string) file_get_contents(
            dirname(__DIR__, 2) . '/app/Controllers/Admin/SettingsController.php'
        );
        $console = (string) file_get_contents(dirname(__DIR__, 2) . '/bin/console.php');

        // Deleting install.php is a manual step, so the application has to
        // keep saying so until it happens.
        $this->assertStringContains("public/install.php", $settings);
        $this->assertStringContains("public/install.php", $console);
    }

    public function testTheInstallerSuppliesAnAppKey(): void
    {
        // Without APP_KEY, gallery links cannot be redisplayed and media URLs
        // fall back to a shared constant. The installer must never leave it
        // empty.
        $source = (string) file_get_contents($this->installer);

        $this->assertStringContains("'APP_KEY=' . \$appKey", $source);
        $this->assertFalse(str_contains($source, "'APP_KEY='," ), 'APP_KEY must never be written empty.');
    }
}
