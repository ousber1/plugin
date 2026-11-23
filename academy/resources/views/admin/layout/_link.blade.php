{{-- One link row: $prefix like "columns[0][links]" or "bottom_links", $i index, $link data --}}
<div class="repeater-item link-row" data-item>
    <input class="input ltr" name="{{ $prefix }}[{{ $i }}][url]" value="{{ $link['url'] ?? '' }}" placeholder="/courses أو https://" aria-label="الرابط">
    @foreach (\App\Support\Locales::all() as $code => $m)
        <input class="input" name="{{ $prefix }}[{{ $i }}][label][{{ $code }}]" value="{{ $link['label'][$code] ?? '' }}" dir="{{ $m['dir'] }}" maxlength="60" placeholder="النص ({{ $m['short'] }})" aria-label="النص ({{ $m['name'] }})">
    @endforeach
    <button class="icon-btn" type="button" data-remove aria-label="حذف الرابط"><x-icon name="trash" size="16"/></button>
</div>
