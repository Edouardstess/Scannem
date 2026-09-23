<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\PortfolioRepository;
use App\Repositories\ServiceRepository;

/**
 * robots.txt and sitemap.xml.
 *
 * Only public pages are listed. Gallery links are never in the sitemap and
 * are explicitly disallowed in robots.txt — a client's gallery appearing in a
 * search index would be a serious privacy failure, quite apart from the
 * tokens being unguessable.
 */
final class SitemapController extends Controller
{
    public function __construct(
        private PortfolioRepository $portfolio = new PortfolioRepository(),
        private ServiceRepository $services = new ServiceRepository()
    ) {
    }

    /** GET /robots.txt */
    public function robots(Request $request): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /gallery/',
            'Disallow: /download/',
            'Disallow: /media/',
            'Disallow: /espace-client',
            'Allow: /',
            '',
            'Sitemap: ' . url('sitemap.xml'),
            '',
        ];

        return Response::text(implode("\n", $lines));
    }

    /** GET /sitemap.xml */
    public function sitemap(Request $request): Response
    {
        $urls = [
            ['loc' => url('/'), 'priority' => '1.0', 'changefreq' => 'monthly'],
            ['loc' => url('portfolio'), 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['loc' => url('services'), 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => url('a-propos'), 'priority' => '0.6', 'changefreq' => 'yearly'],
            ['loc' => url('contact'), 'priority' => '0.7', 'changefreq' => 'yearly'],
            ['loc' => url('reservation'), 'priority' => '0.7', 'changefreq' => 'yearly'],
        ];

        foreach ($this->portfolio->categoriesWithItems() as $category) {
            $urls[] = [
                'loc'        => url('portfolio/' . (string) $category['slug']),
                'priority'   => '0.7',
                'changefreq' => 'weekly',
            ];
        }

        foreach ($this->services->published() as $service) {
            $urls[] = [
                'loc'        => url('services/' . (string) $service['slug']),
                'priority'   => '0.6',
                'changefreq' => 'monthly',
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $entry) {
            $xml .= sprintf(
                "  <url>\n    <loc>%s</loc>\n    <changefreq>%s</changefreq>\n    <priority>%s</priority>\n  </url>\n",
                htmlspecialchars($entry['loc'], ENT_XML1, 'UTF-8'),
                $entry['changefreq'],
                $entry['priority']
            );
        }

        $xml .= '</urlset>';

        return Response::make($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }
}
