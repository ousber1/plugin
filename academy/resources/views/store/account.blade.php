@extends('layouts.app')
@section('title', __('حسابي في المتجر'))
@section('noindex', '1')
@section('content')
<section class="store-hero store-hero--sm"><div class="container store-hero__in">
    <h1>{{ __('حسابي في المتجر') }}</h1>
    <p>{{ __('مشترياتك، ملفات التحميل ومفاتيح الترخيص في مكان واحد.') }}</p>
</div></section>
<div class="container section--tight">
    <div class="stack overlap">
        <section class="card card--pad">
            <h2 class="card__title"><x-icon name="download" size="20"/> {{ __('منتجاتي') }}</h2>
            @forelse ($licenses as $it)
                @php $active = $it->isActive(); $soon = $it->expires_at && $active && $it->expires_at->lt(now()->addDays(14)); @endphp
                <div class="lic">
                    <img src="{{ $it->product?->coverUrl() }}" alt="" width="72" height="48" class="lic__img">
                    <div style="flex:1;min-width:0"><strong dir="auto">{{ $it->product?->tr('title') ?? $it->title }}</strong>
                        <div class="lic__key"><code class="ltr">{{ $it->license_key }}</code><button class="icon-btn icon-btn--sm" type="button" data-copy="{{ $it->license_key }}" data-copied="{{ __('تم النسخ') }}" aria-label="{{ __('نسخ المفتاح') }}"><x-icon name="copy" size="14"/></button></div>
                        @if ($it->expires_at)<small class="{{ $active ? ($soon ? 'text-amber' : 'muted') : 'error' }}">{{ $active ? __('صالح حتى :d', ['d' => $it->expires_at->translatedFormat('j F Y')]) : __('انتهى في :d', ['d' => $it->expires_at->translatedFormat('j F Y')]) }}</small>
                        @else<small class="muted">{{ __('ترخيص دائم') }}</small>@endif
                    </div>
                    <div class="row" style="gap:6px">
                        @if ($active && $it->product?->file_path)<a class="btn btn--primary btn--sm" href="{{ route('store.download', [$it->order, $it]) }}"><x-icon name="download" size="16"/> {{ __('تحميل') }}</a>@endif
                        @if ($it->product && ($soon || ! $active))
                            <form method="POST" action="{{ route('store.cart.add', $it->product) }}">@csrf<input type="hidden" name="buy_now" value="1"><button class="btn btn--amber btn--sm" type="submit">{{ __('جدّد') }}</button></form>
                        @endif
                    </div>
                </div>
            @empty
                <p class="muted">{{ __('لا توجد مشتريات مفعّلة بعد.') }} <a href="{{ route('store.index') }}">{{ __('تصفّح المتجر') }}</a></p>
            @endforelse
        </section>
        <section class="card card--pad">
            <h2 class="card__title"><x-icon name="receipt" size="20"/> {{ __('طلباتي') }}</h2>
            @if ($orders->isEmpty())
                <p class="muted">{{ __('لا توجد طلبات بعد.') }}</p>
            @else
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>{{ __('رقم الطلب') }}</th><th>{{ __('المنتجات') }}</th><th>{{ __('المبلغ') }}</th><th>{{ __('الحالة') }}</th><th>{{ __('التاريخ') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($orders as $o)
                            <tr><td class="ltr"><strong>{{ $o->reference }}</strong></td>
                                <td class="small" dir="auto">{{ $o->items->pluck('title')->implode('، ') ?: $o->product?->tr('title') }}</td>
                                <td class="ltr">{{ $o->totalLabel() }}</td>
                                <td><span class="badge badge--{{ $o->statusColor() }}">{{ $o->statusLabel() }}</span></td>
                                <td class="small">{{ $o->created_at->format('Y-m-d') }}</td>
                                <td><a class="btn btn--ghost btn--sm" href="{{ route('store.order', $o) }}">{{ in_array($o->status, ['pending', 'review']) ? __('الدفع') : __('عرض') }}</a></td></tr>
                        @endforeach
                    </tbody>
                </table></div>
            @endif
        </section>
    </div>
</div>
@endsection
