<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\View;
use App\Services\SettingsService;
use Tests\Support\TestCase;

/**
 * Every public page wears the immersive identity: the homepage's dock and
 * footer, pages.js for the motion, and no executable inline script.
 */
final class PagesLayoutTest extends TestCase
{
    private const SERVICE = [
        'id' => 7, 'slug' => 'mariage', 'title' => 'Reportage <b>de</b> mariage', 'summary' => 'Une journée',
        'description' => "Premier paragraphe.\n\nSecond.", 'deliverables' => "Galerie privée\nRetouches",
        'price_from' => 2400, 'currency' => 'EUR', 'duration' => 'Journée', 'image_path' => '', 'status' => 'published',
    ];

    public function setUp(): void
    {
        SettingsService::flush();
        $settings = SettingsService::make()->all();
        $settings['booking_enabled'] = 1;
        View::share('settings', $settings);
        View::share('currentPath', '/services');
        View::share('flashes', []);
        View::share('errors', []);
        View::share('old', []);
    }

    public function testEveryPageUsesTheImmersiveChrome(): void
    {
        $pages = [
            'public.portfolio'   => ['items' => [], 'categories' => [], 'active' => '', 'category' => null],
            'public.services'    => ['services' => [self::SERVICE], 'pictures' => []],
            'public.service'     => ['service' => self::SERVICE, 'others' => [], 'pictures' => []],
            'public.about'       => ['services' => [self::SERVICE], 'pictures' => []],
            'public.contact'     => ['services' => [self::SERVICE]],
            'public.booking'     => ['services' => [self::SERVICE], 'preselected' => 7],
            'public.client_area' => [],
            'errors.error'       => ['status' => 404, 'message' => ''],
        ];

        foreach ($pages as $template => $data) {
            $html = View::render($template, $data);

            $this->assertStringContains('class="dock', $html, $template . ' has the dock');
            $this->assertStringContains('class="footer"', $html, $template . ' has the homepage footer');
            $this->assertStringContains('immersif/pages.js', $html, $template . ' loads pages.js');
            $this->assertStringContains('immersif/pages.css', $html, $template);
            $this->assertFalse(str_contains($html, '<b>de</b>'), $template . ' escapes the service title');
            $this->assertSame(0, preg_match('/<script(?![^>]*\bsrc=)(?![^>]*application\/(ld\+)?json)[^>]*>/i', $html), $template . ' has an inline script');
        }
    }

    public function testTheCurrentPageIsMarkedInTheNavigation(): void
    {
        $html = View::render('public.services', ['services' => [], 'pictures' => []]);

        $this->assertSame(1, preg_match('/class="dock-link[^"]*" href="[^"]*\/services" aria-current="page"/', $html));
    }

    public function testTheDockOffersPortfolioServicesAndContact(): void
    {
        $inner = View::render('public.client_area', []);
        $home = View::render('public.immersive', [
            'content'   => (new \App\Services\ImmersiveHomeService(SettingsService::make()->all()))->content(),
            'theme'     => 'light', 'accent' => '#f8cf9f', 'accentInk' => '#1a1915',
            'ctaUrl'    => url('/reservation'), 'currentPath' => '/',
        ]);

        foreach (['page intérieure' => $inner, 'accueil' => $home] as $where => $html) {
            preg_match('/<nav class="dock-nav".*?<\/nav>/s', $html, $nav);
            preg_match_all('/<a class="dock-link[^"]*" href="([^"]+)"[^>]*>([^<]+)<\/a>/', $nav[0] ?? '', $links);

            $this->assertSame(['Portfolio', 'Prestations', 'Contact'], array_map('trim', $links[2]), $where);
            $this->assertSame(
                [url('/portfolio'), url('/services'), url('/contact')],
                $links[1],
                $where
            );
        }
    }

    public function testAServiceWithoutImageBorrowsAPortfolioPhoto(): void
    {
        $html = View::render('public.services', [
            'services' => [self::SERVICE],
            'pictures' => [['full' => 'assets/uploads/portfolio/a.jpg', 'thumb' => 'assets/uploads/portfolio/a-thumb.jpg', 'title' => 'A', 'meta' => '', 'year' => 2025]],
        ]);

        $this->assertStringContains('data-follow-src="https://tests.example/assets/uploads/portfolio/a-thumb.jpg"', $html);
    }
}
