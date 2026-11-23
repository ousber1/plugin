@php $list = \App\Models\Testimonial::where('is_approved', true)->orderBy('sort_order')->limit(6)->get(); @endphp
@if ($list->isNotEmpty())
    @include('builder.blocks._head', ['title' => $t('title')])
    <div class="grid grid--3">
        @foreach ($list as $tm)
            <figure class="card testimonial" style="margin:0">
                @if ($tm->rating)<div class="stars">@for ($i = 1; $i <= 5; $i++)<x-icon name="star" size="16" class="{{ $i <= $tm->rating ? 'on' : '' }}"/>@endfor</div>@endif
                <blockquote dir="auto">“{{ $tm->tr('body') }}”</blockquote>
                <figcaption class="row">@if ($tm->photo_path)<img class="avatar" src="{{ \App\Support\Media::url($tm->photo_path) }}" alt="" width="44" height="44">@else<span class="avatar">{{ mb_substr($tm->name, 0, 1) }}</span>@endif
                    <span><strong>{{ $tm->name }}</strong>@if ($tm->role)<br><span class="muted small">{{ $tm->role }}</span>@endif</span></figcaption>
            </figure>
        @endforeach
    </div>
@elseif ($editing ?? false)
    <p class="muted">لا توجد آراء معتمدة بعد — أضفها من «آراء الطلاب» (تظهر هذه الرسالة في المعاينة فقط).</p>
@endif
