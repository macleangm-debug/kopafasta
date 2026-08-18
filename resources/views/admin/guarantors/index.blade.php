<x-admin.layout title="Guarantors" heading="" subheading="">
    <x-admin.letterhead kicker="Credit file" title="Guarantors" subtitle="Loan guarantors registry" />
    <x-admin.index-toolbar route="admin.guarantors" label="New guarantor" />
    @livewire('admin.guarantors-table')
</x-admin.layout>
