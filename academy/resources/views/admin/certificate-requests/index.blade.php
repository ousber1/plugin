@extends('layouts.admin')
@section('title', 'طلبات الشهادات')
@section('content')
<div class="page-head"><h1>طلبات الشهادات الورقية</h1></div>
<nav class="tabs">
    <a href="{{ route('admin.certificate-requests.index') }}" class="{{ !request('status') ? 'is-active' : '' }}">الكل ({{ $counts->sum() }})</a>
    @foreach (\App\Enums\CertificateStatus::cases() as $st)
        <a href="{{ route('admin.certificate-requests.index', ['status' => $st->value]) }}" class="{{ request('status') === $st->value ? 'is-active' : '' }}">{{ $st->label() }} ({{ $counts[$st->value] ?? 0 }})</a>
    @endforeach
</nav>
<form class="filters" method="GET">
    @if (request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <div class="field" style="flex:2"><label for="q">بحث</label><input class="input" id="q" name="q" value="{{ request('q') }}" placeholder="رقم المرجع، اسم الطالب، البريد، الهاتف أو رقم التتبع"></div>
    <button class="btn btn--primary">بحث</button>
</form>
<div class="table-wrap"><table class="table"><thead><tr><th>المرجع</th><th>الطالب</th><th>الدورة</th><th>الحالة</th><th>التتبع</th><th>التاريخ</th><th></th></tr></thead><tbody>
@forelse ($requests as $r)
    <tr><td class="ltr"><strong>{{ $r->reference }}</strong></td><td>{{ $r->user?->name }}<br><span class="small ltr muted">{{ $r->user?->phone ?: $r->user?->email }}</span></td>
        <td class="small" dir="auto">{{ $r->course?->tr('title') }}</td><td><span class="badge badge--{{ $r->status->color() }}">{{ $r->status->label() }}</span></td>
        <td class="small ltr">{{ $r->tracking_number ?: '—' }}</td><td class="small">{{ $r->created_at->format('Y-m-d') }}</td>
        <td><a class="btn btn--outline btn--sm" href="{{ route('admin.certificate-requests.show', $r) }}">معالجة</a></td></tr>
@empty<tr><td colspan="7" class="muted" style="text-align:center;padding:24px">لا توجد طلبات.</td></tr>@endforelse
</tbody></table></div>
{{ $requests->links() }}
@endsection
