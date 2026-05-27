<x-admin.layout title="Support Tickets" heading="Support Tickets" subheading="Customer support requests">
    <x-admin.index-toolbar route="admin.support-tickets" label="New ticket" />
    @livewire('admin.support-tickets-table')
</x-admin.layout>
