@php $cats = \App\Models\Category::ofType('course')->where('is_active', true)->withCount(['courses' => fn ($q) => $q->published()])->get()->where('courses_count', '>', 0); @endphp
@include('builder.blocks._head', ['title' => $t('title')])
<div class="grid grid--4">
    @foreach ($cats as $cat)
        <a class="cat-card" href="{{ route('courses.index', ['category' => $cat->slug]) }}"><span class="cat-card__icon"><x-icon :name="$cat->icon ?: 'layers'" size="24"/></span><span><strong>{{ $cat->tr('name') }}</strong><span class="muted small">{{ __(':count دورة', ['count' => $cat->courses_count]) }}</span></span></a>
    @endforeach
</div>
