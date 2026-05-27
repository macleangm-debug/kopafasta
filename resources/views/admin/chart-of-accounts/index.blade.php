<x-admin.layout title="Chart of Accounts" heading="Chart of Accounts" subheading="General ledger accounts">
    <x-admin.index-toolbar route="admin.chart-of-accounts" label="New GL account" />
    @livewire('admin.chart-of-accounts-table')
</x-admin.layout>
