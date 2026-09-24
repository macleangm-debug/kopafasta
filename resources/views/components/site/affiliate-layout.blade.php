@props(['title' => null, 'active' => 'dashboard', 'contentWidth' => 'wide', 'hero' => null, 'heroKey' => null])

@php
    $vendor = auth()->user()
        ? \App\Models\Vendor::query()->where('user_id', auth()->id())->first()
        : null;
    $navService = app(\App\Services\PartnerPortalNavService::class);
    $nav = $navService->affiliateNav();
    $displayName = $vendor?->name ?? auth()->user()?->name ?? 'Partner';
    $kycOk = in_array($vendor?->affiliate_kyc_status, ['verified', 'approved'], true);
    $resolvedHero = $hero === false ? null : ($hero ?? $navService->contextualHero('affiliate', $heroKey ?? $active, $vendor));
@endphp

<x-site.partner-shell
    :title="$title ?? brand_title(__('site.affiliate_portal.title'))"
    :active="$active"
    :content-width="$contentWidth"
    :nav="$nav"
    home-route="site.affiliate.dashboard"
    notifications-route="site.affiliate.notifications"
    :portal-label="__('site.affiliate_portal.title')"
    :display-name="$displayName"
    :subtitle="$vendor?->partner_number ?? auth()->user()?->email"
    :banner="null"
    :hero="$resolvedHero"
    :profile-links="[
        ['label' => __('site.affiliate_portal.nav_dashboard'), 'route' => 'site.affiliate.dashboard'],
        ['label' => __('site.affiliate_portal.nav_wallet'), 'route' => 'site.affiliate.wallet'],
        ['label' => __('site.affiliate_portal.nav_profile'), 'route' => 'site.affiliate.profile'],
    ]"
>
    {{ $slot }}
</x-site.partner-shell>
