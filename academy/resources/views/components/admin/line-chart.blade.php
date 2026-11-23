{{-- Daily line chart (server-rendered SVG). $days: ['Y-m-d'...], $series: [['label','color','values'=>[]]] --}}
@props(['days', 'series', 'title' => '', 'id' => 'lc'.uniqid()])
@php
    $W = 640; $H = 230; $padL = 34; $padR = 46; $padT = 14; $padB = 26;
    $n = count($days);
    $max = max(1, collect($series)->flatMap(fn ($s) => $s['values'])->max());
    $step = $max <= 4 ? 1 : (int) ceil($max / 4);
    $top = $step * (int) ceil($max / $step);
    $x = fn ($i) => $padL + ($n > 1 ? $i * ($W - $padL - $padR) / ($n - 1) : 0);
    $y = fn ($v) => $padT + ($H - $padT - $padB) * (1 - $v / $top);
    $fmt = fn ($d) => \Carbon\Carbon::parse($d)->format('d/m');
    $chartData = json_encode(['days' => array_map($fmt, $days), 'series' => array_map(fn ($s) => ['label' => $s['label'], 'color' => $s['color'], 'values' => $s['values']], $series)], JSON_UNESCAPED_UNICODE);
    // End-of-line value labels, nudged apart when they would overlap.
    $ends = collect($series)->map(fn ($s) => $y(end($s['values'])) + 4)->all();
    if (count($ends) === 2 && abs($ends[0] - $ends[1]) < 14) {
        $ends[$ends[0] <= $ends[1] ? 0 : 1] -= 7;
        $ends[$ends[0] <= $ends[1] ? 1 : 0] += 7;
    }
@endphp
<figure class="chart" style="margin:0">
    @if (count($series) > 1)
        <div class="chart__legend" aria-hidden="true">
            @foreach ($series as $s)<span><i style="background:{{ $s['color'] }}"></i>{{ $s['label'] }}</span>@endforeach
        </div>
    @endif
    <div class="chart__plot" data-linechart="{{ $chartData }}">
        <svg viewBox="0 0 {{ $W }} {{ $H }}" role="img" aria-label="{{ $title }}" dir="ltr" preserveAspectRatio="none" style="width:100%;height:auto;display:block">
            @for ($v = 0; $v <= $top; $v += $step)
                <line x1="{{ $padL }}" x2="{{ $W - $padR }}" y1="{{ $y($v) }}" y2="{{ $y($v) }}" stroke="#ece8dd" stroke-width="1"/>
                <text x="{{ $padL - 8 }}" y="{{ $y($v) + 4 }}" text-anchor="end" font-size="11" fill="#6b6880">{{ $v }}</text>
            @endfor
            @foreach ($days as $i => $d)
                @if ($i % 5 === 0 || $i === $n - 1)
                    <text x="{{ $x($i) }}" y="{{ $H - 6 }}" text-anchor="middle" font-size="11" fill="#6b6880">{{ $fmt($d) }}</text>
                @endif
            @endforeach
            @foreach ($series as $si => $s)
                @php
                    $pts = collect($s['values'])->map(fn ($v, $i) => round($x($i), 1).','.round($y($v), 1))->implode(' ');
                    $last = end($s['values']);
                @endphp
                @if ($si === 0)
                    <polygon points="{{ $padL }},{{ $y(0) }} {{ $pts }} {{ $x($n - 1) }},{{ $y(0) }}" fill="{{ $s['color'] }}" opacity=".08"/>
                @endif
                <polyline points="{{ $pts }}" fill="none" stroke="{{ $s['color'] }}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>
                <circle cx="{{ $x($n - 1) }}" cy="{{ $y($last) }}" r="4" fill="{{ $s['color'] }}" stroke="#fff" stroke-width="2"/>
                <text x="{{ $x($n - 1) + 8 }}" y="{{ $ends[$si] }}" font-size="12" font-weight="700" fill="#1b1a2e">{{ $last }}</text>
            @endforeach
            <line class="chart__cross" x1="0" x2="0" y1="{{ $padT }}" y2="{{ $H - $padB }}" stroke="#9aa0ad" stroke-width="1" stroke-dasharray="3 3" visibility="hidden"/>
            <rect class="chart__hit" x="{{ $padL }}" y="{{ $padT }}" width="{{ $W - $padL - $padR }}" height="{{ $H - $padT - $padB }}" fill="transparent" data-pad-l="{{ $padL }}" data-pad-r="{{ $padR }}" data-w="{{ $W }}"/>
        </svg>
        <div class="chart__tip" role="status" hidden></div>
    </div>
    <details class="chart__table">
        <summary>عرض البيانات كجدول</summary>
        <div class="table-wrap"><table class="table"><thead><tr><th>اليوم</th>@foreach ($series as $s)<th>{{ $s['label'] }}</th>@endforeach</tr></thead>
            <tbody>@foreach (array_reverse($days, true) as $i => $d)<tr><td>{{ $fmt($d) }}</td>@foreach ($series as $s)<td>{{ $s['values'][$i] }}</td>@endforeach</tr>@endforeach</tbody></table></div>
    </details>
</figure>
