<x-admin.layout title="Expenses" heading="Expenses" subheading="Operational and vendor expenses">
    <x-admin.index-toolbar route="admin.expenses" label="New expense" />
    @livewire('admin.expenses-table')
</x-admin.layout>
