@extends('layouts.admin')
@section('title', 'طلبات المتجر')
@section('content')
<div class="page-head"><h1>طلبات المتجر</h1></div>
<p class="muted small">هذه الطلبات خاصة بالمتجر الرقمي ومستقلة تمامًا عن التسجيل المجاني وطلبات الشهادات. أكّد الدفع بعد التحقق من التحويل البنكي.</p>
<form class="filters" method="GET"><div class="field"><label for="q">رقم الطلب</label><input class="input ltr" id="q" name="q" value="{{ request('q') }}"></div>
    <div class="field"><label for="status">الحالة</label><select class="select" id="status" name="status"><option value="">الكل</option><option value="pending" @selected(request('status') === 'pending')>بانتظار التحويل</option><option value="paid" @selected(request('status') === 'paid')>مدفوع</option><option value="cancelled" @selected(request('status') === 'cancelled')>ملغى</option></select></div>
    <button class="btn btn--primary">تصفية</button></form>
<div class="table-wrap"><table class="table"><thead><tr><th>الطلب</th><th>الزبون</th><th>المنتج</th><th>المبلغ</th><th>التحديث</th></tr></thead><tbody>
@forelse ($orders as $o)
    <tr><td class="ltr">{{ $o->reference }}<br><span class="small muted">{{ $o->created_at->format('Y-m-d') }}</span></td><td>{{ $o->user?->name }}<br><span class="small ltr muted">{{ $o->user?->email }}</span></td><td class="small">{{ $o->product?->tr('title') }}</td><td class="ltr">{{ number_format($o->amount_cents / 100, 2) }} {{ $o->currency }}</td>
        <td><form class="row" method="POST" action="{{ route('admin.orders.update', $o) }}" style="flex-wrap:nowrap">@csrf @method('PUT')
            <select class="select" name="status" aria-label="الحالة" style="min-height:36px">@foreach (['pending' => 'بانتظار التحويل', 'paid' => 'مدفوع', 'cancelled' => 'ملغى'] as $v => $l)<option value="{{ $v }}" @selected($o->status === $v)>{{ $l }}</option>@endforeach</select>
            <input class="input" name="transfer_reference" value="{{ $o->transfer_reference }}" placeholder="مرجع التحويل" aria-label="مرجع التحويل" style="min-height:36px;max-width:140px">
            <button class="btn btn--primary btn--sm">حفظ</button></form></td></tr>
@empty<tr><td colspan="5" class="muted" style="text-align:center;padding:24px">لا توجد طلبات.</td></tr>@endforelse
</tbody></table></div>
{{ $orders->links() }}
@endsection
