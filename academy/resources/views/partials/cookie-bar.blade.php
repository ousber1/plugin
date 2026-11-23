@if (\App\Support\Settings::get('cookie_banner'))
    <div class="cookie-bar" id="cookie-bar" role="region" aria-label="{{ __('ملفات تعريف الارتباط') }}" hidden>
        <p>{{ \App\Support\Settings::tr('cookie_text') }} <a href="{{ route('privacy') }}">{{ __('سياسة الخصوصية') }}</a></p>
        <button class="btn btn--amber btn--sm" type="button" data-cookie-ok>{{ __('موافق') }}</button>
    </div>
@endif
