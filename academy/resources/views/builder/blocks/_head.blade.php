@if (($title ?? '') !== '' || ($sub ?? '') !== '')
    <div class="bk-head">
        @if (($eyebrow ?? '') !== '')<span class="eyebrow">{{ $eyebrow }}</span>@endif
        @if (($title ?? '') !== '')<h2 dir="auto">{{ $title }}</h2>@endif
        @if (($sub ?? '') !== '')<p dir="auto">{{ $sub }}</p>@endif
    </div>
@endif
