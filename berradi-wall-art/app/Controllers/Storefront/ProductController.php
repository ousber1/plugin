<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Session;
use App\Services\CatalogService;
use App\Services\SeoService;

final class ProductController extends Controller
{
    public function show(string $slug): Response
    {
        $product = CatalogService::product($slug);
        if ($product === null) {
            throw new HttpException(404);
        }
        // count one view per product per session (cheap anti-inflation)
        $seen = (array) Session::get('viewed_products', []);
        if (!in_array((int) $product['id'], $seen, true)) {
            CatalogService::incrementViews((int) $product['id']);
            $seen[] = (int) $product['id'];
            Session::put('viewed_products', array_slice($seen, -100));
        }
        // "Sur mon mur" pro mode: the camera may be used by this page only (never by embedded third parties)
        \App\Services\Csp::allowFeature('camera');
        $v = null;
        foreach ($product['variants'] as $cand) {
            if ($v === null || (int) $cand['is_default'] === 1) {
                $v = $cand;
            }
        }
        \App\Services\Tracking\TrackingService::event('view_item', ['items' => [\App\Services\Tracking\TrackingService::item(
            (string) $product['id'], (string) $product['title'], (string) ($v['effective_price'] ?? $product['base_price'] ?? '0'), 1,
            (string) ($product['category']['name'] ?? ''), (string) ($v['name'] ?? '')
        )]]);
        $crumbs = [[__('nav.home'), '/'], [__('nav.shop'), '/shop']];
        if ($product['category']) {
            $crumbs[] = [$product['category']['name'], '/shop/category/' . $product['category']['slug']];
        }
        $crumbs[] = [$product['title'], null];

        return $this->view('storefront/product/show', [
            'product' => $product,
            'related' => CatalogService::related($product, 4),
            'crumbs' => $crumbs,
            'seo' => SeoService::page(
                (string) ($product['seo_title'] ?: $product['title']),
                $product['seo_description'] ?: ($product['short_description'] ?: $product['description']),
                [
                    'canonical' => '/product/' . $product['slug'],
                    'type' => 'product',
                    'image' => $product['main_image'] ?: null,
                    'jsonLd' => [SeoService::product($product), SeoService::breadcrumbs($crumbs)],
                ]
            ),
        ]);
    }
}
