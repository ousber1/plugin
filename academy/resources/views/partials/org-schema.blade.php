@php
    use App\Support\Settings;
    $sameAs = array_values(array_filter(Settings::get('social') ?: []));
    $org = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'EducationalOrganization',
        'name' => Settings::siteName(),
        'url' => route('home'),
        'logo' => Settings::get('logo_path') ? \App\Support\Media::url(Settings::get('logo_path')) : asset('favicon.svg'),
        'description' => Settings::tr('seo_description'),
        'email' => Settings::get('contact_email'),
        'telephone' => Settings::get('contact_phone'),
        'address' => ['@type' => 'PostalAddress', 'addressLocality' => Settings::tr('contact_address'), 'addressCountry' => 'MA'],
        'sameAs' => $sameAs ?: null,
        'department' => \App\Models\Center::hasPublic() ? \App\Models\Center::active()->get()->map(fn ($c) => array_filter([
            '@type' => 'EducationalOrganization', 'name' => $c->tr('name'), 'url' => route('centers.show', $c), 'telephone' => $c->phone,
            'address' => ['@type' => 'PostalAddress', 'streetAddress' => $c->tr('address'), 'addressLocality' => $c->tr('city'), 'addressCountry' => 'MA'],
        ]))->values()->all() : null,
    ]);
@endphp
<script type="application/ld+json">{!! json_encode($org, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => Settings::siteName(), 'url' => route('home'), 'potentialAction' => ['@type' => 'SearchAction', 'target' => route('courses.index').'?q={search_term_string}', 'query-input' => 'required name=search_term_string']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
