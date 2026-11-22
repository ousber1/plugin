@extends('layouts.admin')
@section('title', 'الطلاب والتقدّم')
@section('content')
<div class="page-head"><h1 dir="auto">{{ $course->tr('title') }}</h1></div>
@include('admin.courses._nav')
<form class="filters" method="GET"><div class="field" style="flex:2"><label for="q">بحث عن طالب</label><input class="input" id="q" name="q" value="{{ request('q') }}"></div><button class="btn btn--primary" type="submit">بحث</button></form>
<div class="table-wrap"><table class="table">
    <thead><tr><th>الطالب</th><th>التقدّم</th><th>آخر درس</th><th>آخر نشاط</th><th>الإتمام</th></tr></thead>
    <tbody>
    @forelse ($rows as $row)
        <tr>
            <td><strong>{{ $row['e']->user->name }}</strong><br><span class="muted small ltr">{{ $row['e']->user->email }}</span></td>
            <td style="min-width:160px"><div class="progress"><div class="progress__bar" style="width:{{ $row['p']['percent'] }}%"></div></div><span class="small">{{ $row['p']['percent'] }}% ({{ $row['p']['completed'] }}/{{ $row['p']['total'] }})</span></td>
            <td class="small" dir="auto">{{ $row['e']->lastLesson?->tr('title') ?? '—' }}</td>
            <td class="small">{{ $row['e']->last_activity_at?->diffForHumans() ?? '—' }}</td>
            <td>@if ($row['e']->completed_at)<span class="badge badge--green">{{ $row['e']->completed_at->format('Y-m-d') }}</span>@else — @endif</td>
        </tr>
    @empty<tr><td colspan="5" class="muted" style="text-align:center;padding:30px">لا يوجد طلاب مسجلون.</td></tr>@endforelse
    </tbody>
</table></div>
{{ $enrollments->links() }}
@endsection
