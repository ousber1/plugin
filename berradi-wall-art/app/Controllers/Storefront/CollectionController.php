<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Response;
use App\Services\CatalogService;
use App\Services\SeoService;

final class CollectionController extends Controller
{
    public function index(): Response
    {
        return $this->view('storefront/collections/index', [
            'collections' => CatalogService::collections(),
            'seo' => SeoService::page(__('nav.collections'), __('collections.intro'), [
                'canonical' => '/collections',
                'jsonLd' => [SeoService::breadcrumbs([[__('nav.home'), '/'], [__('nav.collections'), null]])],
            ]),
        ]);
    }

    public function show(string $slug): Response
    {
        $collection = CatalogService::collectionBySlug($slug);
        if ($collection === null) {
            throw new HttpException(404);
        }
        $products = CatalogService::listing([
            'collection' => (int) $collection['id'],
            'sort' => (string) $this->request->query('sort', 'featured'),
        ], 24);
        return $this->view('storefront/collections/show', [
            'collection' => $collection,
            'products' => $products,
            'sort' => (string) $this->request->query('sort', 'featured'),
            'seo' => SeoService::page(
                (string) ($collection['seo_title'] ?: $collection['name']),
                $collection['seo_description'] ?: $collection['description'],
                [
                    'canonical' => '/collections/' . $slug,
                    'image' => $collection['image'] ?: null,
                    'jsonLd' => [SeoService::breadcrumbs([[__('nav.home'), '/'], [__('nav.collections'), '/collections'], [$collection['name'], null]])],
                ]
            ),
        ]);
    }
}
