{{-- Horizontal bars (HTML/CSS so labels flow naturally in RTL). $rows: [['label','value','note'?,'url'?]] --}}
@props(['rows', 'empty' => 'لا توجد بيانات بعد.', 'unit' => ''])
@php $max = max(1, collect($rows)->max('value')); @endphp
@if (collect($rows)->sum('value') == 0)
    <p class="muted small" style="margin:0">{{ $empty }}</p>
@else
    <ul class="hbars">
        @foreach ($rows as $r)
            <li title="{{ $r['label'] }}: {{ $r['value'] }} {{ $unit }}">
                <a href="{{ $r['url'] ?? '#' }}" class="hbars__row">
                    <span class="hbars__label" dir="auto">{{ $r['label'] }}</span>
                    <span class="hbars__track"><span class="hbars__bar" style="width:{{ max(1.5, $r['value'] * 100 / $max) }}%"></span></span>
                    <span class="hbars__value">{{ $r['value'] }}@if (! empty($r['note']))<small>{{ $r['note'] }}</small>@endif</span>
                </a>
            </li>
        @endforeach
    </ul>
@endif
