{{-- Floating WhatsApp button on every public page (Admin › Settings › WhatsApp). --}}
@php
    use App\Support\Settings;
    $waUrl = Settings::get('wa_float_enabled') ? Settings::whatsappLink(Settings::tr('wa_float_message') ?: __('السلام عليكم، لدي سؤال حول الأكاديمية.')) : null;
    $side = Settings::get('wa_float_side');
    $label = Settings::tr('wa_float_label');
@endphp
@if ($waUrl)
    <a class="wa-float{{ $side !== 'auto' ? ' wa-float--'.$side : '' }}{{ Settings::get('wa_float_pulse') ? ' wa-float--pulse' : '' }}{{ Settings::get('wa_float_show_label') && $label ? ' wa-float--label' : '' }}"
       href="{{ $waUrl }}" target="_blank" rel="noopener" aria-label="{{ $label ?: __('راسلنا عبر واتساب') }}">
        <x-icon name="whatsapp" size="28"/>
        @if (Settings::get('wa_float_show_label') && $label)<span class="wa-float__label">{{ $label }}</span>@endif
    </a>
@endif
