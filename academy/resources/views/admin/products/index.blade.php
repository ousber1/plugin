@extends('layouts.admin')
@section('title', 'المنتجات')
@section('content')
@php use App\Support\Shop; @endphp
<div class="page-head"><h1>منتجات المتجر</h1>
    <div class="row">
        <a class="btn btn--ghost" href="{{ route('admin.categories.index', ['type' => 'product']) }}"><x-icon name="layers" size="18"/> التصنيفات</a>
        <a class="btn btn--ghost" href="{{ route('admin.settings.edit', 'store') }}"><x-icon name="settings" size="18"/> إعدادات الدفع</a>
        <a class="btn btn--primary" href="{{ route('admin.products.create') }}"><x-icon name="plus" size="18"/> منتج جديد</a>
    </div></div>
@unless (\App\Support\Settings::feature('store'))<div class="alert alert--info"><x-icon name="info"/> <span>المتجر معطّل حاليًا ولا يظهر للزوار. فعّله من <a href="{{ route('admin.settings.edit', 'features') }}">الإعدادات ← الوحدات</a>.</span></div>@endunless
<div class="table-wrap"><table class="table"><thead><tr><th></th><th>المنتج</th><th>التصنيف</th><th>السعر</th><th>المبيعات</th><th>الإيرادات (درهم)</th><th>الحالة</th><th></th></tr></thead><tbody>
@forelse ($items as $p)
    <tr><td><img src="{{ $p->coverUrl() }}" alt="" style="width:64px;height:40px;object-fit:cover;border-radius:6px"></td>
        <td dir="auto"><strong>{{ $p->tr('title') }}</strong>@if ($p->is_featured) <span class="badge badge--amber">مميز</span>@endif @if ($p->version)<br><small class="muted ltr">v{{ $p->version }}</small>@endif</td>
        <td class="small">{{ $p->category?->tr('name') ?? '—' }}</td>
        <td class="ltr small">{{ Shop::money($p->price_cents, 'MAD') }}@if ($p->billingLabel()) / {{ $p->billingLabel() }}@endif<br><span class="muted">{{ Shop::money($p->price('USD'), 'USD') }}</span></td>
        <td>{{ $p->sales_count }}</td><td class="ltr">{{ number_format(($p->revenue ?? 0) / 100, 0, '.', ' ') }}</td>
        <td><x-admin.status :status="$p->status"/></td>
        <td class="row">@if ($p->status === 'published')<a class="btn btn--ghost btn--sm" href="{{ route('store.show', $p) }}" target="_blank">عرض</a>@endif<a class="btn btn--ghost btn--sm" href="{{ route('admin.products.edit', $p) }}">تعديل</a><form method="POST" action="{{ route('admin.products.destroy', $p) }}" data-confirm="حذف المنتج؟">@csrf @method('DELETE')<button class="btn btn--ghost btn--sm">حذف</button></form></td></tr>
@empty<tr><td colspan="8" class="muted" style="text-align:center;padding:24px">لا توجد منتجات.</td></tr>@endforelse
</tbody></table></div>
{{ $items->links() }}
@endsection
