@extends('layouts.admin')
@section('title', 'تسجيل شهادة')
@section('content')
<div class="page-head"><h1>تسجيل شهادة ورقية للتحقق العام</h1></div>
<form class="card card--pad" style="max-width:720px" method="POST" action="{{ route('admin.certificates.store') }}">
    @csrf
    @if ($fromRequest)
        <input type="hidden" name="certificate_request_id" value="{{ $fromRequest->id }}">
        <input type="hidden" name="course_id" value="{{ $fromRequest->course_id }}">
        <div class="alert alert--info small"><x-icon name="info"/> مرتبطة بالطلب <span class="ltr">{{ $fromRequest->reference }}</span>. بيانات الشحن لا تظهر أبدًا في صفحة التحقق.</div>
    @endif
    <div class="field"><label for="holder_name">الاسم كما طُبع على الشهادة</label><input class="input" id="holder_name" name="holder_name" value="{{ old('holder_name', $fromRequest?->printed_name ?: $fromRequest?->user->name) }}" required dir="auto"></div>
    @unless ($fromRequest)
        <div class="field"><label for="course_id">الدورة (اختياري)</label><select class="select" id="course_id" name="course_id"><option value="">—</option>@foreach ($courses as $c)<option value="{{ $c->id }}" @selected(old('course_id') == $c->id)>{{ $c->tr('title') }}</option>@endforeach</select></div>
    @endunless
    <div class="field"><label for="course_title">عنوان الدورة كما طُبع</label><input class="input" id="course_title" name="course_title" value="{{ old('course_title', $fromRequest?->course->tr('title', 'ar')) }}" required dir="auto"></div>
    <div class="field"><label for="issued_on">تاريخ الإصدار</label><input class="input" type="date" id="issued_on" name="issued_on" value="{{ old('issued_on', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></div>
    <div class="field"><label for="public_note">ملاحظة عامة (اختياري، تظهر في التحقق)</label><input class="input" id="public_note" name="public_note" value="{{ old('public_note') }}" maxlength="255" dir="auto"></div>
    <button class="btn btn--primary" type="submit">تسجيل وتوليد رمز التحقق</button>
</form>
@endsection
