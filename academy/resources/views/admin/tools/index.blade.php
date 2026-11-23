@extends('layouts.admin')
@section('title', 'الأدوات')
@section('content')
<div class="page-head"><h1>الأدوات واقتراحات الزوار</h1></div>
<form class="card card--pad" method="POST" action="{{ route('admin.tools.update') }}" style="margin-bottom:24px">
    @csrf @method('PUT')
    <h2 class="card__title">الأدوات المتاحة</h2>
    <p class="small muted">عدّل الاسم والوصف كما يظهران في صفحة الأدوات (الحقول الفارغة تستعمل العربية).
        @unless (\App\Http\Controllers\ToolController::aiToolEnabled()) المولّد بالذكاء الاصطناعي غير مفعّل حاليًا؛ تعمل الأداة كمنشئ اختبارات يدوي. <a href="{{ route('admin.settings.edit', 'ai') }}">إعدادات الذكاء الاصطناعي</a>@endunless</p>
    @foreach ($tools as $t)
        <fieldset class="qq">
            <legend class="label">{{ $t->tr('name') }} <span class="muted small ltr">/tools/{{ $t->key }}</span></legend>
            <div class="row" style="margin-bottom:10px">
                <label class="check"><input type="checkbox" name="tools[{{ $t->id }}][is_enabled]" value="1" @checked($t->is_enabled)> مفعّلة</label>
                <label class="small">الترتيب <input class="input" type="number" min="0" name="tools[{{ $t->id }}][sort_order]" value="{{ $t->sort_order }}" style="max-width:90px;display:inline-block;min-height:36px"></label>
                <label class="small">الشارة <input class="input" name="tools[{{ $t->id }}][badge]" value="{{ $t->badge }}" style="max-width:140px;display:inline-block;min-height:36px"></label>
            </div>
            <div class="form-grid">
                @foreach (\App\Support\Locales::all() as $code => $m)
                    <div class="field"><label for="tn-{{ $t->id }}-{{ $code }}">الاسم ({{ $m['short'] }})</label><input class="input" id="tn-{{ $t->id }}-{{ $code }}" name="tools[{{ $t->id }}][name][{{ $code }}]" value="{{ $t->name[$code] ?? '' }}" dir="{{ $m['dir'] }}" maxlength="80" @if ($code === 'ar') required @endif></div>
                @endforeach
            </div>
            <div class="form-grid">
                @foreach (\App\Support\Locales::all() as $code => $m)
                    <div class="field"><label for="td-{{ $t->id }}-{{ $code }}">الوصف ({{ $m['short'] }})</label><textarea class="textarea" style="min-height:70px" id="td-{{ $t->id }}-{{ $code }}" name="tools[{{ $t->id }}][description][{{ $code }}]" dir="{{ $m['dir'] }}" maxlength="300">{{ $t->description[$code] ?? '' }}</textarea></div>
                @endforeach
            </div>
        </fieldset>
    @endforeach
    <button class="btn btn--primary" type="submit">حفظ</button>
</form>
<h2>اقتراحات الأدوات</h2>
<nav class="tabs">@foreach (['' => 'الكل', 'pending' => 'جديدة', 'approved' => 'مقبولة', 'done' => 'منفّذة', 'rejected' => 'مرفوضة'] as $k => $l)<a class="{{ request('status', '') === $k ? 'is-active' : '' }}" href="{{ route('admin.tools.index', array_filter(['status' => $k])) }}">{{ $l }}</a>@endforeach</nav>
@forelse ($suggestions as $s)
    <div class="card card--pad" style="margin-bottom:12px">
        <p dir="auto" style="white-space:pre-line">{{ $s->idea }}</p>
        <p class="small muted">{{ $s->name ?: 'بدون اسم' }} @if ($s->email)— <span class="ltr">{{ $s->email }}</span>@endif — {{ $s->created_at->diffForHumans() }}</p>
        <form class="row" method="POST" action="{{ route('admin.tool-suggestions.update', $s) }}">@csrf @method('PUT')
            <select class="select" name="status" style="max-width:160px;min-height:36px" aria-label="الحالة">@foreach (['pending' => 'جديدة', 'approved' => 'مقبولة', 'done' => 'منفّذة', 'rejected' => 'مرفوضة'] as $k => $l)<option value="{{ $k }}" @selected($s->status === $k)>{{ $l }}</option>@endforeach</select>
            <input class="input" name="admin_note" value="{{ $s->admin_note }}" placeholder="ملاحظة داخلية" aria-label="ملاحظة داخلية" style="flex:1;min-height:36px">
            <button class="btn btn--primary btn--sm">حفظ</button></form>
        <form method="POST" action="{{ route('admin.tool-suggestions.destroy', $s) }}" data-confirm="حذف الاقتراح؟" style="margin-top:6px">@csrf @method('DELETE')<button class="btn btn--ghost btn--sm">حذف</button></form>
    </div>
@empty<p class="muted">لا توجد اقتراحات.</p>@endforelse
{{ $suggestions->links() }}
@endsection
