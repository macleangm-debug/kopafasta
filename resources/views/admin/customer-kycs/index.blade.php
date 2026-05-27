<x-admin.layout title="KYC" heading="Customer KYC" subheading="Identity verification status">
    <x-admin.index-toolbar route="admin.customer-kycs" label="New KYC record" />
    @livewire('admin.customer-kycs-table')
</x-admin.layout>
