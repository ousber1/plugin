<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Core\Controller;
use App\Core\Response;
use App\Services\CatalogService;
use App\Services\SeoService;

/**
 * Premium homepage. Every section is fed by real database content and is hidden when empty
 * (no invented products, reviews or statistics).
 */
final class HomeController extends Controller
{
    public function index(): Response
    {
        $data = [
            'heroBanners' => CatalogService::banners('hero', 3),
            'promoBanners' => CatalogService::banners('promo', 2),
            'collections' => CatalogService::collections(true, 4),
            'bestSellers' => CatalogService::byFlag('best_seller', 8),
            'newArrivals' => CatalogService::byFlag('new', 8, 'newest'),
            'offers' => CatalogService::byFlag('promo', 4),
            'categories' => CatalogService::categories(true),
            'reviews' => CatalogService::featuredReviews(6),
            'reviewStats' => CatalogService::reviewStats(),
            'gallery' => CatalogService::gallery(8),
            'faqs' => CatalogService::faqs(6),
        ];
        if ($data['categories'] === []) {
            $data['categories'] = array_slice(CatalogService::categories(), 0, 8);
        }
        $data['seo'] = SeoService::page('', null, [
            'canonical' => '/',
            'jsonLd' => [SeoService::organization(), [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => (string) setting('company_name', 'BERRADI WALL ART'),
                'url' => absolute_url('/'),
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => absolute_url('shop') . '?q={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ],
            ]],
        ]);
        return $this->view('storefront/home', $data);
    }
}
