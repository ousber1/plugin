@extends('layouts.app')
@section('title', $tool->tr('name'))
@section('description', $tool->tr('description'))
@push('head')
<link rel="stylesheet" href="{{ asset('css/tools.css') }}?v={{ filemtime(public_path('css/tools.css')) }}">
@endpush
@section('content')
<div class="container no-print">
    <ol class="breadcrumbs" style="color:var(--muted);margin-top:16px"><li><a href="{{ route('tools.index') }}" style="color:var(--indigo-700)">{{ __('الأدوات') }}</a></li><li>{{ $tool->tr('name') }}</li></ol>
    <div class="tk-head">
        <h1>{{ __('صمّم شهادة احترافية في دقائق') }}</h1>
        <p>{{ __('اختر اللغة ونوع الشهادة والقالب، أضف شعارك وألوانك وتوقيعاتك، ثم حمّلها PDF بحجم A4 — أو ولّد شهادات لكل المشاركين دفعة واحدة.') }}</p>
    </div>
</div>
<div class="container" style="padding-bottom:40px">
    <div id="tool-app" aria-live="polite">
        <noscript><div class="alert alert--warning">{{ __('هذه الأداة تحتاج إلى تفعيل JavaScript في المتصفح.') }}</div></noscript>
    </div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/tools/engine.js') }}?v={{ filemtime(public_path('js/tools/engine.js')) }}" defer></script>
<script src="{{ asset('js/tools/cert.js') }}?v={{ filemtime(public_path('js/tools/cert.js')) }}" defer></script>
@endpush
