@props(['title' => null, 'active' => 'dashboard', 'contentWidth' => 'wide'])

@php
    $vendor = auth()->user()
        ? \App\Models\Vendor::query()->where('user_id', auth()->id())->first()
        : null;
    $navService = app(\App\Services\PartnerPortalNavService::class);
    $nav = $navService->affiliateNav();
    $displayName = $vendor?->name ?? auth()->user()?->name ?? 'Partner';
@endphp

<x-site.partner-shell
    :title="$title ?? brand_title(__('site.affiliate_portal.title'))"
    :active="$active"
    :content-width="$contentWidth"
    :nav="$nav"
    home-route="site.affiliate.dashboard"
    :portal-label="__('site.affiliate_portal.title')"
    :display-name="$displayName"
    :subtitle="$vendor?->partner_number ?? auth()->user()?->email"
    :profile-links="[
        ['label' => __('site.affiliate_portal.nav_profile'), 'route' => 'site.affiliate.profile'],
        ['label' => __('site.affiliate_portal.nav_wallet'), 'route' => 'site.affiliate.wallet'],
    ]"
>
    {{ $slot }}
</x-site.partner-shell>
