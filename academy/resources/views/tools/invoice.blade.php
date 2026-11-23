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
        <h1>{{ __('صمّم فاتورتك الاحترافية في دقائق') }}</h1>
        <p>{{ __('اختر اللغة والقالب، أضف شعارك وألوانك، املأ المعلومات وحمّل الفاتورة PDF. الحسابات (الضريبة، الخصم، المجموع والمبلغ بالحروف) تتم تلقائيًا.') }}</p>
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
<script src="{{ asset('js/tools/invoice.js') }}?v={{ filemtime(public_path('js/tools/invoice.js')) }}" defer></script>
@endpush
