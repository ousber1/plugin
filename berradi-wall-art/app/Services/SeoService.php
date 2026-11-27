<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Helpers\Money;
use App\Helpers\Str;

/**
 * Builds per-page SEO data consumed by partials/head.php:
 * title, meta description, canonical URL, Open Graph / Twitter tags and JSON-LD blocks.
 */
final class SeoService
{
    /** schema.org types offered in SEO → General (Store and its subtypes are LocalBusiness). */
    public const BUSINESS_TYPES = ['Organization', 'Store', 'HomeGoodsStore', 'ArtGallery', 'LocalBusiness'];

    /**
     * @param array<string,mixed> $extra keys: image (public upload path), type, noindex, canonical (path), jsonLd (list)
     * @return array<string,mixed>
     */
    public static function page(string $title, ?string $description = null, array $extra = []): array
    {
        $request = Request::current();
        $path = $extra['canonical'] ?? ($request?->path() ?? '/');
        // canonical keeps only the pagination parameter (filters/sorting are not indexable duplicates)
        $page = (int) ($request?->query('page') ?? 1);
        $alternates = self::alternates((string) $path, $page);
        $canonical = $alternates[locale()] ?? $alternates['x-default'];
        $image = (string) ($extra['image'] ?? setting('seo_og_image', '') ?: setting('company_logo', ''));
        $desc = $description !== null && trim($description) !== ''
            ? Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags($description)) ?? ''), 300)
            : (string) setting('seo_meta_description', '');
        // owner's overrides for the fixed pages (SEO → Pages), per language
        $override = isset($extra['canonical']) && empty($extra['noindex']) ? \App\Services\Seo\SeoPages::override((string) $extra['canonical'], locale()) : null;
        $exactTitle = null;
        if ($override !== null) {
            if (($override['meta_title'] ?? '') !== '' && $override['meta_title'] !== null) {
                $exactTitle = (string) $override['meta_title'];
            }
            if (($override['meta_description'] ?? '') !== '' && $override['meta_description'] !== null) {
                $desc = (string) $override['meta_description'];
            }
            if ((int) $override['noindex'] === 1) {
                $extra['noindex'] = true;
            }
        }
        return [
            'title' => $title,
            'title_exact' => $exactTitle,
            'description' => $desc,
            'canonical' => $canonical,
            'og_type' => $extra['type'] ?? 'website',
            'og_image' => $image !== '' ? absolute_url('uploads/' . ltrim($image, '/')) : null,
            'noindex' => (bool) ($extra['noindex'] ?? false),
            'alternates' => !empty($extra['noindex']) ? [] : $alternates,
            'jsonLd' => $extra['jsonLd'] ?? [],
        ];
    }

    /**
     * One URL per language (the default language has no parameter, the other one uses ?lang=),
     * plus x-default. Used for canonical, hreflang and the sitemap.
     *
     * @return array<string,string>
     */
    public static function alternates(string $path, int $page = 1): array
    {
        $base = absolute_url(ltrim($path, '/'));
        $default = default_locale();
        $out = [];
        foreach (\App\Core\Translator::SUPPORTED as $l) {
            $q = array_filter(['lang' => $l === $default ? null : $l, 'page' => $page > 1 ? $page : null]);
            $out[$l] = $base . ($q !== [] ? '?' . http_build_query($q) : '');
        }
        $out['x-default'] = $out[$default];
        return $out;
    }

    /** @return array<string,mixed> Organization + WebSite schema for the home page */
    public static function organization(): array
    {
        $same = array_values(array_filter([
            (string) setting('social_instagram', ''),
            (string) setting('social_facebook', ''),
            (string) setting('social_tiktok', ''),
        ]));
        $logo = (string) setting('company_logo', '');
        $org = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => (string) setting('company_name', 'BERRADI WALL ART'),
            'url' => absolute_url('/'),
        ];
        if ($logo !== '') {
            $org['logo'] = absolute_url('uploads/' . $logo);
        }
        if ($same !== []) {
            $org['sameAs'] = $same;
        }
        if ((string) setting('company_phone', '') !== '') {
            $org['contactPoint'] = ['@type' => 'ContactPoint', 'telephone' => (string) setting('company_phone'), 'contactType' => 'customer service', 'areaServed' => 'MA', 'availableLanguage' => ['French', 'Arabic']];
        }
        // local business (Google Maps / local results): only with a real address
        $type = (string) setting('seo_business_type', 'Store');
        $street = trim((string) setting('company_address', ''));
        if (in_array($type, self::BUSINESS_TYPES, true) && $type !== 'Organization' && $street !== '') {
            $org['@type'] = $type;
            $org['address'] = array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $street,
                'addressLocality' => (string) setting('company_city', ''),
                'addressCountry' => (string) setting('company_country', 'MA') ?: 'MA',
            ]);
            if ((string) setting('company_phone', '') !== '') {
                $org['telephone'] = (string) setting('company_phone');
            }
            if ($logo !== '') {
                $org['image'] = absolute_url('uploads/' . $logo);
            }
            $lat = (string) setting('seo_geo_lat', '');
            $lng = (string) setting('seo_geo_lng', '');
            if (is_numeric($lat) && is_numeric($lng)) {
                $org['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float) $lat, 'longitude' => (float) $lng];
            }
            $hours = array_values(array_filter(array_map('trim', explode(',', (string) setting('seo_opening_hours', '')))));
            if ($hours !== []) {
                $org['openingHours'] = $hours;
            }
            if ((string) setting('seo_price_range', '') !== '') {
                $org['priceRange'] = (string) setting('seo_price_range');
            }
        }
        return $org;
    }

    /**
     * schema.org Product with offers (AggregateOffer when variants exist) and real ratings only.
     *
     * @param array<string,mixed> $p product from CatalogService::product()
     * @return array<string,mixed>
     */
    public static function product(array $p): array
    {
        $images = array_map(fn ($i) => absolute_url('uploads/' . $i['path']), $p['images'] ?? []);
        if ($images === [] && !empty($p['main_image'])) {
            $images[] = absolute_url('uploads/' . $p['main_image']);
        }
        $url = absolute_url('product/' . $p['slug']);
        $availability = !empty($p['out_of_stock']) ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock';
        $currency = (string) setting('currency_code', 'MAD');
        $variants = $p['variants'] ?? [];
        if (count($variants) > 1) {
            $prices = array_map(fn ($v) => Money::toCents($v['effective_price']), $variants);
            $offers = [
                '@type' => 'AggregateOffer',
                'priceCurrency' => $currency,
                'lowPrice' => Money::fromCents(min($prices)),
                'highPrice' => Money::fromCents(max($prices)),
                'offerCount' => count($variants),
                'availability' => $availability,
                'url' => $url,
            ];
        } else {
            $offers = [
                '@type' => 'Offer',
                'priceCurrency' => $currency,
                'price' => $variants !== [] ? $variants[0]['effective_price'] : $p['display_price'],
                'availability' => $availability,
                'url' => $url,
                'itemCondition' => 'https://schema.org/NewCondition',
            ];
        }
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $p['title'],
            'sku' => $p['sku'],
            'description' => Str::limit(trim(strip_tags((string) ($p['description'] ?: $p['short_description']))), 500),
            'image' => $images,
            'brand' => ['@type' => 'Brand', 'name' => (string) setting('company_name', 'BERRADI WALL ART')],
            'offers' => $offers,
        ];
        if (!empty($p['category']['name'])) {
            $data['category'] = $p['category']['name'];
        }
        if ((int) ($p['review_count'] ?? 0) > 0) {
            $data['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => (string) $p['avg_rating'], 'reviewCount' => (int) $p['review_count']];
        }
        return $data;
    }

    /** @param list<array{0:string,1:?string}> $items label => url (null = current) @return array<string,mixed> */
    public static function breadcrumbs(array $items): array
    {
        $list = [];
        foreach ($items as $i => [$label, $url]) {
            $entry = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $label];
            if ($url !== null) {
                $entry['item'] = preg_match('#^https?://#', $url) ? $url : absolute_url(ltrim($url, '/'));
            }
            $list[] = $entry;
        }
        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list];
    }

    /** JSON for a <script type="application/ld+json"> block, safe against "</script>" injection. */
    public static function encode(array $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
}
