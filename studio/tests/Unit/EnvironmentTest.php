<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Csrf;
use App\Core\Environment;
use App\Core\Request;
use App\Middleware\CsrfMiddleware;
use Tests\Support\TestCase;

/**
 * Behaviour under shared-hosting limits (ByetHost: 10 MB uploads, mail and
 * some functions disabled, tight memory).
 */
final class EnvironmentTest extends TestCase
{
    public function testIniSizesAreParsed(): void
    {
        $this->assertSame(10 * 1024 * 1024, Environment::parseBytes('10M'));
        $this->assertSame(2 * 1024 * 1024 * 1024, Environment::parseBytes('2G'));
        $this->assertSame(512 * 1024, Environment::parseBytes('512k'));
        $this->assertSame(1500, Environment::parseBytes('1500'));
        $this->assertSame(0, Environment::parseBytes('-1'));
        $this->assertSame(0, Environment::parseBytes(''));
    }

    public function testUploadLimitNeverExceedsWhatPhpAccepts(): void
    {
        $php = Environment::iniBytes('upload_max_filesize');
        $limit = Environment::uploadLimitBytes(100 * 1024 * 1024);

        $this->assertTrue($limit <= 100 * 1024 * 1024);

        if ($php > 0) {
            $this->assertTrue($limit <= $php, 'The page must not promise more than upload_max_filesize.');
        }
    }

    public function testUnknownFunctionsAreReportedUnavailable(): void
    {
        $this->assertFalse(Environment::functionAvailable('definitely_not_a_php_function'));
        $this->assertTrue(Environment::functionAvailable('strlen'));
    }

    public function testDroppedBodyIsRecognised(): void
    {
        $limit = Environment::iniBytes('post_max_size');

        if ($limit === 0) {
            $this->pass();

            return;
        }

        $server = ['REQUEST_METHOD' => 'POST', 'CONTENT_LENGTH' => (string) ($limit + 1)];

        $this->assertTrue(Environment::postBodyWasDropped($server, [], []));
        $this->assertFalse(Environment::postBodyWasDropped($server, ['_token' => 'x'], []), 'A body that arrived was not dropped.');
        $this->assertFalse(Environment::postBodyWasDropped(['REQUEST_METHOD' => 'POST', 'CONTENT_LENGTH' => '10'], [], []));
    }

    public function testOversizedUploadIsExplainedNotBlamedOnTheSession(): void
    {
        $limit = Environment::iniBytes('post_max_size');

        if ($limit === 0) {
            $this->pass();

            return;
        }

        Csrf::token();
        $request = new Request('POST', '/admin/galleries/1/photos', [], [], [
            'CONTENT_LENGTH'        => (string) ($limit * 2),
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ], [], []);

        $response = (new CsrfMiddleware())->handle($request, []);

        $this->assertSame(413, $response?->status());
        $this->assertStringContains('trop volumineux', (string) $response?->body());
    }

    public function testSmallImagesAlwaysFitInMemory(): void
    {
        $this->assertTrue(Environment::ensureMemoryForImage(400, 300));
    }
}
