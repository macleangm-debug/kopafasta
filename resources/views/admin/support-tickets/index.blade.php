<x-admin.layout title="Support Tickets" heading="" subheading="">
    <x-admin.letterhead kicker="Support" title="Support tickets" subtitle="Agent desk — auto-assigned intake and triage" />
    <x-admin.index-toolbar route="admin.support-tickets" label="New ticket" />
    @livewire('admin.support-tickets-table')
</x-admin.layout>
