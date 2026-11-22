<section class="page-hero no-print"><div class="container">
    <ol class="breadcrumbs"><li><a href="{{ route('home') }}">{{ __('الرئيسية') }}</a></li><li><a href="{{ route('tools.index') }}">{{ __('الأدوات') }}</a></li><li>{{ $tool->tr('name') }}</li></ol>
    <h1>{{ $tool->tr('name') }}</h1><p>{{ $tool->tr('description') }}</p>
</div></section>
