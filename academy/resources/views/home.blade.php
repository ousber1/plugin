@extends('layouts.app')
@section('canonical', route('home'))

@section('content')
@foreach ($sections as $section)
    @switch($section)
        @case('hero')
            <section class="hero">
                <div class="container hero__grid">
                    <div>
                        <span class="hero__badge"><span class="dot"></span> {{ __('كل الدورات مجانية 100%') }}</span>
                        <h1>{{ __('تعلّم مهارة جديدة اليوم، واحصل على شهادتك الورقية غدًا') }}</h1>
                        <p class="hero__lead">{{ \App\Support\Settings::tr('tagline') }}</p>
                        <form class="search-bar" action="{{ route('courses.index') }}" method="GET" role="search">
                            <x-icon name="search"/>
                            <label for="hero-q" class="sr-only">{{ __('ابحث عن دورة') }}</label>
                            <input id="hero-q" type="search" name="q" placeholder="{{ __('ابحث عن دورة: برمجة، تصميم، لغة إنجليزية…') }}" dir="auto">
                            <button class="btn btn--primary" type="submit">{{ __('ابحث') }}</button>
                        </form>
                        <div class="hero__actions">
                            <a class="btn btn--amber btn--lg" href="{{ route('courses.index') }}">{{ __('تصفّح الدورات') }}</a>
                            @guest<a class="btn btn--glass btn--lg" href="{{ route('register') }}">{{ __('أنشئ حسابك المجاني') }}</a>@endguest
                            @auth<a class="btn btn--glass btn--lg" href="{{ route('dashboard') }}">{{ __('تابع التعلم') }}</a>@endauth
                        </div>
                    </div>
                    <div class="hero__art" aria-hidden="true">
                        <svg viewBox="0 0 400 400" width="100%" height="100%">
                            <circle cx="200" cy="200" r="170" fill="rgba(255,255,255,.06)" stroke="rgba(255,255,255,.18)" stroke-dasharray="6 10"/>
                            <circle cx="200" cy="200" r="118" fill="rgba(255,255,255,.08)"/>
                            <path d="M200 92c26 29 39 52 39 72a39 39 0 0 1-78 0c0-20 13-43 39-72z" fill="#f5b82e"/>
                            <path d="M200 140c10 12 15 21 15 29a15 15 0 0 1-30 0c0-8 5-17 15-29z" fill="#fff" opacity=".85"/>
                            <path d="M110 238c33-13 63-10 90 10 27-20 57-23 90-10v58c-33-13-63-10-90 10-27-20-57-23-90-10z" fill="#fff"/>
                            <path d="M200 248v58" stroke="#3a2a9e" stroke-width="4"/>
                        </svg>
                        <div class="hero__card" style="top:12%;inset-inline-start:-4%"><span class="icon-wrap"><x-icon name="play"/></span> {{ __('فيديوهات قصيرة وواضحة') }}</div>
                        <div class="hero__card" style="bottom:16%;inset-inline-end:-6%"><span class="icon-wrap"><x-icon name="truck"/></span> {{ __('شهادة ورقية تصلك للبيت') }}</div>
                    </div>
                </div>
            </section>
            @break

        @case('stats')
            @if (($stats['courses'] ?? 0) > 0)
            <section class="section--tight">
                <div class="container">
                    <div class="stats overlap">
                        <div class="stat"><strong>{{ number_format($stats['courses']) }}</strong><span>{{ __('دورة مجانية منشورة') }}</span></div>
                        <div class="stat"><strong>{{ number_format($stats['lessons']) }}</strong><span>{{ __('درس متاح') }}</span></div>
                        <div class="stat"><strong>{{ number_format($stats['students']) }}</strong><span>{{ __('طالب مسجّل') }}</span></div>
                        <div class="stat"><strong>{{ number_format($stats['certificates']) }}</strong><span>{{ __('شهادة مسلّمة') }}</span></div>
                    </div>
                </div>
            </section>
            @endif
            @break

        @case('categories')
            @if (isset($categories) && $categories->isNotEmpty())
            <section class="section">
                <div class="container">
                    <div class="section__head">
                        <div><span class="eyebrow">{{ __('المجالات') }}</span><h2>{{ __('اختر المجال الذي يناسبك') }}</h2></div>
                        <a class="btn btn--outline" href="{{ route('courses.index') }}">{{ __('كل الدورات') }}</a>
                    </div>
                    <div class="grid grid--4">
                        @foreach ($categories as $cat)
                            <a class="cat-card" href="{{ route('courses.index', ['category' => $cat->slug]) }}">
                                <span class="cat-card__icon"><x-icon :name="$cat->icon ?: 'layers'" size="24"/></span>
                                <span><strong>{{ $cat->tr('name') }}</strong><span class="muted small">{{ __(':count دورة', ['count' => $cat->courses_count]) }}</span></span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
            @endif
            @break

        @case('featured')
            @if (isset($featured) && $featured->isNotEmpty())
            <section class="section section--white">
                <div class="container">
                    <div class="section__head">
                        <div><span class="eyebrow">{{ __('مختارة لك') }}</span><h2>{{ __('دورات مميزة') }}</h2><p>{{ __('اختيارات فريق الأكاديمية للبدء بقوة.') }}</p></div>
                    </div>
                    <div class="grid grid--4">@foreach ($featured as $course) @include('partials.course-card') @endforeach</div>
                </div>
            </section>
            @endif
            @break

        @case('how')
            <section class="section">
                <div class="container">
                    <div class="section__head"><div><span class="eyebrow">{{ __('كيف تعمل الأكاديمية؟') }}</span><h2>{{ __('من التسجيل إلى الشهادة في أربع خطوات') }}</h2></div></div>
                    <ol class="steps" style="list-style:none;padding:0;margin:0">
                        <li class="step"><h3>{{ __('أنشئ حسابك') }}</h3><p>{{ __('حساب مجاني في أقل من دقيقة.') }}</p></li>
                        <li class="step"><h3>{{ __('سجّل في الدورة مجانًا') }}</h3><p>{{ __('بدون أي دفع أو بطاقة بنكية.') }}</p></li>
                        <li class="step"><h3>{{ __('تعلّم وأكمل الدروس') }}</h3><p>{{ __('تقدّمك محفوظ، وتابع من حيث توقفت.') }}</p></li>
                        <li class="step"><h3>{{ __('اطلب شهادتك عبر واتساب') }}</h3><p>{{ __('نؤكد بياناتك ونرسل الشهادة الورقية إلى عنوانك.') }}</p></li>
                    </ol>
                </div>
            </section>
            @break

        @case('recent')
            @if (isset($recent) && $recent->isNotEmpty())
            <section class="section section--white">
                <div class="container">
                    <div class="section__head">
                        <div><span class="eyebrow">{{ __('أحدث الإضافات') }}</span><h2>{{ __('دورات منشورة حديثًا') }}</h2></div>
                        <a class="btn btn--outline" href="{{ route('courses.index', ['sort' => 'newest']) }}">{{ __('عرض الكل') }}</a>
                    </div>
                    <div class="grid grid--4">@foreach ($recent as $course) @include('partials.course-card') @endforeach</div>
                </div>
            </section>
            @endif
            @break

        @case('benefits')
            <section class="section">
                <div class="container">
                    <div class="section__head"><div><span class="eyebrow">{{ __('لماذا نحن؟') }}</span><h2>{{ __('تعلّم بلا عوائق') }}</h2></div></div>
                    <div class="grid grid--3">
                        <div class="feature"><div class="feature__icon"><x-icon name="unlock" size="26"/></div><h3>{{ __('مجانية بالكامل') }}</h3><p>{{ __('كل الدورات مفتوحة مجانًا، بدون اشتراك أو رسوم خفية للتعلم.') }}</p></div>
                        <div class="feature"><div class="feature__icon"><x-icon name="globe" size="26"/></div><h3>{{ __('بالدارجة والعربية والفرنسية') }}</h3><p>{{ __('شرح قريب منك، مع مصطلحات بالفرنسية والإنجليزية حين يلزم.') }}</p></div>
                        <div class="feature"><div class="feature__icon"><x-icon name="chart" size="26"/></div><h3>{{ __('تقدّم واضح') }}</h3><p>{{ __('تابع نسبة إنجازك، واختبر فهمك باختبارات قصيرة.') }}</p></div>
                        <div class="feature"><div class="feature__icon"><x-icon name="award" size="26"/></div><h3>{{ __('شهادة ورقية') }}</h3><p>{{ __('بعد إتمام المتطلبات، اطلب شهادتك المطبوعة وتتبع شحنتها.') }}</p></div>
                        <div class="feature"><div class="feature__icon"><x-icon name="shield" size="26"/></div><h3>{{ __('قابلة للتحقق') }}</h3><p>{{ __('كل شهادة تحمل رمزًا يمكن لأي جهة التحقق منه عبر موقعنا.') }}</p></div>
                        <div class="feature"><div class="feature__icon"><x-icon name="phone" size="26"/></div><h3>{{ __('على الهاتف أيضًا') }}</h3><p>{{ __('واجهة خفيفة وسريعة تعمل جيدًا على الهواتف.') }}</p></div>
                    </div>
                    @if (\App\Support\Settings::get('accreditation_enabled') && \App\Support\Settings::tr('accreditation_text'))
                        <div class="alert alert--info" style="margin-top:24px"><x-icon name="award"/> <span>{{ \App\Support\Settings::tr('accreditation_text') }}</span></div>
                    @endif
                </div>
            </section>
            @break

        @case('testimonials')
            @if (isset($testimonials) && $testimonials->isNotEmpty())
            <section class="section section--white">
                <div class="container">
                    <div class="section__head"><div><span class="eyebrow">{{ __('آراء الطلاب') }}</span><h2>{{ __('ماذا يقول طلابنا؟') }}</h2></div></div>
                    <div class="grid grid--3">
                        @foreach ($testimonials as $t)
                            <figure class="card testimonial" style="margin:0">
                                @if ($t->rating)<div class="stars" aria-label="{{ __('التقييم :r من 5', ['r' => $t->rating]) }}">@for ($i = 1; $i <= 5; $i++)<x-icon name="star" size="16" class="{{ $i <= $t->rating ? 'on' : '' }}"/>@endfor</div>@endif
                                <blockquote dir="auto">“{{ $t->tr('body') }}”</blockquote>
                                <figcaption class="row">
                                    @if ($t->photo_path)<img class="avatar" src="{{ \App\Support\Media::url($t->photo_path) }}" alt="" width="44" height="44">@else<span class="avatar">{{ mb_substr($t->name, 0, 1) }}</span>@endif
                                    <span><strong>{{ $t->name }}</strong>@if ($t->role)<br><span class="muted small">{{ $t->role }}</span>@endif</span>
                                </figcaption>
                            </figure>
                        @endforeach
                    </div>
                </div>
            </section>
            @endif
            @break

        @case('articles')
            @if (isset($articles) && $articles->isNotEmpty())
            <section class="section">
                <div class="container">
                    <div class="section__head">
                        <div><span class="eyebrow">{{ __('من المدونة') }}</span><h2>{{ __('أحدث المقالات') }}</h2></div>
                        <a class="btn btn--outline" href="{{ route('blog.index') }}">{{ __('كل المقالات') }}</a>
                    </div>
                    <div class="grid grid--3">@foreach ($articles as $post) @include('blog.card') @endforeach</div>
                </div>
            </section>
            @endif
            @break

        @case('partners')
            @if (isset($partners) && $partners->isNotEmpty())
            <section class="section section--white section--tight">
                <div class="container">
                    <h2 class="sr-only">{{ __('شركاؤنا') }}</h2>
                    <p class="muted" style="text-align:center">{{ __('شركاؤنا') }}</p>
                    <div class="partners">
                        @foreach ($partners as $p)
                            @if ($p->url)<a href="{{ $p->url }}" target="_blank" rel="noopener"><img src="{{ \App\Support\Media::url($p->logo_path) }}" alt="{{ $p->name }}" loading="lazy"></a>
                            @else<img src="{{ \App\Support\Media::url($p->logo_path) }}" alt="{{ $p->name }}" loading="lazy">@endif
                        @endforeach
                    </div>
                </div>
            </section>
            @endif
            @break

        @case('cta')
            <section class="section">
                <div class="container" style="text-align:center">
                    <h2>{{ __('جاهز للبدء؟') }}</h2>
                    <p class="muted">{{ __('اختر دورتك الأولى الآن وابدأ التعلم مجانًا.') }}</p>
                    <a class="btn btn--primary btn--lg" href="{{ route('courses.index') }}">{{ __('تصفّح الدورات') }}</a>
                </div>
            </section>
            @break
    @endswitch
@endforeach
@endsection
