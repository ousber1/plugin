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
