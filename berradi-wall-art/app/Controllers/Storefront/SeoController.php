<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Core\Controller;
use App\Core\Response;
use App\Services\Seo\SitemapService;

final class SeoController extends Controller
{
    public function sitemap(): Response
    {
        return new Response(SitemapService::xml(), 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function robots(): Response
    {
        $body = (bool) setting('maintenance_mode', false) ? "User-agent: *\nDisallow: /\n" : SitemapService::robots();
        return new Response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
