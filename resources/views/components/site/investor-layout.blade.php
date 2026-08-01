@props(['title' => 'Capital partner portal — Kopafasta', 'active' => 'dashboard', 'contentWidth' => 'wide'])

@php
    $navService = app(\App\Services\PartnerPortalNavService::class);
    $nav = $navService->capitalNav();
    $displayName = auth()->user()?->name ?? 'Capital partner';
    $lender = auth()->user()
        ? \App\Models\Lender::query()->where('user_id', auth()->id())->first()
        : null;
@endphp

<x-site.partner-shell
    :title="$title"
    :active="$active"
    :content-width="$contentWidth"
    :nav="$nav"
    home-route="site.investor.dashboard"
    portal-label="Capital partner portal"
    :display-name="$displayName"
    :subtitle="$lender?->code ?? auth()->user()?->email"
    banner="Capital workspace — deploy funds, track pools, and monitor returns."
    :profile-links="[
        ['label' => 'Dashboard', 'route' => 'site.investor.dashboard'],
        ['label' => 'Profile', 'route' => 'site.investor.profile'],
        ['label' => 'Documents', 'route' => 'site.investor.documents'],
        ['label' => 'Wallet', 'route' => 'site.investor.wallet'],
    ]"
>
    {{ $slot }}
</x-site.partner-shell>
