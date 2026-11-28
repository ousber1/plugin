<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Response;
use App\Services\CatalogService;
use App\Services\SeoService;

/**
 * Product listings: shop, category, best sellers, new arrivals, special offers.
 */
final class ShopController extends Controller
{
    /** @return array<string,string> */
    private function filters(): array
    {
        $f = [];
        foreach (['q', 'type', 'min', 'max', 'sort', 'flag', 'category'] as $k) {
            $v = $this->request->query($k);
            if (is_string($v) && trim($v) !== '') {
                $f[$k] = mb_substr(trim($v), 0, 100);
            }
        }
        return $f;
    }

    /** @param array<string,mixed> $context */
    private function render(array $context, array $fixed = []): Response
    {
        $filters = array_merge($this->filters(), $fixed);
        $products = CatalogService::listing($filters, 24);
        $categories = CatalogService::categories();
        $noindex = count(array_diff_key($filters, array_flip(['category', 'flag', 'family']))) > 0;
        $seo = SeoService::page(
            (string) ($context['seo_title'] ?? $context['heading']),
            $context['seo_description'] ?? $context['intro'] ?? null,
            [
                'canonical' => $context['canonical'],
                'noindex' => $noindex,
                'jsonLd' => [SeoService::breadcrumbs($context['breadcrumbs'])],
            ]
        );
        return $this->view('storefront/shop/index', array_merge($context, [
            'products' => $products,
            'filters' => $filters,
            'fixed' => $fixed,
            'categories' => $categories,
            'bounds' => CatalogService::priceBounds(),
            'seo' => $seo,
        ]));
    }

    public function index(): Response
    {
        $q = trim((string) $this->request->query('q', ''));
        if ($q !== '' && (int) $this->request->query('page', 1) <= 1) {
            \App\Services\Tracking\TrackingService::event('search', ['search_term' => mb_substr($q, 0, 100), 'items' => []]);
        }
        return $this->render([
            'heading' => $q !== '' ? __('shop.search_results', ['q' => $q]) : __('shop.title'),
            'intro' => $q !== '' ? null : __('shop.intro'),
            'eyebrow' => __('shop.eyebrow'),
            'action' => route('shop.index'),
            'canonical' => '/shop',
            'breadcrumbs' => [[__('nav.home'), '/'], [__('nav.shop'), null]],
        ]);
    }

    public function category(string $slug): Response
    {
        $category = CatalogService::categoryBySlug($slug);
        if ($category === null) {
            throw new HttpException(404);
        }
        return $this->render([
            'heading' => $category['name'],
            'intro' => $category['description'] ?: null,
            'eyebrow' => __('shop.category'),
            'seo_title' => $category['seo_title'] ?: $category['name'],
            'seo_description' => $category['seo_description'] ?: ($category['description'] ?: null),
            'action' => route('shop.category', ['slug' => $slug]),
            'canonical' => '/shop/category/' . $slug,
            'currentCategory' => $slug,
            'heroImage' => $category['image'],
            'breadcrumbs' => [[__('nav.home'), '/'], [__('nav.shop'), '/shop'], [$category['name'], null]],
        ], ['category' => $slug]);
    }

    public function bestSellers(): Response
    {
        return $this->render([
            'heading' => __('nav.best_sellers'),
            'intro' => __('shop.best_sellers_intro'),
            'eyebrow' => __('shop.eyebrow'),
            'action' => route('shop.best_sellers'),
            'canonical' => '/best-sellers',
            'breadcrumbs' => [[__('nav.home'), '/'], [__('nav.best_sellers'), null]],
        ], ['flag' => 'best_seller']);
    }

    public function newArrivals(): Response
    {
        return $this->render([
            'heading' => __('shop.new_arrivals'),
            'intro' => __('shop.new_arrivals_intro'),
            'eyebrow' => __('shop.eyebrow'),
            'action' => route('shop.new'),
            'canonical' => '/new-arrivals',
            'breadcrumbs' => [[__('nav.home'), '/'], [__('shop.new_arrivals'), null]],
        ], ['flag' => 'new']);
    }

    public function offers(): Response
    {
        return $this->render([
            'heading' => __('shop.offers'),
            'intro' => __('shop.offers_intro'),
            'eyebrow' => __('shop.eyebrow'),
            'action' => route('shop.offers'),
            'canonical' => '/offers',
            'breadcrumbs' => [[__('nav.home'), '/'], [__('shop.offers'), null]],
        ], ['flag' => 'promo']);
    }

    public function wallArt(): Response
    {
        return $this->universe('wall');
    }

    public function gifts(): Response
    {
        return $this->universe('gift');
    }

    public function laser(): Response
    {
        return $this->universe('laser');
    }

    /** Universes of the print shop: decorative wall art, personalised gifts, laser engraving & cutting. */
    private function universe(string $family): Response
    {
        $paths = ['wall' => '/wall-art', 'gift' => '/personalized-gifts', 'laser' => '/laser-engraving'];
        if (!isset($paths[$family])) {
            throw new \App\Core\HttpException(404);
        }
        return $this->render([
            'heading' => __('universe.' . $family . '_title'),
            'intro' => __('universe.' . $family . '_intro'),
            'eyebrow' => __('universe.eyebrow'),
            'action' => url(ltrim($paths[$family], '/')),
            'canonical' => $paths[$family],
            'breadcrumbs' => [[__('nav.home'), '/'], [__('universe.' . $family . '_title'), null]],
        ], ['family' => $family]);
    }
}
