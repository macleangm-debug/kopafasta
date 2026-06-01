<x-admin.layout title="Campaigns" heading="Campaigns & promotions" subheading="Birthday messages, fee discounts, and referral promos">
    <x-admin.index-toolbar route="admin.promotions" label="New campaign" />
    @livewire('admin.promotions-table')
</x-admin.layout>
