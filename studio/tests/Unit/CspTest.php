<?php

declare(strict_types=1);

namespace Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Support\TestCase;

/**
 * The Content-Security-Policy only allows scripts loaded from the site
 * (script-src 'self'). A browser silently refuses any inline <script>, which
 * left the client gallery and download page inert (no favourites, no ZIP)
 * while every server-side test stayed green.
 */
final class CspTest extends TestCase
{
    /** Script types the browser treats as data, never executes. */
    private const DATA_TYPES = ['application/json', 'application/ld+json'];

    public function testViewsContainNoExecutableInlineScript(): void
    {
        $root = dirname(__DIR__, 2);
        $offenders = [];

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app/Views'));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            preg_match_all('/<script\b([^>]*)>/i', (string) file_get_contents($file->getPathname()), $matches);

            foreach ($matches[1] as $attributes) {
                if (preg_match('/\bsrc\s*=/i', $attributes) === 1) {
                    continue;
                }

                preg_match('/\btype\s*=\s*["\']([^"\']+)/i', $attributes, $type);

                if (!in_array(strtolower($type[1] ?? ''), self::DATA_TYPES, true)) {
                    $offenders[] = substr($file->getPathname(), strlen($root) + 1);
                }
            }
        }

        $this->assertSame([], $offenders, 'Inline scripts are blocked by the CSP; pass data through a JSON block or data-* attributes.');
    }

    public function testInlineEventHandlersAreNotUsed(): void
    {
        $root = dirname(__DIR__, 2);
        $offenders = [];

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app/Views'));

        foreach ($files as $file) {
            if ($file->getExtension() === 'php'
                && preg_match('/<[a-z][^>]*\son(click|load|submit|change|input|error)\s*=/i', (string) file_get_contents($file->getPathname())) === 1) {
                $offenders[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }

        $this->assertSame([], $offenders, 'onclick="" and friends are inline scripts too.');
    }
}
