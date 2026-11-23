@php
    $u = auth()->user();
    $is = fn (...$p) => request()->routeIs(...$p) ? 'is-active' : '';
    $pendingCerts = $u->isAdmin() ? \App\Models\CertificateRequest::where('status', 'pending_contact')->count() : 0;
    $pendingReviews = $u->isAdmin() ? \App\Models\Review::where('is_approved', false)->count() : 0;
    $unread = $u->isAdmin() ? \App\Models\ContactMessage::whereNull('read_at')->count() : 0;
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'لوحة الإدارة') | {{ $siteName }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
</head>
<body class="admin">
<a class="skip-link" href="#admin-main">انتقل إلى المحتوى</a>
<div class="admin-shell">
    <aside class="admin-side" id="admin-side" aria-label="قائمة الإدارة">
        <a href="{{ route('admin.dashboard') }}" class="logo"><x-logo light/></a>
        <nav>
            <a class="{{ $is('admin.dashboard') }}" href="{{ route('admin.dashboard') }}"><x-icon name="home" size="18"/> الرئيسية</a>
            <h2>التعليم</h2>
            <a class="{{ $is('admin.courses.*', 'admin.lessons.*', 'admin.quizzes.*', 'admin.sections.*') }}" href="{{ route('admin.courses.index') }}"><x-icon name="book" size="18"/> {{ $u->isAdmin() ? 'الدورات' : 'دوراتي' }}</a>
            @if ($u->isAdmin())
                <a class="{{ request()->routeIs('admin.categories.*') && request('type', 'course') === 'course' ? 'is-active' : '' }}" href="{{ route('admin.categories.index', ['type' => 'course']) }}"><x-icon name="layers" size="18"/> التصنيفات</a>
                <a class="{{ $is('admin.reviews.*') }}" href="{{ route('admin.reviews.index') }}"><x-icon name="star" size="18"/> المراجعات @if ($pendingReviews)<span class="count">{{ $pendingReviews }}</span>@endif</a>
                <a class="{{ $is('admin.centers.*') }}" href="{{ route('admin.centers.index') }}"><x-icon name="map-pin" size="18"/> مراكز التكوين</a>
                <h2>الشهادات</h2>
                <a class="{{ $is('admin.certificate-requests.*') }}" href="{{ route('admin.certificate-requests.index') }}"><x-icon name="truck" size="18"/> طلبات الشهادات @if ($pendingCerts)<span class="count">{{ $pendingCerts }}</span>@endif</a>
                <a class="{{ $is('admin.certificates.*') }}" href="{{ route('admin.certificates.index') }}"><x-icon name="shield" size="18"/> سجل التحقق</a>
                <h2>المحتوى</h2>
                <a class="{{ $is('admin.posts.*') }}" href="{{ route('admin.posts.index') }}"><x-icon name="file-text" size="18"/> المدونة</a>
                <a class="{{ $is('admin.resources.*') }}" href="{{ route('admin.resources.index') }}"><x-icon name="folder" size="18"/> المكتبة</a>
                <a class="{{ $is('admin.pages.*') }}" href="{{ route('admin.pages.index') }}"><x-icon name="list" size="18"/> الصفحات</a>
                <a class="{{ $is('admin.testimonials.*') }}" href="{{ route('admin.testimonials.index') }}"><x-icon name="users" size="18"/> آراء الطلاب</a>
                <a class="{{ $is('admin.partners.*') }}" href="{{ route('admin.partners.index') }}"><x-icon name="award" size="18"/> الشركاء</a>
                <a class="{{ $is('admin.tools.*') }}" href="{{ route('admin.tools.index') }}"><x-icon name="sparkles" size="18"/> الأدوات والاقتراحات</a>
                <a class="{{ $is('admin.messages.*') }}" href="{{ route('admin.messages.index') }}"><x-icon name="inbox" size="18"/> رسائل التواصل @if ($unread)<span class="count">{{ $unread }}</span>@endif</a>
                <h2>تصميم الموقع</h2>
                <a class="{{ request()->routeIs('admin.builder.*') && request()->route('target') === 'home' ? 'is-active' : '' }}" href="{{ route('admin.builder.edit', 'home') }}"><x-icon name="sparkles" size="18"/> تصميم الصفحة الرئيسية</a>
                <a class="{{ request()->route('group') === 'appearance' ? 'is-active' : '' }}" href="{{ route('admin.settings.edit', 'appearance') }}"><x-icon name="eye" size="18"/> الثيمات والألوان</a>
                <a class="{{ $is('admin.layout.*') }}" href="{{ route('admin.layout.edit') }}"><x-icon name="layers" size="18"/> الهيدر والتذييل</a>
                <a class="{{ $is('admin.optimize.*') }}" href="{{ route('admin.optimize.index') }}"><x-icon name="chart" size="18"/> تحسين الموقع و SEO</a>
                <h2>المتجر</h2>
                @php $ordersToReview = \App\Models\Order::where('status', 'review')->count(); @endphp
                <a class="{{ $is('admin.products.*') }}" href="{{ route('admin.products.index') }}"><x-icon name="store" size="18"/> المنتجات</a>
                <a class="{{ $is('admin.orders.*') }}" href="{{ route('admin.orders.index') }}"><x-icon name="receipt" size="18"/> الطلبات @if ($ordersToReview)<span class="count">{{ $ordersToReview }}</span>@endif</a>
                <a class="{{ $is('admin.coupons.*') }}" href="{{ route('admin.coupons.index') }}"><x-icon name="sparkles" size="18"/> رموز الخصم</a>
                <a class="{{ $is('admin.product-reviews.*') }}" href="{{ route('admin.product-reviews.index') }}"><x-icon name="star" size="18"/> تقييمات المنتجات</a>
                <a class="{{ request()->route('group') === 'store' ? 'is-active' : '' }}" href="{{ route('admin.settings.edit', 'store') }}"><x-icon name="settings" size="18"/> الدفع والعملات</a>
                <h2>النظام</h2>
                <a class="{{ $is('admin.users.*') }}" href="{{ route('admin.users.index') }}"><x-icon name="users" size="18"/> المستخدمون</a>
                <a class="{{ $is('admin.settings.*') && request()->route('group') !== 'appearance' ? 'is-active' : '' }}" href="{{ route('admin.settings.edit') }}"><x-icon name="settings" size="18"/> الإعدادات</a>
                <a class="{{ $is('admin.audit.*') }}" href="{{ route('admin.audit.index') }}"><x-icon name="list" size="18"/> سجل العمليات</a>
            @endif
        </nav>
    </aside>
    <div style="min-width:0">
        <header class="admin-top">
            <button class="icon-btn admin-burger" type="button" data-side-toggle aria-controls="admin-side" aria-expanded="false" aria-label="القائمة"><x-icon name="menu"/></button>
            <span class="muted small">{{ $u->name }} — <span class="badge">{{ $u->role->label() }}</span></span>
            <div class="row">
                <a class="btn btn--outline btn--sm" href="{{ route('home') }}" target="_blank"><x-icon name="eye" size="16"/> الموقع</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn--ghost btn--sm" type="submit"><x-icon name="log-out" size="16"/> خروج</button></form>
            </div>
        </header>
        <main class="admin-main" id="admin-main" tabindex="-1">
            @foreach (['success' => 'success', 'error' => 'error', 'info' => 'info', 'status' => 'success'] as $key => $type)
                @if (session($key))<div class="alert alert--{{ $type }}" role="status"><x-icon :name="$type === 'error' ? 'alert' : 'check-circle'"/> <span>{{ session($key) }}</span></div>@endif
            @endforeach
            @include('partials.form-errors')
            @if ($u->isAdmin() && ($pendingMigrations = \App\Http\Controllers\Admin\SystemController::pendingMigrations()))
                <div class="alert alert--warning" role="alert"><x-icon name="alert"/>
                    <div style="flex:1">تم رفع نسخة جديدة من الموقع وتحتاج قاعدة البيانات إلى تحديث ({{ count($pendingMigrations) }}). اضغط الزر مرة واحدة؛ لن تُحذف أي بيانات.</div>
                    <form method="POST" action="{{ route('admin.system.migrate') }}">@csrf<button class="btn btn--primary btn--sm" type="submit">تحديث قاعدة البيانات</button></form>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
<script src="{{ asset('js/admin.js') }}?v={{ filemtime(public_path('js/admin.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
