@props(['active' => 'dashboard'])

@php
    $tabs = [
        'dashboard' => ['label' => 'Dashboard', 'route' => 'site.affiliate.dashboard'],
        'referrals' => ['label' => 'Referrals', 'route' => 'site.affiliate.referrals'],
        'wallet'    => ['label' => 'Commission wallet', 'route' => 'site.affiliate.wallet'],
        'profile'   => ['label' => 'Profile', 'route' => 'site.affiliate.profile'],
    ];
@endphp

<nav class="mb-6 -mx-1 px-1 overflow-x-auto snap-x snap-mandatory scrollbar-none" aria-label="Affiliate portal">
    <div class="inline-flex min-w-max p-1 rounded-2xl bg-gray-100/80 ring-1 ring-gray-200/80 gap-1">
        @foreach ($tabs as $key => $tab)
            <a href="{{ route($tab['route']) }}"
               class="snap-start min-w-[7.5rem] text-center px-4 py-2.5 rounded-xl text-sm font-semibold transition whitespace-nowrap
                      {{ $active === $key ? 'bg-white text-brand shadow-sm ring-1 ring-gray-200/80' : 'text-gray-600 hover:text-gray-900 hover:bg-white/60' }}">
                {{ $tab['label'] }}
            </a>
        @endforeach
    </div>
</nav>
