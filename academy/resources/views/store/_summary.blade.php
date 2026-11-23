{{-- Order summary. $items, $subtotal, $discount, $total, $coupon, $currency, optional $editable --}}
@php use App\Support\Shop; @endphp
<div class="card card--pad osum">
    <h2 class="card__title">{{ __('ملخص الطلب') }}</h2>
    <ul class="osum__items">
        @foreach ($items as $p)
            <li>
                <img src="{{ $p->coverUrl() }}" alt="" width="64" height="48">
                <div><a href="{{ $p->itemUrl() }}" dir="auto">{{ $p->tr('title') }}</a>@if ($p instanceof \App\Models\Course)<small class="muted">({{ __('دورة') }})</small>@endif @if ($p->billingLabel())<small class="muted">({{ __('اشتراك :p', ['p' => $p->billingLabel()]) }})</small>@endif</div>
                <strong>{{ Shop::money($p->price($currency), $currency) }}</strong>
                @if (! empty($editable))
                    <form method="POST" action="{{ route('store.cart.remove', $p->cartKey()) }}">@csrf @method('DELETE')
                        <button class="icon-btn icon-btn--sm" type="submit" aria-label="{{ __('حذف «:t» من السلة', ['t' => $p->tr('title')]) }}"><x-icon name="trash" size="14"/></button></form>
                @endif
            </li>
        @endforeach
    </ul>
    <dl class="osum__totals">
        <dt>{{ __('المجموع الفرعي') }}</dt><dd>{{ Shop::money($subtotal, $currency) }}</dd>
        @if ($discount)<dt>{{ __('الخصم') }} <span class="badge badge--green ltr">{{ $coupon?->code }}</span></dt><dd>− {{ Shop::money($discount, $currency) }}</dd>@endif
        <dt>{{ __('الضرائب') }}</dt><dd>{{ Shop::money(0, $currency) }}</dd>
    </dl>
    <div class="osum__total"><span>{{ __('المجموع') }}</span><strong>{{ Shop::money($total, $currency) }}</strong></div>
    @if (! empty($editable))
        <form method="POST" action="{{ route('store.coupon') }}" class="osum__coupon">@csrf
            @if ($coupon)
                <input type="hidden" name="remove" value="1"><span class="small">{{ __('رمز الخصم:') }} <strong class="ltr">{{ $coupon->code }}</strong></span>
                <button class="btn btn--ghost btn--sm" type="submit">{{ __('إزالة') }}</button>
            @else
                <label class="sr-only" for="code">{{ __('رمز الخصم') }}</label>
                <input class="input ltr" id="code" name="code" placeholder="{{ __('رمز الخصم') }}" maxlength="40" value="{{ old('code') }}">
                <button class="btn btn--outline btn--sm" type="submit">{{ __('تطبيق') }}</button>
            @endif
        </form>
        @error('code')<p class="error">{{ $message }}</p>@enderror
    @endif
    <ul class="osum__trust small muted">
        <li><x-icon name="shield" size="14"/> {{ __('معلوماتك محمية ولا نخزّن بياناتك البنكية') }}</li>
        <li><x-icon name="download" size="14"/> {{ __('تفعيل الطلب مباشرة بعد تأكيد الدفع') }}</li>
        <li><x-icon name="whatsapp" size="14"/> {{ __('دعم على واتساب إذا احتجت مساعدة') }}</li>
    </ul>
</div>
