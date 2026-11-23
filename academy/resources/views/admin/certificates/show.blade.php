@extends('layouts.admin')
@section('title', $cert->code)
@section('content')
<div class="page-head"><h1 class="ltr">{{ $cert->code }}</h1>@if ($cert->isRevoked())<span class="badge badge--red" style="font-size:1rem">ملغاة</span>@else<span class="badge badge--green" style="font-size:1rem">صالحة</span>@endif</div>
<div class="dash-grid">
    <div class="card card--pad">
        <dl style="display:grid;grid-template-columns:auto 1fr;gap:8px 18px;margin:0">
            <dt class="muted">الاسم</dt><dd style="margin:0" dir="auto">{{ $cert->holder_name }}</dd>
            <dt class="muted">الدورة</dt><dd style="margin:0" dir="auto">{{ $cert->course_title }}</dd>
            <dt class="muted">الإصدار</dt><dd style="margin:0">{{ $cert->issued_on->format('Y-m-d') }}</dd>
            @if ($cert->request)<dt class="muted">الطلب</dt><dd style="margin:0"><a class="ltr" href="{{ route('admin.certificate-requests.show', $cert->request) }}">{{ $cert->request->reference }}</a></dd>@endif
            @if ($cert->isRevoked())<dt class="muted">سبب الإلغاء (داخلي)</dt><dd style="margin:0">{{ $cert->revocation_reason }}</dd>@endif
        </dl>
        <hr style="border:0;border-top:1px solid var(--border);margin:18px 0">
        @if ($cert->isRevoked())
            <form method="POST" action="{{ route('admin.certificates.restore', $cert) }}" data-confirm="إعادة تفعيل الشهادة؟">@csrf<button class="btn btn--outline">إعادة التفعيل</button></form>
        @else
            <form method="POST" action="{{ route('admin.certificates.revoke', $cert) }}" data-confirm="إلغاء الشهادة؟ ستظهر كملغاة لكل من يتحقق منها.">@csrf
                <div class="field"><label for="reason">سبب الإلغاء (داخلي، لا يظهر للعموم)</label><input class="input" id="reason" name="reason" required maxlength="255"></div>
                <button class="btn btn--danger btn--sm">إلغاء الشهادة</button></form>
        @endif
    </div>
    <div class="card card--pad" style="text-align:center">
        <h2 class="card__title">للطباعة على الشهادة</h2>
        <img src="{{ route('admin.certificates.qr', $cert) }}" alt="رمز QR لرابط التحقق" width="200" height="200" style="background:#fff;padding:8px;border:1px solid var(--border);border-radius:8px">
        <p class="small ltr" style="word-break:break-all">{{ $cert->verifyUrl() }}</p>
        <div class="row" style="justify-content:center">
            <button class="btn btn--outline btn--sm" type="button" data-copy="{{ $cert->verifyUrl() }}"><x-icon name="copy" size="16"/> نسخ رابط QR</button>
            <a class="btn btn--outline btn--sm" href="{{ route('admin.certificates.qr', $cert) }}" download="qr-{{ $cert->code }}.svg"><x-icon name="download" size="16"/> تحميل QR (SVG)</a>
            <a class="btn btn--ghost btn--sm" href="{{ $cert->verifyUrl() }}" target="_blank">فتح صفحة التحقق</a>
        </div>
    </div>
</div>
@endsection
