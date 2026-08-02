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
    notifications-route="site.investor.notifications"
    portal-label="Capital partner portal"
    :display-name="$displayName"
    :subtitle="$lender?->code ?? auth()->user()?->email"
    :banner="null"
    :profile-links="[
        ['label' => 'Dashboard', 'route' => 'site.investor.dashboard'],
        ['label' => 'Profile', 'route' => 'site.investor.profile'],
        ['label' => 'Settings', 'route' => 'site.investor.settings'],
        ['label' => 'Reports', 'route' => 'site.investor.documents'],
        ['label' => 'Wallet', 'route' => 'site.investor.wallet'],
    ]"
>
    {{ $slot }}
</x-site.partner-shell>
