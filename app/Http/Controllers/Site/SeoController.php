<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\SeoService;
use App\Services\SitemapService;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function robots(SeoService $seo): Response
    {
        $allowIndexing = $seo->environmentAllowsIndexing()
            && $seo->settings()['default_index'];

        if (! $allowIndexing) {
            $body = "User-agent: *\nDisallow: /\n";

            return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $disallow = collect(config('seo.private_path_prefixes', []))
            ->map(fn (string $path) => 'Disallow: '.$path)
            ->implode("\n");

        $sitemap = $seo->settings()['canonical_domain'].'/sitemap.xml';
        $body = "User-agent: *\nAllow: /\n{$disallow}\n\nSitemap: {$sitemap}\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(SitemapService $sitemap, SeoService $seo): Response
    {
        if (! $seo->environmentAllowsIndexing() || ! $seo->settings()['default_index']) {
            $empty = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';

            return response($empty, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
        }

        return response($sitemap->xml(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
