@extends('layouts.admin')
@section('title', 'طلبات المتجر')
@section('content')
@php use App\Support\Shop; @endphp
<div class="page-head"><h1>طلبات المتجر</h1><a class="btn btn--ghost" href="{{ route('admin.coupons.index') }}"><x-icon name="receipt" size="18"/> رموز الخصم</a></div>
<div class="kpis" style="margin-bottom:16px">
    <div class="kpi"><strong class="ltr">{{ Shop::money($revenue['month'], 'MAD') }}</strong><span>إيرادات هذا الشهر</span></div>
    <div class="kpi"><strong class="ltr">{{ Shop::money($revenue['MAD'], 'MAD') }}</strong><span>إجمالي الإيرادات بالدرهم</span></div>
    @if ($revenue['USD'])<div class="kpi"><strong class="ltr">{{ Shop::money($revenue['USD'], 'USD') }}</strong><span>إجمالي الإيرادات بالدولار</span></div>@endif
    <div class="kpi"><strong>{{ ($counts['review'] ?? 0) }}</strong><span>وصل بانتظار المراجعة</span></div>
</div>
<nav class="tabs">
    <a href="{{ route('admin.orders.index') }}" class="{{ ! request('status') ? 'is-active' : '' }}">الكل</a>
    @foreach (['review' => 'قيد المراجعة', 'pending' => 'بانتظار الدفع', 'paid' => 'مدفوع', 'cancelled' => 'ملغى'] as $k => $l)
        <a href="{{ route('admin.orders.index', ['status' => $k]) }}" class="{{ request('status') === $k ? 'is-active' : '' }}">{{ $l }} ({{ $counts[$k] ?? 0 }})</a>
    @endforeach
</nav>
<form class="filters" method="GET">
    @if (request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <div class="field"><label for="method">طريقة الدفع</label><select class="select" id="method" name="method"><option value="">الكل</option>@foreach (['bank' => 'تحويل بنكي', 'agency' => 'وكالات التحويل', 'whatsapp' => 'واتساب', 'paypal' => 'PayPal'] as $k => $l)<option value="{{ $k }}" @selected(request('method') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="field" style="flex:2"><label for="q">بحث</label><input class="input" id="q" name="q" value="{{ request('q') }}" placeholder="رقم الطلب، الاسم، الهاتف أو البريد"></div>
    <button class="btn btn--primary">بحث</button>
</form>
<div class="table-wrap"><table class="table"><thead><tr><th>المرجع</th><th>الزبون</th><th>المنتجات</th><th>المبلغ</th><th>الدفع</th><th>الحالة</th><th>التاريخ</th><th></th></tr></thead><tbody>
@forelse ($orders as $o)
    <tr><td class="ltr"><strong>{{ $o->reference }}</strong></td>
        <td>{{ $o->name ?: $o->user?->name }}<br><small class="muted ltr">{{ $o->phone ?: $o->user?->email }}</small></td>
        <td class="small" dir="auto">{{ $o->items->pluck('title')->implode('، ') ?: $o->product?->tr('title') }}</td>
        <td class="ltr">{{ $o->totalLabel() }}</td>
        <td class="small">{{ $o->methodLabel() }}@if ($o->receipt_path)<br><span class="badge badge--blue">وصل مرفوع</span>@endif</td>
        <td><span class="badge badge--{{ $o->statusColor() }}">{{ $o->statusLabel() }}</span></td>
        <td class="small">{{ $o->created_at->format('Y-m-d') }}</td>
        <td><a class="btn btn--outline btn--sm" href="{{ route('admin.orders.show', $o) }}">معالجة</a></td></tr>
@empty<tr><td colspan="8" class="muted" style="text-align:center;padding:24px">لا توجد طلبات.</td></tr>@endforelse
</tbody></table></div>
{{ $orders->links() }}
@endsection
