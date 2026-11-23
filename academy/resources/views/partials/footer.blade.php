{{-- Site footer, configured in Admin › الهيدر والتذييل. --}}
@php
    use App\Support\Settings;
    use App\Support\SiteChrome;
    $columns = SiteChrome::footerColumns();
    $social = Settings::get('footer_show_social') ? array_filter(Settings::get('social') ?: []) : [];
    $ctaTitle = Settings::get('footer_cta_enabled') ? Settings::tr('footer_cta_title') : '';
    $bottomLinks = collect(Settings::get('footer_bottom_links') ?: [])->filter(fn ($l) => ! empty($l['url']));
    $style = Settings::get('footer_style');
@endphp
<footer class="site-footer">
    <div class="container">
        @if ($ctaTitle)
            <div class="footer-cta">
                <div class="cta-band">
                    <div>
                        <h2>{{ $ctaTitle }}</h2>
                        @if (Settings::tr('footer_cta_text'))<p>{{ Settings::tr('footer_cta_text') }}</p>@endif
                    </div>
                    @if (Settings::tr('footer_cta_button'))
                        <a class="btn btn--white btn--lg" href="{{ Settings::get('footer_cta_url') ?: route('courses.index') }}">{{ Settings::tr('footer_cta_button') }} <x-icon name="arrow" size="18" style="transform:scaleX({{ $dir === 'rtl' ? -1 : 1 }})"/></a>
                    @endif
                </div>
            </div>
        @endif
        <div class="footer-grid footer-grid--{{ count($columns) + 1 }}">
            <div>
                @if (Settings::get('footer_show_logo') ?? true)
                    @if (Settings::get('footer_logo_path'))
                        <a href="{{ route('home') }}" class="footer-logo"><img src="{{ \App\Support\Media::url(Settings::get('footer_logo_path')) }}" alt="{{ $siteName }}" style="height:{{ (int) Settings::get('footer_logo_height', 44) }}px;width:auto"></a>
                    @else
                        <a href="{{ route('home') }}" class="footer-logo" style="--logo-h:{{ (int) Settings::get('footer_logo_height', 44) }}px"><x-logo :light="$style !== 'light'"/></a>
                    @endif
                @endif
                @if (Settings::tr('footer_about'))<p style="margin-top:14px;max-width:46ch">{{ Settings::tr('footer_about') }}</p>@endif
                @if (Settings::get('footer_show_contact') && ! collect($columns)->contains('type', 'contact'))
                    @include('partials.footer-contact')
                @endif
                @if ($social)
                    <div class="socials">
                        @foreach ($social as $network => $url)
                            <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ ucfirst($network) }}"><x-icon :name="$network === 'x' ? 'x-social' : $network" size="18"/></a>
                        @endforeach
                    </div>
                @endif
            </div>
            @foreach ($columns as $col)
                <div>
                    <h2>{{ SiteChrome::label(['label' => $col['title'] ?? '']) ?: SiteChrome::COLUMN_TYPES[$col['type']] }}</h2>
                    @switch($col['type'])
                        @case('contact')
                            @include('partials.footer-contact')
                            @break
                        @case('categories')
                            <ul>
                                @foreach (\App\Models\Category::ofType('course')->where('is_active', true)->limit(6)->get() as $cat)
                                    <li><a href="{{ route('courses.index', ['category' => $cat->slug]) }}">{{ $cat->tr('name') }}</a></li>
                                @endforeach
                                <li><a href="{{ route('courses.index') }}" class="footer-more">{{ __('كل الدورات') }}</a></li>
                            </ul>
                            @break
                        @case('pages')
                            <ul>
                                <li><a href="{{ route('about') }}">{{ __('من نحن') }}</a></li>
                                <li><a href="{{ route('contact') }}">{{ __('تواصل معنا') }}</a></li>
                                @if ($features['verification'] ?? false)<li><a href="{{ route('verify.index') }}">{{ __('التحقق من الشهادة') }}</a></li>@endif
                                @foreach (\App\Models\Page::where('status', 'published')->where('show_in_footer', true)->get() as $p)
                                    <li><a href="{{ route('pages.show', $p) }}">{{ $p->tr('title') }}</a></li>
                                @endforeach
                            </ul>
                            @break
                        @case('services')
                            <ul>
                                @if ($features['library'] ?? false)<li><a href="{{ route('library.index') }}">{{ __('المكتبة') }}</a></li>@endif
                                @if ($features['blog'] ?? false)<li><a href="{{ route('blog.index') }}">{{ __('المدونة') }}</a></li>@endif
                                @if ($features['tools'] ?? false)
                                    @foreach (\App\Models\Tool::where('is_enabled', true)->orderBy('sort_order')->limit(4)->get() as $t)
                                        <li><a href="{{ route('tools.show', $t) }}">{{ $t->tr('name') }}</a></li>
                                    @endforeach
                                @endif
                                @if ($features['store'] ?? false)<li><a href="{{ route('store.index') }}">{{ __('المتجر') }}</a></li>@endif
                            </ul>
                            @break
                        @case('centers')
                            <ul>
                                @foreach (\App\Models\Center::active()->limit(6)->get() as $center)
                                    <li><a href="{{ route('centers.show', $center) }}"><x-icon name="map-pin" size="14"/> {{ $center->tr('name') }} <small class="footer-muted">— {{ $center->tr('city') }}</small></a></li>
                                @endforeach
                                <li><a href="{{ route('centers.index') }}" class="footer-more">{{ __('كل المراكز') }}</a></li>
                            </ul>
                            @break
                        @default
                            <ul>
                                @foreach ($col['links'] ?? [] as $link)
                                    @continue(empty($link['url']))
                                    <li><a href="{{ $link['url'] }}" @if (str_starts_with($link['url'], 'http')) target="_blank" rel="noopener" @endif>{{ SiteChrome::label($link) }}</a></li>
                                @endforeach
                            </ul>
                    @endswitch
                </div>
            @endforeach
        </div>
        <div class="footer-bottom">
            <span>{{ SiteChrome::copyright() }}</span>
            @if ($bottomLinks->isNotEmpty())
                <nav class="footer-bottom__links" aria-label="{{ __('روابط التذييل') }}">
                    @foreach ($bottomLinks as $link)<a href="{{ $link['url'] }}">{{ SiteChrome::label($link) }}</a>@endforeach
                </nav>
            @endif
            @if (Settings::get('footer_show_back_top'))
                <a href="#main" class="btn btn--glass btn--sm"><x-icon name="arrow-up" size="16"/> {{ __('إلى الأعلى') }}</a>
            @endif
        </div>
    </div>
</footer>
