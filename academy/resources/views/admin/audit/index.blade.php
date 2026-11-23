@extends('layouts.admin')
@section('title', 'سجل العمليات')
@section('content')
<div class="page-head"><h1>سجل العمليات المهمة</h1></div>
<form class="filters" method="GET"><div class="field"><label for="action">العملية</label><select class="select" id="action" name="action"><option value="">الكل</option>@foreach ($actions as $a)<option value="{{ $a }}" @selected(request('action') === $a)>{{ \App\Support\Audit::label($a) }}</option>@endforeach</select></div><button class="btn btn--primary">تصفية</button></form>
<div class="table-wrap"><table class="table"><thead><tr><th>التاريخ</th><th>المستخدم</th><th>العملية</th><th>العنصر</th><th>تفاصيل</th><th>IP</th></tr></thead><tbody>
@forelse ($logs as $l)
    <tr><td class="small">{{ $l->created_at?->format('Y-m-d H:i') }}</td><td>{{ $l->user?->name ?? 'النظام' }}</td><td>{{ \App\Support\Audit::label($l->action) }}</td>
        <td class="small ltr">{{ $l->subject_type ? class_basename($l->subject_type).' #'.$l->subject_id : '—' }}</td>
        <td class="small ltr" style="max-width:320px;overflow-wrap:anywhere">{{ $l->meta ? json_encode($l->meta, JSON_UNESCAPED_UNICODE) : '' }}</td><td class="small ltr">{{ $l->ip }}</td></tr>
@empty<tr><td colspan="6" class="muted" style="text-align:center;padding:24px">لا توجد عمليات.</td></tr>@endforelse
</tbody></table></div>
{{ $logs->links() }}
@endsection
