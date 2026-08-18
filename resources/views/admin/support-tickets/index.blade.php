<x-admin.layout title="Support Tickets" heading="" subheading="">
    <x-admin.letterhead kicker="Support" title="Support tickets" subtitle="Customer support requests" />
    <x-admin.index-toolbar route="admin.support-tickets" label="New ticket" />
    @livewire('admin.support-tickets-table')
</x-admin.layout>
