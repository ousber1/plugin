@php
    $items = $c['mode'] === 'custom'
        ? collect($c['items'])->map(fn ($i) => ['v' => $i['value'], 'l' => $t('label', $i)])->all()
        : [
            ['v' => number_format(\App\Models\Course::published()->count()), 'l' => __('دورة مجانية منشورة')],
            ['v' => number_format(\App\Models\Lesson::where('status', 'published')->whereHas('course', fn ($q) => $q->published())->count()), 'l' => __('درس متاح')],
            ['v' => number_format(\App\Models\Enrollment::distinct('user_id')->count('user_id')), 'l' => __('طالب مسجّل')],
            ['v' => number_format(\App\Models\IssuedCertificate::where('status', 'valid')->count()), 'l' => __('شهادة مسلّمة')],
        ];
@endphp
@include('builder.blocks._head', ['title' => $t('title')])
<div class="stats">@foreach ($items as $i)<div class="stat"><strong>{{ $i['v'] }}</strong><span>{{ $i['l'] }}</span></div>@endforeach</div>
