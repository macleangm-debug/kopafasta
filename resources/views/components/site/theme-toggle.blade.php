@props(['variant' => 'header'])

@php
    $isCompact = $variant === 'compact' || $variant === 'mobile';
@endphp

<button type="button"
        data-kf-theme-toggle
        class="{{ $isCompact
            ? 'inline-flex items-center justify-center rounded-lg border border-gray-200/80 bg-white/80 p-1.5 text-gray-700 shadow-sm hover:text-brand'
            : 'inline-flex items-center justify-center rounded-lg border border-gray-200/80 bg-white/80 p-2 text-gray-700 shadow-sm hover:bg-brand-muted hover:text-brand' }}"
        title="{{ __('site.account_theme.toggle') }}"
        aria-label="{{ __('site.account_theme.toggle') }}">
    <svg class="kf-theme-icon-sun w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
        <circle cx="12" cy="12" r="4"/>
        <path d="M12 3v2M12 19v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M3 12h2M19 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4"/>
    </svg>
    <svg class="kf-theme-icon-moon w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M21 14.5A8.5 8.5 0 1 1 9.5 3 7 7 0 0 0 21 14.5z"/>
    </svg>
</button>
