@extends('layouts.admin')
@section('title', 'رسائل التواصل')
@section('content')
<div class="page-head"><h1>رسائل التواصل</h1></div>
<div class="table-wrap"><table class="table"><thead><tr><th>المرسل</th><th>الموضوع</th><th>التاريخ</th><th></th></tr></thead><tbody>
@forelse ($messages as $m)
    <tr style="{{ $m->read_at ? '' : 'font-weight:700' }}"><td>{{ $m->name }}<br><span class="small ltr muted">{{ $m->email }}</span></td><td dir="auto">{{ $m->subject ?: \Illuminate\Support\Str::limit($m->body, 60) }}</td><td class="small">{{ $m->created_at->diffForHumans() }}</td>
        <td><a class="btn btn--ghost btn--sm" href="{{ route('admin.messages.show', $m) }}">قراءة</a></td></tr>
@empty<tr><td colspan="4" class="muted" style="text-align:center;padding:24px">لا توجد رسائل.</td></tr>@endforelse
</tbody></table></div>
{{ $messages->links() }}
@endsection
