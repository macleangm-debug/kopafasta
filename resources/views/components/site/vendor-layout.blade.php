@props(['title' => null, 'active' => 'dashboard', 'contentWidth' => 'wide'])

@php
    $vendor = auth()->user()
        ? \App\Models\Vendor::query()->where('user_id', auth()->id())->first()
        : null;
    $navService = app(\App\Services\PartnerPortalNavService::class);
    $nav = $navService->serviceNav($vendor);
    $portalLabel = $navService->portalSubtitle($vendor);
    $displayName = $vendor?->name ?? auth()->user()?->name ?? 'Partner';
    $profileLinks = [
        ['label' => __('site.partner_portal.nav_profile'), 'route' => 'site.partner.profile'],
        ['label' => __('site.partner_portal.nav_documents'), 'route' => 'site.partner.documents'],
        ['label' => __('site.partner_portal.nav_settings'), 'route' => 'site.partner.settings'],
    ];
    if ($vendor && app(\App\Services\PartnerTermsService::class)->appliesTo($vendor)) {
        array_splice($profileLinks, 1, 0, [[
            'label' => __('site.partner_portal.nav_terms'),
            'route' => 'site.partner.terms',
        ]]);
    }
@endphp

<x-site.partner-shell
    :title="$title ?? brand_title($portalLabel)"
    :active="$active"
    :content-width="$contentWidth"
    :nav="$nav"
    home-route="site.partner.dashboard"
    :portal-label="$portalLabel"
    :display-name="$displayName"
    :subtitle="$vendor?->partner_number ?? auth()->user()?->email"
    :banner="$navService->roleBanner($vendor)"
    :profile-links="$profileLinks"
>
    {{ $slot }}
</x-site.partner-shell>
