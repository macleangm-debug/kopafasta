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
    portal-label="Supplier portal"
    :display-name="$displayName"
    :subtitle="$vendor?->partner_number ?? auth()->user()?->email"
    banner="Supplier workspace — manage assets, applications, and settlements."
    :profile-links="[['label' => 'Dashboard', 'route' => 'site.supplier.dashboard'], ['label' => 'Assets', 'route' => 'site.supplier.assets']]"
>
    {{ $slot }}
</x-site.partner-shell>
