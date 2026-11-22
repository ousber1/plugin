@extends('layouts.app')
@section('title', __('طلب :ref', ['ref' => $order->reference]))
@section('noindex', '1')
@section('content')
<section class="page-hero"><div class="container"><h1>{{ __('طلب :ref', ['ref' => $order->reference]) }}</h1><p dir="auto">{{ $order->product->tr('title') }}</p></div></section>
<div class="container section--tight" style="max-width:760px">
    <div class="card card--pad overlap stack">
        <div class="row row--between"><span>{{ __('الحالة') }}</span><span class="badge {{ $order->status === 'paid' ? 'badge--green' : 'badge--amber' }}">{{ $order->statusLabel() }}</span></div>
        <div class="row row--between"><span>{{ __('المبلغ') }}</span><strong class="ltr">{{ number_format($order->amount_cents / 100, 2, '.', ' ') }} {{ $order->currency }}</strong></div>
        @if ($order->status === 'paid')
            <a class="btn btn--primary" href="{{ route('store.download', $order) }}"><x-icon name="download"/> {{ __('تحميل المنتج') }}</a>
        @elseif ($order->status === 'pending')
            <div class="alert alert--info"><x-icon name="info"/><div>
                <strong>{{ __('معلومات التحويل البنكي') }}</strong>
                <ul>
                    @if ($bank['name'])<li>{{ __('البنك:') }} {{ $bank['name'] }}</li>@endif
                    @if ($bank['holder'])<li>{{ __('صاحب الحساب:') }} {{ $bank['holder'] }}</li>@endif
                    @if ($bank['rib'])<li>RIB: <span class="ltr">{{ $bank['rib'] }}</span></li>@endif
                    <li>{{ __('اكتب رقم الطلب :ref في خانة سبب التحويل.', ['ref' => $order->reference]) }}</li>
                </ul>
                @if ($bank['instructions'])<p style="margin:8px 0 0">{{ $bank['instructions'] }}</p>@endif
                @unless ($bank['rib'])<p style="margin:8px 0 0">{{ __('سيتواصل معك فريقنا بمعلومات الدفع.') }}</p>@endunless
            </div></div>
        @endif
    </div>
</div>
@endsection
