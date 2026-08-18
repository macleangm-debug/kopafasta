<x-admin.layout title="Complaints" heading="" subheading="">
    <x-admin.letterhead kicker="Support" title="Complaints" subtitle="Formal customer complaints" />
    <x-admin.index-toolbar route="admin.complaints" label="New complaint" />
    @livewire('admin.complaints-table')
</x-admin.layout>
