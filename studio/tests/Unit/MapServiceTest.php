<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Middleware\SecurityHeadersMiddleware;
use App\Services\MapService;
use App\Validators\SettingsRequest;
use Tests\Support\TestCase;

/**
 * Google Maps location: what a visitor sees, and what a pasted embed code
 * can never do (frame another site, inject markup).
 */
final class MapServiceTest extends TestCase
{
    private const EMBED = 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3783.2!2d-72.33!3d18.54';

    public function testTheIframeCodeFromGoogleIsAccepted(): void
    {
        $code = '<iframe src="' . self::EMBED . '" width="600" height="450" style="border:0;" '
            . 'allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>';

        $this->assertSame(self::EMBED, MapService::extractEmbedUrl($code));
        $this->assertSame(self::EMBED, MapService::extractEmbedUrl(self::EMBED), 'The bare URL works too.');
        $this->assertSame(self::EMBED . '&hl=fr', MapService::extractEmbedUrl(self::EMBED . '&amp;hl=fr'));
    }

    public function testAnythingButGooglesEmbedIsRefused(): void
    {
        foreach ([
            'https://evil.example/maps/embed?pb=1',
            'http://www.google.com/maps/embed?pb=1',
            'javascript:alert(1)',
            'https://maps.app.goo.gl/AbCdEf',
            'https://www.google.com/search?q=studio',
            'https://www.google.com.evil.example/maps/embed?pb=1',
            'https://user@www.google.com/maps/embed?pb=1',
            'https://www.google.com/maps/embed?pb=1"onload="alert(1)',
            '<iframe src="https://evil.example/x"></iframe>',
            'du texte au hasard',
        ] as $input) {
            $this->assertNull(MapService::extractEmbedUrl($input), 'Accepted: ' . $input);
        }
    }

    public function testTheContactAddressIsUsedWhenNoLocationIsGiven(): void
    {
        $map = new MapService(['contact_address' => '12 rue Capois, Port-au-Prince']);

        $this->assertTrue($map->isAvailable());
        $this->assertStringContains('q=12%20rue%20Capois%2C%20Port-au-Prince', (string) $map->embedUrl());
        $this->assertStringContains('output=embed', (string) $map->embedUrl());
        $this->assertSame(
            'https://www.google.com/maps/dir/?api=1&destination=12%20rue%20Capois%2C%20Port-au-Prince',
            $map->directionsUrl()
        );
    }

    public function testAnExplicitLocationAndAPastedPinTakePrecedence(): void
    {
        $map = new MapService([
            'contact_address' => 'Adresse postale',
            'map_query'       => 'Studio L’Enfant Visual, Pétion-Ville',
            'map_embed_url'   => self::EMBED,
        ]);

        $this->assertSame(self::EMBED, $map->embedUrl());
        $this->assertStringContains('P%C3%A9tion-Ville', (string) $map->searchUrl());
    }

    public function testNoLocationMeansNoMap(): void
    {
        $map = new MapService(['contact_address' => '', 'map_query' => '']);

        $this->assertFalse($map->isAvailable());
        $this->assertNull($map->directionsUrl());
    }

    public function testCoordinatesAreRecognised(): void
    {
        $this->assertSame(['lat' => 18.5392, 'lng' => -72.3364], (new MapService(['map_query' => '18.5392, -72.3364']))->coordinates());
        $this->assertNull((new MapService(['map_query' => '95, 10']))->coordinates());
        $this->assertNull((new MapService(['map_query' => 'Port-au-Prince']))->coordinates());
    }

    public function testSettingsKeepOnlyTheVerifiedUrl(): void
    {
        $form = (new SettingsRequest())->validate($this->request([
            'studio_name'    => "L'ENFANT VISUAL",
            'map_embed_code' => '<iframe src="' . self::EMBED . '"></iframe>',
            'map_show_home'  => '1',
        ]));

        $this->assertTrue($form->passes());
        $this->assertSame(self::EMBED, $form->data()['map_embed_url']);
        $this->assertFalse(array_key_exists('map_embed_code', $form->data()), 'The pasted HTML is never stored.');
        $this->assertTrue($form->data()['map_show_home']);
        $this->assertFalse($form->data()['map_click_to_load']);
    }

    public function testSettingsRejectAnUnusableCode(): void
    {
        $form = (new SettingsRequest())->validate($this->request([
            'studio_name'    => "L'ENFANT VISUAL",
            'map_embed_code' => 'https://maps.app.goo.gl/AbCdEf',
        ]));

        $this->assertTrue($form->fails());
        $this->assertStringContains('Intégrer une carte', (string) ($form->errors()['map_embed_code'] ?? ''));
    }

    public function testTheCspAllowsOnlyGoogleMapsFrames(): void
    {
        $headers = SecurityHeadersMiddleware::headers(new Request('GET', '/', [], [], [], [], []));
        $csp = (string) ($headers['Content-Security-Policy'] ?? $headers['Content-Security-Policy-Report-Only'] ?? '');

        $this->assertStringContains('frame-src https://www.google.com https://maps.google.com', $csp);
    }

    /** @param array<string, string> $body */
    private function request(array $body): Request
    {
        return new Request('POST', '/admin/settings', [], $body, [], [], []);
    }
}
