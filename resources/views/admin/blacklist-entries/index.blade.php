<x-admin.layout title="Blacklist" heading="Blacklist" subheading="Blocked identifiers — NIDA, phone, TIN, etc.">
    <x-admin.index-toolbar route="admin.blacklist-entries" label="New blacklist entry" />
    @livewire('admin.blacklist-entries-table')
</x-admin.layout>
