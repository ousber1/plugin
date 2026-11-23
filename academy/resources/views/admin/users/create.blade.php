@extends('layouts.admin')
@section('title', 'إضافة مستخدم')
@section('content')
<div class="page-head"><h1>{{ ['instructor' => 'إضافة مدرّس', 'student' => 'إضافة طالب', 'admin' => 'إضافة مدير'][$role] }}</h1></div>
<form class="card card--pad" style="max-width:900px" method="POST" action="{{ route('admin.users.store') }}">
    @csrf
    <div class="form-grid">
        <div class="field"><label for="role">الدور</label><select class="select" id="role" name="role" data-toggle-target="role">@foreach (\App\Enums\Role::cases() as $r)<option value="{{ $r->value }}" @selected(old('role', $role) === $r->value)>{{ $r->label() }}</option>@endforeach</select></div>
        <div class="field"><label for="name">الاسم الكامل <span class="req">*</span></label><input class="input" id="name" name="name" value="{{ old('name') }}" required maxlength="120" dir="auto"></div>
        <div class="field"><label for="email">البريد الإلكتروني <span class="req">*</span></label><input class="input ltr" type="email" id="email" name="email" value="{{ old('email') }}" required></div>
        <div class="field"><label for="phone">الهاتف / واتساب</label><input class="input ltr" id="phone" name="phone" value="{{ old('phone') }}" placeholder="06XXXXXXXX"></div>
        <div class="field"><label for="password">كلمة المرور</label><input class="input ltr" id="password" name="password" value="{{ old('password') }}" minlength="8" autocomplete="new-password" placeholder="اتركها فارغة لإنشاء كلمة مرور تلقائيًا">
            <p class="hint">تظهر لك بعد الحفظ مع زر لإرسالها عبر واتساب.</p></div>
        @if ($centers->isNotEmpty())
            <div class="field"><label for="center_id">المركز</label><select class="select" id="center_id" name="center_id"><option value="">— بدون مركز (عن بُعد) —</option>@foreach ($centers as $c)<option value="{{ $c->id }}" @selected(old('center_id') == $c->id)>{{ $c->tr('city') }} — {{ $c->tr('name') }}</option>@endforeach</select></div>
        @endif
    </div>
    <div data-show-when="role=instructor|admin">
        <div class="field"><label for="headline">المسمى (مثل: مدرّس البرمجة)</label><input class="input" id="headline" name="headline" value="{{ old('headline') }}" dir="auto"></div>
        <div class="field"><label for="bio">نبذة</label><textarea class="textarea" id="bio" name="bio" rows="3" dir="auto">{{ old('bio') }}</textarea></div>
    </div>
    <fieldset class="box" data-show-when="role=instructor"><legend class="label">الدورات التي يديرها المدرّس</legend>
        <div class="check-grid">@forelse ($courses as $c)<label class="check small"><input type="checkbox" name="courses[]" value="{{ $c->id }}" @checked(in_array($c->id, old('courses', [])))> {{ $c->tr('title') }}</label>@empty<p class="muted small">لا توجد دورات بعد.</p>@endforelse</div>
    </fieldset>
    <fieldset class="box" data-show-when="role=student"><legend class="label">تسجيل الطالب مباشرة في دورات (اختياري)</legend>
        <div class="check-grid">@forelse ($courses as $c)<label class="check small"><input type="checkbox" name="enroll[]" value="{{ $c->id }}" @checked(in_array($c->id, old('enroll', [])))> {{ $c->tr('title') }}</label>@empty<p class="muted small">لا توجد دورات بعد.</p>@endforelse</div>
    </fieldset>
    <p class="hint">الحساب يُفعَّل مباشرة (البريد مؤكَّد) لأنك أنشأته بنفسك.</p>
    <button class="btn btn--primary btn--lg" type="submit">إضافة</button>
</form>
@endsection
