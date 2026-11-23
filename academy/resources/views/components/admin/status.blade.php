@props(['status'])
@if ($status === 'published')<span class="badge badge--green">منشور</span>@else<span class="badge badge--gray">مسودة</span>@endif
