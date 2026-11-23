<article class="card center-card">
    <a class="center-card__media" href="{{ route('centers.show', $center) }}" tabindex="-1" aria-hidden="true">
        @if ($center->image_path)
            <img src="{{ $center->imageUrl() }}" alt="" loading="lazy">
        @else
            <span class="center-card__ph"><x-icon name="map-pin" size="34"/><span>{{ $center->tr('city') }}</span></span>
        @endif
        <span class="badge badge--amber center-card__city"><x-icon name="map-pin" size="14"/> {{ $center->tr('city') }}</span>
    </a>
    <div class="center-card__body">
        <h3><a href="{{ route('centers.show', $center) }}">{{ $center->tr('name') }}</a></h3>
        @if ($center->tr('address'))<p class="small muted center-card__line"><x-icon name="map-pin" size="16"/> <span>{{ $center->tr('address') }}</span></p>@endif
        @if ($center->tr('hours'))<p class="small muted center-card__line"><x-icon name="clock" size="16"/> <span>{{ $center->tr('hours') }}</span></p>@endif
        @if (isset($center->courses_count) && $center->courses_count)<p class="small center-card__line"><x-icon name="book" size="16"/> <span>{{ trans_choice('{1} دورة حضورية واحدة|{2} دورتان حضوريتان|[3,10] :count دورات حضورية|[11,*] :count دورة حضورية', $center->courses_count, ['count' => $center->courses_count]) }}</span></p>@endif
    </div>
    <div class="center-card__foot">
        <a class="btn btn--primary btn--sm" href="{{ route('centers.show', $center) }}">{{ __('التفاصيل') }}</a>
        @if ($wa = $center->whatsappLink(__('السلام عليكم، أريد معلومات عن مركز :name.', ['name' => $center->tr('name', 'ar')])))
            <a class="btn btn--whatsapp btn--sm" href="{{ $wa }}" target="_blank" rel="noopener" aria-label="{{ __('واتساب') }} — {{ $center->tr('name') }}"><x-icon name="whatsapp" size="16"/> {{ __('واتساب') }}</a>
        @endif
        <a class="btn btn--ghost btn--sm" href="{{ $center->mapLink() }}" target="_blank" rel="noopener"><x-icon name="map-pin" size="16"/> {{ __('الخريطة') }}</a>
    </div>
</article>
