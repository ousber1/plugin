@extends('layouts.admin')
@section('title', 'رسالة')
@section('content')
<div class="page-head"><h1 dir="auto">{{ $message->subject ?: 'رسالة' }}</h1></div>
<div class="card card--pad" style="max-width:760px">
    <p><strong>{{ $message->name }}</strong> — <a class="ltr" href="mailto:{{ $message->email }}">{{ $message->email }}</a> @if ($message->phone)— <span class="ltr">{{ $message->phone }}</span>@endif</p>
    <p class="small muted">{{ $message->created_at->format('Y-m-d H:i') }}</p>
    <div style="white-space:pre-line" dir="auto">{{ $message->body }}</div>
    <div class="row" style="margin-top:16px"><a class="btn btn--primary btn--sm" href="mailto:{{ $message->email }}?subject={{ rawurlencode('Re: '.($message->subject ?? '')) }}">رد بالبريد</a>
        <form method="POST" action="{{ route('admin.messages.destroy', $message) }}" data-confirm="حذف الرسالة؟">@csrf @method('DELETE')<button class="btn btn--ghost btn--sm">حذف</button></form></div>
</div>
@endsection
