<x-admin.layout title="Fee management" heading="Fee management" subheading="Central catalog: fixed or percentage fees by type and when they are charged (application, after approval, disbursement, late, events)">
    @include('admin.settings._tabs', ['active' => 'fees'])
    <x-admin.index-toolbar route="admin.charges-fees" label="New fee" />
    @livewire('admin.charges-fees-table')
</x-admin.layout>
