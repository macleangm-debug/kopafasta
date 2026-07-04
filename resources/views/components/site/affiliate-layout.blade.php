@props(['title' => 'Affiliate portal', 'active' => 'dashboard'])

<x-site.borrower-layout :title="brand_title($title)" active="dashboard" content-width="wide">
    <x-site.affiliate-portal-tabs :active="$active" />
    {{ $slot }}
</x-site.borrower-layout>
