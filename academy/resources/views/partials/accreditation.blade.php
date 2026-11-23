{{-- Accreditation band (Admin › الإعدادات › الاعتماد). Shown only when enabled and filled. --}}
@php
    use App\Support\Settings;
    $acTitle = Settings::tr('accreditation_title');
    $acText = Settings::tr('accreditation_text');
    $acLogo = Settings::get('accreditation_logo_path');
    $acDoc = Settings::get('accreditation_doc_path') ? \App\Support\Media::url(Settings::get('accreditation_doc_path')) : (Settings::get('accreditation_doc_url') ?: null);
    $locale = app()->getLocale();
    $pick = fn ($v) => is_array($v) ? (trim($v[$locale] ?? '') ?: (trim($v['fr'] ?? '') ?: trim($v['ar'] ?? ''))) : (string) $v;
    $acFacts = collect(Settings::get('accreditation_facts') ?: [])->filter(fn ($f) => $pick($f['value'] ?? '') !== '');
@endphp
@if (Settings::get('accreditation_enabled') && ($acTitle || $acText))
@unless (! empty($bare))<section class="section accr-wrap">
    <div class="container">@endunless
        <div class="accr">
            <div class="accr__text">
                @if (Settings::tr('accreditation_badge'))<span class="accr__badge"><x-icon name="shield" size="14"/> {{ Settings::tr('accreditation_badge') }}</span>@endif
                @if ($acTitle)<h2>{{ $acTitle }}</h2>@endif
                @if ($acText)<p>{{ $acText }}</p>@endif
                @if ($acFacts->isNotEmpty())
                    <div class="accr__facts">
                        @foreach ($acFacts as $f)
                            <div><span class="accr__ficon"><x-icon :name="$f['icon'] === 'hash' ? 'list' : $f['icon']" size="18"/></span><span><small>{{ $pick($f['label'] ?? '') }}</small><strong dir="auto">{{ $pick($f['value']) }}</strong></span></div>
                        @endforeach
                    </div>
                @endif
                <div class="accr__actions">
                    @if ($acDoc)<a class="btn btn--amber" href="{{ $acDoc }}" target="_blank" rel="noopener"><x-icon name="award" size="18"/> {{ Settings::tr('accreditation_doc_label') ?: __('اطّلع على شهادة الاعتماد') }}</a>@endif
                    @if (Settings::get('accreditation_verify_button') && Settings::feature('verification') && ! request()->routeIs('verify.*'))<a class="btn btn--glass" href="{{ route('verify.index') }}"><x-icon name="shield" size="18"/> {{ __('تحقق من صحة شهادة') }}</a>@endif
                </div>
            </div>
            @if ($acLogo)
                <div class="accr__logo"><img src="{{ \App\Support\Media::url($acLogo) }}" alt="{{ $acTitle }}" loading="lazy"></div>
            @endif
        </div>
@unless (! empty($bare))    </div>
</section>@endunless
@endif
