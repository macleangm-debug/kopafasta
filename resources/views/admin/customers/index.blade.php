<x-admin.layout title="Customers" heading="Customers" subheading="All registered borrowers">
    <x-admin.index-toolbar route="admin.customers" label="New customer" />
    @livewire('admin.customers-table')
</x-admin.layout>
