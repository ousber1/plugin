@extends('layouts.app')
@section('title', __('إتمام الطلب'))
@section('noindex', '1')
@section('content')
@php $first = array_key_first($methods); @endphp
<section class="store-hero store-hero--sm"><div class="container store-hero__in">
    <h1>{{ __('إتمام الطلب') }}</h1>
    <p>{{ __('أدخل معلوماتك واختر طريقة الدفع لإتمام طلبك.') }}</p>
    @include('store._steps', ['step' => 2])
</div></section>
<div class="container section--tight">
    <div class="card card--pad overlap cur-bar">
        @include('store._currency')
        <p class="small muted" style="margin:0">{{ $currency === 'USD' ? __('الدفع بالدولار: PayPal أو البطاقة، أو تواصل معنا عبر واتساب.') : __('الدفع بالدرهم: تحويل بنكي أو وكالات تحويل الأموال أو واتساب. للدفع بالبطاقة أو PayPal اختر الدولار.') }}</p>
    </div>
    <div class="checkout-grid">
        <form class="stack" method="POST" action="{{ route('store.place') }}" id="checkout-form">
            @csrf
            @include('partials.form-errors')
            <section class="card card--pad">
                <h2 class="card__title"><span class="step-dot">1</span> {{ __('معلوماتك') }}</h2>
                <div class="field"><label for="name">{{ __('الاسم الكامل') }} <span class="req">*</span></label><input class="input" id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="120" autocomplete="name" dir="auto"></div>
                <div class="form-grid">
                    <div class="field"><label for="phone">{{ __('رقم الهاتف (واتساب)') }} <span class="req">*</span></label><input class="input input-ltr" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" required inputmode="tel" autocomplete="tel" placeholder="06XXXXXXXX"></div>
                    <div class="field"><label for="email">{{ __('البريد الإلكتروني') }} <span class="req">*</span></label><input class="input input-ltr" type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="email"></div>
                </div>
                <div class="field"><label for="customer_note">{{ __('ملاحظة (اختياري)') }}</label><textarea class="textarea" id="customer_note" name="customer_note" rows="2" maxlength="1000" dir="auto" placeholder="{{ __('مثال: رابط موقعك لتفعيل الترخيص') }}">{{ old('customer_note') }}</textarea></div>
            </section>
            <section class="card card--pad">
                <h2 class="card__title"><span class="step-dot">2</span> {{ __('طريقة الدفع') }}</h2>
                @if (! $methods)
                    <div class="alert alert--warning"><x-icon name="alert"/> <span>{{ __('لا توجد طريقة دفع متاحة لهذه العملة حاليًا. غيّر العملة أو تواصل معنا.') }}</span></div>
                @else
                    <fieldset class="paym" style="border:0;padding:0;margin:0">
                        <legend class="sr-only">{{ __('طريقة الدفع') }}</legend>
                        @foreach ($methods as $key => $m)
                            <label class="paym__opt">
                                <input type="radio" name="payment_method" value="{{ $key }}" @checked(old('payment_method', $first) === $key) required>
                                <span class="paym__icon paym__icon--{{ $key }}"><x-icon :name="$m['icon']" size="20"/></span>
                                <span class="paym__text"><strong>{{ $m['label'] }}</strong><small>{{ $m['desc'] }}</small></span>
                                <span class="paym__note">{{ $m['note'] }}</span>
                            </label>
                        @endforeach
                    </fieldset>
                    <label class="check field" style="margin-top:12px"><input type="checkbox" name="accept" value="1" required @checked(old('accept'))> <span class="small">{{ __('أوافق على شروط الاستخدام وسياسة الخصوصية، وأفهم أن المنتجات رقمية تُسلّم بعد تأكيد الدفع.') }}</span></label>
                    <button class="btn btn--primary btn--lg btn--block" type="submit">{{ __('تأكيد الطلب') }} — {{ \App\Support\Shop::money($total, $currency) }}</button>
                    <p class="small muted" style="text-align:center;margin:8px 0 0"><x-icon name="lock" size="14"/> {{ __('بالضغط على «تأكيد الطلب» تُسجَّل طلبيتك وتظهر لك تعليمات الدفع.') }}</p>
                @endif
            </section>
        </form>
        <aside>@include('store._summary')</aside>
    </div>
</div>
@endsection
