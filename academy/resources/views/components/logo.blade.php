@props(['light' => false])
@php $logo = \App\Support\Settings::get('logo_path'); @endphp
<span {{ $attributes->merge(['class' => 'logo'.($light ? ' logo--light' : '')]) }}>
    @if ($logo)
        <img src="{{ \App\Support\Media::url($logo) }}" alt="{{ $siteName }}" height="40">
    @else
        {{-- Original mark: a lamp (نبراس) whose flame forms an open book. --}}
        <svg class="logo__mark" viewBox="0 0 48 48" width="40" height="40" aria-hidden="true">
            <rect width="48" height="48" rx="14" fill="var(--indigo-700)"/>
            <path d="M24 9c4 4.5 6 8 6 11a6 6 0 0 1-12 0c0-3 2-6.5 6-11z" fill="var(--amber)"/>
            <path d="M24 17c1.6 1.9 2.4 3.4 2.4 4.6a2.4 2.4 0 0 1-4.8 0c0-1.2.8-2.7 2.4-4.6z" fill="#fff" opacity=".85"/>
            <path d="M10 30c5-2 10-1.5 14 1.5 4-3 9-3.5 14-1.5v9c-5-2-10-1.5-14 1.5-4-3-9-3.5-14-1.5z" fill="#fff"/>
            <path d="M24 31.5v9" stroke="var(--indigo-700)" stroke-width="1.6"/>
        </svg>
        <span class="logo__text">{{ $siteName }}</span>
    @endif
</span>
