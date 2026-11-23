@extends('layouts.app')
@section('title', __('نتيجة التحقق'))
@section('noindex', '1')
@section('content')
<section class="page-hero page-hero--center"><div class="container"><h1>{{ __('نتيجة التحقق') }}</h1><p class="ltr">{{ $code }}</p></div></section>
<div class="container">
    @if (! $result)
        <div class="card verify-result verify-result--missing overlap" role="status">
            <span class="verify-result__icon"><x-icon name="alert" size="34"/></span>
            <h2>{{ __('لم نجد شهادة بهذا الرمز') }}</h2>
            <p class="muted">{{ __('تأكّد من كتابة الرمز كما هو مطبوع على الشهادة. إن استمرت المشكلة، تواصل معنا.') }}</p>
        </div>
    @elseif ($result['status'] === 'revoked')
        <div class="card verify-result verify-result--revoked overlap" role="status">
            <span class="verify-result__icon"><x-icon name="x" size="34"/></span>
            <h2>{{ __('هذه الشهادة ملغاة') }}</h2>
            <p class="muted">{{ __('الرمز :code كان مسجلًا لدينا لكنه أُلغي ولم يعد صالحًا.', ['code' => $result['code']]) }}</p>
            @if ($result['revoked_at'])<p class="small muted">{{ __('تاريخ الإلغاء: :d', ['d' => $result['revoked_at']->translatedFormat('j F Y')]) }}</p>@endif
        </div>
    @else
        <div class="card verify-result verify-result--valid overlap" role="status">
            <span class="verify-result__icon"><x-icon name="check" size="34"/></span>
            <h2>{{ __('شهادة صالحة') }}</h2>
            <p class="muted">{{ __('هذه الشهادة صادرة عن :site ومسجّلة لدينا.', ['site' => $siteName]) }}</p>
            <dl>
                <dt>{{ __('رمز التحقق') }}</dt><dd class="ltr">{{ $result['code'] }}</dd>
                @if ($result['holder_name'])<dt>{{ __('اسم الحاصل عليها') }}</dt><dd dir="auto">{{ $result['holder_name'] }}</dd>@endif
                @if ($result['course_title'])<dt>{{ __('الدورة') }}</dt><dd dir="auto">{{ $result['course_title'] }}</dd>@endif
                @if ($result['issued_on'])<dt>{{ __('تاريخ الإصدار') }}</dt><dd>{{ $result['issued_on']->translatedFormat('j F Y') }}</dd>@endif
                @if ($result['public_note'])<dt>{{ __('ملاحظة') }}</dt><dd dir="auto">{{ $result['public_note'] }}</dd>@endif
            </dl>
        </div>
    @endif
    <p style="text-align:center;margin-top:20px"><a class="btn btn--outline" href="{{ route('verify.index') }}">{{ __('تحقّق من رمز آخر') }}</a></p>
</div>
@endsection
