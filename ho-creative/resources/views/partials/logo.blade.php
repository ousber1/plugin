@php($logo = setting('brand.logo'))
@if($logo)
  <img src="{{ $logo }}" alt="{{ setting('brand.name') }}" style="height:42px;width:auto">
@else
<svg viewBox="0 0 48 48" aria-hidden="true" focusable="false">
  <rect x="2" y="2" width="44" height="44" rx="12" fill="#1B1F3B"/>
  <circle cx="17" cy="19" r="7" fill="#00A0E3" opacity=".9"/>
  <circle cx="25" cy="19" r="7" fill="#E4007C" opacity=".85"/>
  <circle cx="21" cy="26" r="7" fill="#FFD400" opacity=".85"/>
  <text x="24" y="42" text-anchor="middle" font-family="Arial,sans-serif" font-weight="800" font-size="10" fill="#fff" letter-spacing="1">HO</text>
</svg>
@endif
