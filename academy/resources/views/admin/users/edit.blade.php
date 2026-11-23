@extends('layouts.admin')
@section('title', $user->name)
@section('content')
<div class="page-head"><h1>{{ $user->name }}</h1><span class="ltr muted">{{ $user->email }}</span></div>
<div class="dash-grid">
    <form class="card card--pad" method="POST" action="{{ route('admin.users.update', $user) }}">
        @csrf @method('PUT')
        <div class="form-grid">
            <div class="field"><label for="role">الدور</label><select class="select" id="role" name="role">@foreach (\App\Enums\Role::cases() as $r)<option value="{{ $r->value }}" @selected($user->role === $r)>{{ $r->label() }}</option>@endforeach</select>
                <p class="hint">المدرّس يدير الدورات المعيّنة له فقط. المدير يدير كل شيء.</p></div>
            @if ($centers->isNotEmpty())
                <div class="field"><label for="center_id">المركز</label><select class="select" id="center_id" name="center_id"><option value="">— بدون مركز (عن بُعد) —</option>@foreach ($centers as $c)<option value="{{ $c->id }}" @selected($user->center_id === $c->id)>{{ $c->tr('city') }} — {{ $c->tr('name') }}</option>@endforeach</select></div>
            @endif
            <div class="field"><label for="headline">المسمى (يظهر للمدرّسين والكتّاب)</label><input class="input" id="headline" name="headline" value="{{ old('headline', $user->headline) }}"></div>
        </div>
        <div class="field"><label for="bio">نبذة</label><textarea class="textarea" id="bio" name="bio" dir="auto">{{ old('bio', $user->bio) }}</textarea></div>
        <label class="check field"><input type="checkbox" name="is_active" value="1" @checked($user->is_active)> الحساب نشط</label>
        @unless ($user->hasVerifiedEmail())<label class="check field"><input type="checkbox" name="verify_email" value="1"> تأكيد البريد الإلكتروني يدويًا</label>@endunless
        <fieldset class="field" style="border:1px solid var(--border);border-radius:12px;padding:12px"><legend class="label">الدورات المعيّنة (للمدرّسين)</legend>
            @php $assigned = $user->assignedCourses->pluck('id')->all(); @endphp
            <div style="max-height:260px;overflow:auto">
                @foreach ($courses as $c)<label class="check small"><input type="checkbox" name="courses[]" value="{{ $c->id }}" @checked(in_array($c->id, $assigned))> {{ $c->tr('title') }}</label>@endforeach
            </div>
        </fieldset>
        <button class="btn btn--primary" type="submit">حفظ</button>
    </form>
    <div class="card card--pad"><h2 class="card__title">الدورات المسجّل فيها</h2>
        <ul class="list-plain small">@forelse ($enrollments as $e)<li>{{ $e->course?->tr('title') }} @if ($e->completed_at)<span class="badge badge--green">مكتملة</span>@endif</li>@empty<li class="muted">لا شيء.</li>@endforelse</ul>
        @if ($user->phone)<p class="small">الهاتف: <span class="ltr">{{ $user->phone }}</span></p>@endif
    </div>
</div>
@endsection
