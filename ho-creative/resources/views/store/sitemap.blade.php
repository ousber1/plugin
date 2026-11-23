{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach($urls as $u)
  <url>
    <loc>{{ $u['loc'] }}</loc>
    <xhtml:link rel="alternate" hreflang="fr" href="{{ $u['loc'] }}?lang=fr"/>
    <xhtml:link rel="alternate" hreflang="ar" href="{{ $u['loc'] }}?lang=ar"/>
    @if($u['lastmod'])<lastmod>{{ \Illuminate\Support\Carbon::parse($u['lastmod'])->toAtomString() }}</lastmod>@endif
  </url>
@endforeach
</urlset>
