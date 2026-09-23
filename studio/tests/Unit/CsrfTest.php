<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Middleware\CsrfMiddleware;
use Tests\Support\TestCase;

final class CsrfTest extends TestCase
{
    public function setUp(): void
    {
        Csrf::rotate();
    }

    public function testTokenIsStableWithinASession(): void
    {
        $first = Csrf::token();

        $this->assertSame(64, strlen($first));
        $this->assertSame($first, Csrf::token(), 'Rotating per request would break parallel uploads.');
    }

    public function testValidationAcceptsOnlyTheCurrentToken(): void
    {
        $token = Csrf::token();

        $this->assertTrue(Csrf::validate($token));
        $this->assertFalse(Csrf::validate('wrong'));
        $this->assertFalse(Csrf::validate(''));
        $this->assertFalse(Csrf::validate(null));
        $this->assertFalse(Csrf::validate(strrev($token)));
    }

    public function testRotationInvalidatesTheOldToken(): void
    {
        $old = Csrf::token();
        Csrf::rotate();

        $this->assertFalse(Csrf::validate($old));
    }

    public function testMiddlewareLetsSafeMethodsThrough(): void
    {
        $middleware = new CsrfMiddleware();

        foreach (['GET', 'HEAD', 'OPTIONS'] as $method) {
            $request = new Request($method, '/contact', [], [], [], [], []);
            $this->assertNull($middleware->handle($request, []));
        }
    }

    public function testMiddlewareRejectsAPostWithoutAToken(): void
    {
        Csrf::token();
        $middleware = new CsrfMiddleware();
        $request = new Request('POST', '/contact', [], ['name' => 'X'], [], [], []);

        $response = $middleware->handle($request, []);

        $this->assertNotNull($response, 'A POST without a CSRF token must be refused.');
        $this->assertSame(303, $response->status());
    }

    public function testRefusalSendsTheVisitorBackToTheFormNotToThePostUrl(): void
    {
        Csrf::token();
        $middleware = new CsrfMiddleware();

        // /gallery/{token}/select only exists as POST: redirecting there would
        // show an error page instead of the gallery.
        $request = new Request('POST', '/gallery/abc/select', [], [], [
            'HTTP_HOST'    => 'studio.test',
            'HTTP_REFERER' => 'http://studio.test/gallery/abc',
        ], [], []);

        $response = $middleware->handle($request, []);

        $this->assertSame('http://studio.test/gallery/abc', $response?->headers()['location'] ?? null);
    }

    public function testRefusalNeverRedirectsToAnotherSite(): void
    {
        Csrf::token();
        $middleware = new CsrfMiddleware();

        $request = new Request('POST', '/contact', [], [], [
            'HTTP_HOST'    => 'studio.test',
            'HTTP_REFERER' => 'https://phishing.example/fake-login',
        ], [], []);

        $location = (string) ($middleware->handle($request, [])?->headers()['location'] ?? '');

        $this->assertFalse(str_contains($location, 'phishing.example'), 'A foreign Referer must never become a redirect target.');
    }

    public function testMiddlewareRejectsAWrongToken(): void
    {
        Csrf::token();
        $middleware = new CsrfMiddleware();
        $request = new Request('POST', '/contact', [], ['_token' => 'nope'], [], [], []);

        $this->assertNotNull($middleware->handle($request, []));
    }

    public function testMiddlewareAcceptsAValidToken(): void
    {
        $token = Csrf::token();
        $middleware = new CsrfMiddleware();

        $body = new Request('POST', '/contact', [], ['_token' => $token], [], [], []);
        $this->assertNull($middleware->handle($body, []));

        // The AJAX uploader sends the token as a header rather than a field.
        $header = new Request('POST', '/contact', [], [], ['HTTP_X_CSRF_TOKEN' => $token], [], []);
        $this->assertSame($token, $header->header('X-CSRF-Token'));
        $this->assertNull($middleware->handle($header, []));
    }

    public function testAjaxRequestsGetAJsonRefusal(): void
    {
        Csrf::token();
        $middleware = new CsrfMiddleware();

        $request = new Request(
            'POST',
            '/admin/galleries/1/photos',
            [],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
            [],
            []
        );

        $response = $middleware->handle($request, []);

        $this->assertNotNull($response);
        $this->assertSame(419, $response->status());
    }

    public function testAGetCannotBeUpgradedToADelete(): void
    {
        // Method override is accepted on POST only: a link or a prefetch must
        // never be able to become a destructive request.
        $request = new Request('GET', '/admin/clients/1', [], ['_method' => 'DELETE'], [], [], []);

        $this->assertSame('GET', $request->method());
    }

    public function testPostCanBeOverriddenToPutOrDelete(): void
    {
        foreach (['PUT', 'PATCH', 'DELETE'] as $verb) {
            $request = new Request('POST', '/admin/clients/1', [], ['_method' => $verb], [], [], []);
            $this->assertSame($verb, $request->method());
        }

        $bogus = new Request('POST', '/admin/clients/1', [], ['_method' => 'TRACE'], [], [], []);
        $this->assertSame('POST', $bogus->method());
    }
}
