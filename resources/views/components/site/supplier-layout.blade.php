@props(['title' => 'Supplier portal — KopaFasta', 'active' => 'dashboard'])
@php
$nav = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'site.supplier.dashboard'],
    ['key' => 'assets', 'label' => 'Assets', 'route' => 'site.supplier.assets'],
    ['key' => 'applications', 'label' => 'Applications', 'route' => 'site.supplier.applications'],
    ['key' => 'reservations', 'label' => 'Reservations', 'route' => 'site.supplier.reservations'],
    ['key' => 'delivered', 'label' => 'Delivered', 'route' => 'site.supplier.delivered'],
    ['key' => 'requests', 'label' => 'Asset requests', 'route' => 'site.supplier.requests'],
    ['key' => 'settlements', 'label' => 'Settlements', 'route' => 'site.supplier.settlements'],
];
@endphp
<x-site.borrower-layout :title="$title" active="">
    <div class="mb-6 flex flex-wrap gap-2">
        @foreach ($nav as $item)
            <a href="{{ route($item['route']) }}"
               class="px-3 py-1.5 rounded-full text-sm font-semibold {{ $active === $item['key'] ? 'bg-amber-500 text-gray-900' : 'bg-white ring-1 ring-gray-200 text-gray-600' }}">
                {{ $item['label'] }}
            </a>
        @endforeach
    </div>
    {{ $slot }}
</x-site.borrower-layout>
