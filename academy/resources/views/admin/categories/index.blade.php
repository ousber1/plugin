@extends('layouts.admin')
@section('title', 'التصنيفات')
@section('content')
<div class="page-head"><h1>{{ $types[$type] }}</h1><a class="btn btn--primary" href="{{ route('admin.categories.create', ['type' => $type]) }}"><x-icon name="plus" size="18"/> تصنيف جديد</a></div>
<nav class="tabs">@foreach ($types as $k => $label)<a href="{{ route('admin.categories.index', ['type' => $k]) }}" class="{{ $type === $k ? 'is-active' : '' }}">{{ $label }}</a>@endforeach</nav>
<div class="table-wrap"><table class="table">
    <thead><tr><th>الاسم</th><th>الرابط</th><th>العناصر</th><th>الترتيب</th><th>الحالة</th><th></th></tr></thead>
    <tbody>
    @forelse ($categories as $c)
        <tr><td><x-icon :name="$c->icon ?: 'layers'" size="16"/> {{ $c->tr('name') }}</td><td class="ltr small">{{ $c->slug }}</td>
            <td>{{ $type === 'course' ? $c->courses_count : ($type === 'post' ? $c->posts_count : $c->resources_count) }}</td><td>{{ $c->sort_order }}</td>
            <td>@if ($c->is_active)<span class="badge badge--green">ظاهر</span>@else<span class="badge badge--gray">مخفي</span>@endif</td>
            <td class="row"><a class="btn btn--ghost btn--sm" href="{{ route('admin.categories.edit', $c) }}">تعديل</a>
                <form method="POST" action="{{ route('admin.categories.destroy', $c) }}" data-confirm="حذف التصنيف؟ ستبقى العناصر بدون تصنيف.">@csrf @method('DELETE')<button class="btn btn--ghost btn--sm" type="submit">حذف</button></form></td></tr>
    @empty<tr><td colspan="6" class="muted" style="text-align:center;padding:24px">لا توجد تصنيفات.</td></tr>@endforelse
    </tbody>
</table></div>
@endsection
