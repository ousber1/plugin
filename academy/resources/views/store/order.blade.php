@extends('layouts.app')
@section('title', __('الطلب :ref', ['ref' => $order->reference]))
@section('noindex', '1')
@section('content')
@php
    $paid = $order->status === 'paid';
    $items = $order->items->isNotEmpty() ? $order->items : collect();
@endphp
<section class="store-hero store-hero--sm"><div class="container store-hero__in">
    <h1>{{ $paid ? __('طلبك جاهز!') : __('شكرًا! تم تسجيل طلبك') }}</h1>
    <p>{{ __('رقم الطلب:') }} <strong class="ltr">{{ $order->reference }}</strong> · <span class="badge badge--{{ $order->statusColor() }}">{{ $order->statusLabel() }}</span></p>
    @include('store._steps', ['step' => 3])
</div></section>
<div class="container section--tight">
    <div class="checkout-grid overlap">
        <div class="stack">
            @if ($paid)
                <div class="card card--pad">
                    <h2 class="card__title"><x-icon name="download" size="20"/> {{ __('ملفاتك ومفاتيح الترخيص') }}</h2>
                    @foreach ($items as $it)
                        <div class="lic">
                            <div><strong dir="auto">{{ $it->title }}</strong>
                                @if ($it->license_key)<div class="lic__key"><code class="ltr">{{ $it->license_key }}</code><button class="icon-btn icon-btn--sm" type="button" data-copy="{{ $it->license_key }}" data-copied="{{ __('تم النسخ') }}" aria-label="{{ __('نسخ المفتاح') }}"><x-icon name="copy" size="14"/></button></div>@endif
                                @if ($it->expires_at)<small class="muted">{{ __('صالح حتى :d', ['d' => $it->expires_at->translatedFormat('j F Y')]) }}</small>@endif</div>
                            @if ($it->product?->file_path)<a class="btn btn--primary btn--sm" href="{{ route('store.download', [$order, $it]) }}"><x-icon name="download" size="16"/> {{ __('تحميل') }}</a>@endif
                        </div>
                    @endforeach
                    @if ($items->isEmpty() && $order->product?->file_path)
                        <a class="btn btn--primary" href="{{ route('store.download', $order) }}"><x-icon name="download" size="16"/> {{ __('تحميل') }}</a>
                    @endif
                </div>
            @elseif ($order->status === 'cancelled')
                <div class="alert alert--error"><x-icon name="alert"/> <span>{{ __('أُلغي هذا الطلب. تواصل معنا إن كان ذلك خطأ.') }}</span></div>
            @else
                <div class="card card--pad">
                    <h2 class="card__title"><span class="step-dot">1</span> {{ __('ادفع المبلغ:') }} <span class="ltr" style="color:var(--indigo-700)">{{ $order->totalLabel() }}</span></h2>
                    <p class="small muted">{{ __('طريقة الدفع:') }} <strong>{{ $order->methodLabel() }}</strong>. {{ __('اكتب رقم الطلب :ref في خانة السبب/الملاحظة إن أمكن.', ['ref' => $order->reference]) }}</p>
                    @switch($order->payment_method)
                        @case('bank')
                            @forelse ($banks as $b)
                                <dl class="pinfo pay-box">
                                    @if (! empty($b['bank']))<dt>{{ __('البنك') }}</dt><dd>{{ $b['bank'] }}</dd>@endif
                                    @if (! empty($b['holder']))<dt>{{ __('صاحب الحساب') }}</dt><dd>{{ $b['holder'] }}</dd>@endif
                                    <dt>RIB</dt><dd class="row" style="gap:6px"><code class="ltr">{{ $b['rib'] }}</code><button class="icon-btn icon-btn--sm" type="button" data-copy="{{ $b['rib'] }}" data-copied="{{ __('تم النسخ') }}" aria-label="{{ __('نسخ RIB') }}"><x-icon name="copy" size="14"/></button></dd>
                                </dl>
                            @empty
                                <p class="muted">{{ __('سنرسل لك معلومات الحساب البنكي عبر واتساب.') }}</p>
                            @endforelse
                            @if ($instructions)<p class="small">{{ $instructions }}</p>@endif
                            @break
                        @case('agency')
                            <dl class="pinfo pay-box">
                                <dt>{{ __('الوكالات') }}</dt><dd>{{ $agency['names'] }}</dd>
                                @if ($agency['receiver'])<dt>{{ __('اسم المستفيد') }}</dt><dd>{{ $agency['receiver'] }}</dd>@endif
                                @if ($agency['city'])<dt>{{ __('المدينة') }}</dt><dd>{{ $agency['city'] }}</dd>@endif
                                @if ($agency['phone'])<dt>{{ __('هاتف المستفيد') }}</dt><dd class="ltr">{{ $agency['phone'] }}</dd>@endif
                            </dl>
                            @unless ($agency['receiver'])<p class="muted small">{{ __('سنرسل لك اسم المستفيد عبر واتساب.') }}</p>@endunless
                            @break
                        @case('paypal')
                            @if ($paypal)<a class="btn btn--primary btn--lg" href="{{ $paypal }}" target="_blank" rel="noopener"><x-icon name="globe" size="18"/> {{ __('ادفع عبر PayPal') }} — {{ $order->totalLabel() }}</a>
                            @else<p class="muted">{{ __('سنرسل لك رابط الدفع عبر واتساب أو البريد.') }}</p>@endif
                            @break
                    @endswitch
                    @if ($whatsapp)
                        <a class="btn btn--whatsapp {{ $order->payment_method === 'whatsapp' ? 'btn--lg' : 'btn--sm' }}" href="{{ $whatsapp }}" target="_blank" rel="noopener" style="margin-top:12px"><x-icon name="whatsapp" size="18"/> {{ $order->payment_method === 'whatsapp' ? __('أكمل الدفع عبر واتساب') : __('تحتاج مساعدة؟ راسلنا') }}</a>
                    @endif
                </div>
                @if ($order->payment_method !== 'whatsapp' || $order->receipt_path)
                    <form class="card card--pad" method="POST" action="{{ route('store.receipt', $order) }}" enctype="multipart/form-data">@csrf
                        <h2 class="card__title"><span class="step-dot">2</span> {{ __('ارفع وصل الدفع') }}</h2>
                        @if ($order->status === 'review')<div class="alert alert--info small"><x-icon name="info"/> <span>{{ __('استلمنا وصلك وهو قيد المراجعة. يمكنك رفع وصل آخر إن لزم.') }}</span></div>@endif
                        <div class="form-grid">
                            <div class="field"><label for="receipt">{{ __('صورة أو PDF للوصل') }} <span class="req">*</span></label><input class="input" type="file" id="receipt" name="receipt" accept="image/*,application/pdf" required></div>
                            <div class="field"><label for="transfer_reference">{{ __('رقم العملية (اختياري)') }}</label><input class="input ltr" id="transfer_reference" name="transfer_reference" maxlength="120" value="{{ $order->transfer_reference }}"></div>
                        </div>
                        <button class="btn btn--primary" type="submit"><x-icon name="upload" size="18"/> {{ __('إرسال الوصل') }}</button>
                    </form>
                @endif
                <p class="small muted"><x-icon name="info" size="14"/> {{ __('بعد تأكيد الدفع تظهر هنا روابط التحميل ومفاتيح الترخيص، وتجدها دائمًا في «حسابي في المتجر».') }}</p>
            @endif
        </div>
        <aside class="card card--pad osum">
            <h2 class="card__title">{{ __('ملخص الطلب') }}</h2>
            <ul class="osum__items">
                @foreach ($items as $it)<li><div dir="auto">{{ $it->title }}</div><strong>{{ \App\Support\Shop::money($it->price_cents, $order->currency) }}</strong></li>@endforeach
            </ul>
            <dl class="osum__totals">
                @if ($order->discount_cents)<dt>{{ __('الخصم') }}</dt><dd>− {{ \App\Support\Shop::money($order->discount_cents, $order->currency) }}</dd>@endif
            </dl>
            <div class="osum__total"><span>{{ __('المجموع') }}</span><strong>{{ $order->totalLabel() }}</strong></div>
            <a class="btn btn--outline btn--block" href="{{ route('store.account') }}" style="margin-top:12px"><x-icon name="user" size="16"/> {{ __('حسابي في المتجر') }}</a>
        </aside>
    </div>
</div>
@endsection
