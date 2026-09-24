@props(['title' => 'Supplier portal — Kopafasta', 'active' => 'dashboard', 'contentWidth' => 'wide', 'hero' => null, 'heroKey' => null])

@php
    $vendor = auth()->user()
        ? \App\Models\Vendor::query()->where('user_id', auth()->id())->first()
        : null;
    $navService = app(\App\Services\PartnerPortalNavService::class);
    $nav = $navService->supplierNav();
    $displayName = $vendor?->name ?? auth()->user()?->name ?? 'Supplier';
    $resolvedHero = $hero === false ? null : ($hero ?? $navService->contextualHero('supplier', $heroKey ?? $active, $vendor));
@endphp

<x-site.partner-shell
    :title="$title"
    :active="$active"
    :content-width="$contentWidth"
    :nav="$nav"
    home-route="site.supplier.dashboard"
    notifications-route="site.supplier.notifications"
    :portal-label="__('site.supplier_portal.title')"
    :display-name="$displayName"
    :subtitle="$vendor?->partner_number ?? auth()->user()?->email"
    :banner="null"
    :hero="$resolvedHero"
    :profile-links="[
        ['label' => __('site.supplier_portal.nav_card'), 'route' => 'site.supplier.profile', 'params' => ['section' => 'card']],
        ['label' => __('site.supplier_portal.nav_profile'), 'route' => 'site.supplier.profile'],
        ['label' => __('site.supplier_portal.nav_settings'), 'route' => 'site.supplier.settings'],
    ]"
>
    {{ $slot }}
</x-site.partner-shell>
