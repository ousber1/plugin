{{-- Renders builder blocks. $blocks, optional $editing (adds data attributes for the editor preview). --}}
@php use App\Support\Builder; $editing = $editing ?? false; @endphp
@foreach ($blocks as $block)
    @continue($block['hidden'] && ! $editing)
    @php
        $s = $block['style'];
        $c = $block['content'];
        $t = fn ($k, $src = null) => Builder::t(($src ?? $c)[$k] ?? '');
        $bgImg = $s['bg_image'] ? \App\Support\Media::url($s['bg_image']) : null;
        $isDarkBg = in_array($s['bg'], ['brand', 'dark', 'gradient'], true) || $bgImg;
        $light = $s['text'] === 'light' || ($s['text'] === 'auto' && $isDarkBg);
        $classes = 'bk bk--'.$block['type'].' bk--bg-'.$s['bg'].' bk--pad-'.$s['pad'].' bk--align-'.$s['align']
            .($light ? ' bk--light' : '').($bgImg ? ' bk--img bk--ov-'.$s['overlay'] : '').($s['hide_mobile'] ? ' bk--hide-m' : '').($s['hide_desktop'] ? ' bk--hide-d' : '')
            .($s['anim'] !== 'none' ? ' bk--anim bk--anim-'.$s['anim'] : '').($block['hidden'] ? ' bk--is-hidden' : '');
        $styleAttr = ($s['bg'] === 'custom' ? 'background-color:'.$s['bg_color'].';' : '').($bgImg ? "background-image:url('".e($bgImg)."');" : '');
    @endphp
    <section class="{{ $classes }}" @if ($s['anchor']) id="{{ \Illuminate\Support\Str::slug($s['anchor']) }}" @endif @if ($styleAttr) style="{{ $styleAttr }}" @endif @if ($editing) data-block-id="{{ $block['id'] }}" data-block-label="{{ Builder::blocks()[$block['type']]['label'] }}" @endif>
        <div class="bk__in bk__in--{{ $s['width'] }}">
            @include('builder.blocks.'.$block['type'], ['c' => $c, 't' => $t, 'light' => $light])
        </div>
    </section>
@endforeach
