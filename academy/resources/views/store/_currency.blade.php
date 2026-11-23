@php $cur = \App\Support\Shop::currency(); @endphp
@if (\App\Support\Settings::get('store_usd_enabled'))
    <div class="cur-switch" role="group" aria-label="{{ __('العملة') }}">
        <span class="cur-switch__label">{{ __('العملة') }}</span>
        <a href="{{ route('store.currency', 'MAD') }}" class="{{ $cur === 'MAD' ? 'is-active' : '' }}" @if ($cur === 'MAD') aria-current="true" @endif>{{ __('درهم') }}</a>
        <a href="{{ route('store.currency', 'USD') }}" class="{{ $cur === 'USD' ? 'is-active' : '' }}" @if ($cur === 'USD') aria-current="true" @endif>USD $</a>
    </div>
@endif
