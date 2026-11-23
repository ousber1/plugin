{{-- Theme colors, radius and font chosen in Admin › Settings › Appearance. --}}
@php
    use App\Support\Settings;
    $hex = fn ($v, $d) => is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? $v : $d;
    $p = $hex(Settings::get('theme_primary'), '#3a2a9e');
    $a = $hex(Settings::get('theme_accent'), '#f5b82e');
    $bg = $hex(Settings::get('theme_bg'), '#fbf8f1');
    $ft = $hex(Settings::get('theme_footer'), '#15122b');
    $radius = ['square' => ['6px', '10px'], 'normal' => ['14px', '22px'], 'round' => ['20px', '30px']][Settings::get('theme_radius')] ?? ['14px', '22px'];
    $css = (string) Settings::get('custom_css');
    $custom = $p !== '#3a2a9e' || $a !== '#f5b82e' || $bg !== '#fbf8f1' || $ft !== '#15122b' || Settings::get('theme_radius') !== 'normal' || Settings::get('theme_font') === 'system';
@endphp
@if ($custom)
<style>
:root {
@if ($p !== '#3a2a9e')
    --indigo-700: {{ $p }};
    --indigo-600: color-mix(in srgb, {{ $p }} 86%, #fff);
    --indigo-500: color-mix(in srgb, {{ $p }} 72%, #fff);
    --indigo-800: color-mix(in srgb, {{ $p }} 80%, #000);
    --indigo-900: color-mix(in srgb, {{ $p }} 58%, #000);
    --indigo-950: color-mix(in srgb, {{ $p }} 42%, #000);
    --indigo-100: color-mix(in srgb, {{ $p }} 16%, #fff);
    --indigo-50: color-mix(in srgb, {{ $p }} 8%, #fff);
@endif
@if ($a !== '#f5b82e')
    --amber: {{ $a }};
    --amber-600: color-mix(in srgb, {{ $a }} 82%, #000);
    --amber-100: color-mix(in srgb, {{ $a }} 22%, #fff);
@endif
    --ivory: {{ $bg }};
    --footer: {{ $ft }};
    --radius: {{ $radius[0] }};
    --radius-lg: {{ $radius[1] }};
@if (Settings::get('theme_font') === 'system')
    --font: system-ui, -apple-system, "Segoe UI", Tahoma, Arial, sans-serif;
@endif
}
</style>
@endif
@if (trim($css) !== '')
<style>{!! str_ireplace('</style', '<\/style', $css) !!}</style>
@endif
