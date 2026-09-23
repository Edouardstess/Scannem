<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Core\Request;
use Tests\Support\TestCase;

/**
 * Where the application thinks it is mounted.
 *
 * Getting this wrong is what makes an install in htdocs/studio/ answer 404 on
 * every page and load no stylesheet — the site looks completely broken while
 * every other part of it works.
 */
final class BasePathTest extends TestCase
{
    public function tearDown(): void
    {
        Request::forgetCurrent();
    }

    public function testDocumentRootOnPublicHasNoPrefix(): void
    {
        $this->assertSame('', Request::detectBasePath('/', '/index.php'));
        $this->assertSame('', Request::detectBasePath('/portfolio', '/index.php'));
        $this->assertSame('', Request::detectBasePath('/gallery/abc', '/index.php'));
    }

    public function testSubdirectoryInstallStripsTheSubdirectory(): void
    {
        // XAMPP: htdocs/studio/, the root .htaccess rewrites into public/, so
        // SCRIPT_NAME carries a /public the browser never asked for.
        $script = '/studio/public/index.php';

        $this->assertSame('/studio', Request::detectBasePath('/studio/', $script));
        $this->assertSame('/studio', Request::detectBasePath('/studio', $script));
        $this->assertSame('/studio', Request::detectBasePath('/studio/portfolio', $script));
        $this->assertSame('/studio', Request::detectBasePath('/studio/gallery/abc123', $script));
    }

    public function testReachingPublicDirectlyStripsBoth(): void
    {
        $script = '/studio/public/index.php';

        $this->assertSame('/studio/public', Request::detectBasePath('/studio/public/portfolio', $script));
    }

    public function testDeeplyNestedInstallsWork(): void
    {
        $script = '/clients/photo/site/public/index.php';

        $this->assertSame('/clients/photo/site', Request::detectBasePath('/clients/photo/site/contact', $script));
    }

    public function testAnUnrelatedPathYieldsNoPrefix(): void
    {
        // Defensive: a mismatch must not chop characters off the path.
        $this->assertSame('', Request::detectBasePath('/autre/chose', '/studio/public/index.php'));
    }

    public function testCapturedRequestRoutesFromTheStrippedPath(): void
    {
        $request = $this->capture('/studio/public/index.php', '/studio/portfolio');

        $this->assertSame('/portfolio', $request->path(), 'The router must see the application path.');
        $this->assertSame('/studio', $request->basePath());
    }

    public function testTheHomepageOfASubdirectoryInstallIsTheRoot(): void
    {
        // The exact request that answered 404 before: http://localhost/studio/
        $request = $this->capture('/studio/public/index.php', '/studio/');

        $this->assertSame('/', $request->path());
    }

    public function testUrlsAreBuiltForWhereTheSiteActuallyIs(): void
    {
        // A wrong APP_URL — the value a fresh .env.example carries — must not
        // stop the stylesheets loading.
        Config::set('app.url', 'http://localhost:8000');
        $this->capture('/studio/public/index.php', '/studio/');

        $this->assertSame('http://localhost/studio/assets/css/site.css', asset('css/site.css'));
        $this->assertSame('http://localhost/studio/portfolio', url('portfolio'));
    }

    public function testAMatchingAppUrlIsHonoured(): void
    {
        // When the configured address agrees with the request, it wins, so an
        // https or www preference is respected.
        Config::set('app.url', 'https://photographe.com');
        $this->capture('/index.php', '/', 'photographe.com', true);

        $this->assertSame('https://photographe.com/portfolio', url('portfolio'));
    }

    public function testCanonicalUrlsAlwaysUseTheConfiguredAddress(): void
    {
        // A site reachable on several hostnames must still declare one
        // canonical address to search engines.
        Config::set('app.url', 'https://photographe.com');
        $this->capture('/studio/public/index.php', '/studio/portfolio');

        $this->assertSame('https://photographe.com/portfolio', canonical_url('portfolio'));
        $this->assertSame('http://localhost/studio/portfolio', url('portfolio'));
    }

    public function testUrlsFallBackToAppUrlOutsideARequest(): void
    {
        // Cron, the seeder and queued mail have nothing to detect.
        Config::set('app.url', 'https://photographe.com');
        Request::forgetCurrent();

        $this->assertSame('https://photographe.com/gallery/abc', url('gallery/abc'));
    }

    public function testAForgedHostHeaderCannotPoisonGeneratedUrls(): void
    {
        Config::set('app.url', '');
        $this->capture('/index.php', '/', "evil.test\r\nX-Injected: 1");

        $this->assertSame('http://localhost/portfolio', url('portfolio'));
    }

    private function capture(string $script, string $uri, string $host = 'localhost', bool $secure = false): Request
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME'    => $script,
            'REQUEST_URI'    => $uri,
            'HTTP_HOST'      => $host,
        ];

        if ($secure) {
            $_SERVER['HTTPS'] = 'on';
        }

        $_GET = $_POST = $_FILES = $_COOKIE = [];

        return Request::capture();
    }
}
