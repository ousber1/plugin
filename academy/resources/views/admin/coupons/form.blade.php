@extends('layouts.admin')
@section('title', 'رمز خصم')
@section('content')
<div class="page-head"><h1>{{ $item->exists ? 'تعديل رمز الخصم' : 'رمز خصم جديد' }}</h1></div>
<form class="card card--pad" style="max-width:640px" method="POST" action="{{ $item->exists ? route('admin.coupons.update', $item) : route('admin.coupons.store') }}">
    @csrf @if ($item->exists) @method('PUT') @endif
    <div class="form-grid">
        <div class="field"><label for="code">الرمز</label><input class="input ltr" id="code" name="code" value="{{ old('code', $item->code) }}" required maxlength="40" placeholder="WELCOME20" style="text-transform:uppercase"></div>
        <div class="field"><label for="type">النوع</label><select class="select" id="type" name="type"><option value="percent" @selected(old('type', $item->type) === 'percent')>نسبة مئوية %</option><option value="fixed" @selected(old('type', $item->type) === 'fixed')>مبلغ ثابت (درهم)</option></select></div>
        <div class="field"><label for="value">القيمة</label><input class="input ltr" type="number" min="1" id="value" name="value" value="{{ old('value', $item->value) }}" required></div>
        <div class="field"><label for="max_uses">أقصى عدد استعمالات</label><input class="input ltr" type="number" min="1" id="max_uses" name="max_uses" value="{{ old('max_uses', $item->max_uses) }}" placeholder="غير محدود"></div>
        <div class="field"><label for="expires_at">تاريخ الانتهاء</label><input class="input ltr" type="date" id="expires_at" name="expires_at" value="{{ old('expires_at', $item->expires_at?->format('Y-m-d')) }}"></div>
    </div>
    <label class="check field"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active))> مفعّل</label>
    <button class="btn btn--primary" type="submit">حفظ</button>
</form>
@endsection
