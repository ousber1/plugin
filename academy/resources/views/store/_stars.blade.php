@php $r = (float) ($rating ?? 0); @endphp
<span class="pstars" role="img" aria-label="{{ $r ? __('التقييم :r من 5', ['r' => $r]) : __('لا توجد تقييمات بعد') }}">
    @for ($i = 1; $i <= 5; $i++)<svg viewBox="0 0 24 24" width="{{ $size ?? 14 }}" height="{{ $size ?? 14 }}" aria-hidden="true" class="{{ $i <= round($r) ? 'on' : '' }}"><path d="M12 2.5l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.4l-5.9 3.1 1.2-6.5L2.5 9.4l6.6-.9z"/></svg>@endfor
</span>
