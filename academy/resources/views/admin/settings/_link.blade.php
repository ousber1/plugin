<div class="repeater-item card card--pad" data-item style="flex-wrap:wrap">
    <div class="field" style="flex:1 1 220px"><label for="link-{{ $i }}-url">الرابط</label><input class="input ltr" id="link-{{ $i }}-url" name="links[{{ $i }}][url]" value="{{ $link['url'] ?? '' }}" placeholder="/p/faq أو https://"></div>
    @foreach (\App\Support\Locales::all() as $code => $m)
        <div class="field" style="flex:1 1 140px"><label for="link-{{ $i }}-{{ $code }}">النص ({{ $m['short'] }})</label><input class="input" id="link-{{ $i }}-{{ $code }}" name="links[{{ $i }}][label][{{ $code }}]" value="{{ $link['label'][$code] ?? '' }}" dir="{{ $m['dir'] }}" maxlength="40"></div>
    @endforeach
    <button class="btn btn--ghost btn--sm" type="button" data-remove style="align-self:center">حذف</button>
</div>
