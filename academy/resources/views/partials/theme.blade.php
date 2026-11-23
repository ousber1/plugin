{{-- Theme colors, radius and font chosen in Admin › Settings › Appearance (or a ready-made theme). --}}
@php
    use App\Support\Settings;
    $hex = fn ($v, $d) => is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtolower($v) : $d;
    $p = $hex(Settings::get('theme_primary'), '#3a2a9e');
    $a = $hex(Settings::get('theme_accent'), '#f5b82e');
    $bg = $hex(Settings::get('theme_bg'), '#fbf8f1');
    $ft = $hex(Settings::get('theme_footer'), '#15122b');
    // Relative luminance → pick readable text on the accent color.
    $lum = function (string $h) { $c = array_map(fn ($x) => ($x /= 255) <= .03928 ? $x / 12.92 : (($x + .055) / 1.055) ** 2.4, sscanf($h, '#%02x%02x%02x')); return .2126 * $c[0] + .7152 * $c[1] + .0722 * $c[2]; };
    $onAccent = $lum($a) > .32 ? 'var(--indigo-950)' : '#ffffff';
    $radius = ['square' => ['6px', '10px'], 'normal' => ['14px', '22px'], 'round' => ['20px', '30px']][Settings::get('theme_radius')] ?? ['14px', '22px'];
    $css = (string) Settings::get('custom_css');
@endphp
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
    --on-accent: {{ $onAccent }};
    --ivory: {{ $bg }};
    --ivory-200: color-mix(in srgb, {{ $bg }} 96%, #000);
    --footer: {{ $ft }};
    --radius: {{ $radius[0] }};
    --radius-lg: {{ $radius[1] }};
@if (Settings::get('theme_font') === 'system')
    --font: system-ui, -apple-system, "Segoe UI", Tahoma, Arial, sans-serif;
@endif
}
</style>
<meta name="theme-color" content="{{ $p }}">
@if (trim($css) !== '')
<style>{!! str_ireplace('</style', '<\/style', $css) !!}</style>
@endif
