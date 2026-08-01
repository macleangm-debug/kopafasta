@props(['title' => 'Supplier portal — Kopafasta', 'active' => 'dashboard', 'contentWidth' => 'wide'])

@php
    $vendor = auth()->user()
        ? \App\Models\Vendor::query()->where('user_id', auth()->id())->first()
        : null;
    $navService = app(\App\Services\PartnerPortalNavService::class);
    $nav = $navService->supplierNav();
    $displayName = $vendor?->name ?? auth()->user()?->name ?? 'Supplier';
@endphp

<x-site.partner-shell
    :title="$title"
    :active="$active"
    :content-width="$contentWidth"
    :nav="$nav"
    home-route="site.supplier.dashboard"
    :portal-label="__('site.supplier_portal.title')"
    :display-name="$displayName"
    :subtitle="$vendor?->partner_number ?? auth()->user()?->email"
    :banner="__('site.supplier_portal.banner')"
    :profile-links="[
        ['label' => __('site.supplier_portal.nav_dashboard'), 'route' => 'site.supplier.dashboard'],
        ['label' => __('site.supplier_portal.nav_profile'), 'route' => 'site.supplier.profile'],
        ['label' => __('site.supplier_portal.nav_documents'), 'route' => 'site.supplier.documents'],
        ['label' => __('site.supplier_portal.nav_settings'), 'route' => 'site.supplier.settings'],
    ]"
>
    {{ $slot }}
</x-site.partner-shell>
