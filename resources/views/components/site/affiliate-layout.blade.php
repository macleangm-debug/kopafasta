@props(['title' => 'Affiliate portal', 'active' => 'dashboard'])

@php
    $tabs = [
        'dashboard' => ['label' => 'Dashboard', 'route' => 'site.affiliate.dashboard'],
        'referrals' => ['label' => 'Referrals', 'route' => 'site.affiliate.referrals'],
        'wallet'    => ['label' => 'Commission wallet', 'route' => 'site.affiliate.wallet'],
        'profile'   => ['label' => 'Profile', 'route' => 'site.affiliate.profile'],
    ];
@endphp

<x-site.borrower-layout :title="brand_title($title)" active="dashboard" content-width="wide">
    <div class="mb-6 border-b border-gray-200">
        <nav class="flex flex-wrap gap-2 -mb-px">
            @foreach ($tabs as $key => $tab)
                <a href="{{ route($tab['route']) }}"
                   class="px-4 py-2.5 text-sm font-semibold border-b-2 transition {{ $active === $key ? 'border-amber-500 text-amber-700' : 'border-transparent text-gray-500 hover:text-gray-800' }}">
                    {{ $tab['label'] }}
                </a>
            @endforeach
        </nav>
    </div>
    {{ $slot }}
</x-site.borrower-layout>
