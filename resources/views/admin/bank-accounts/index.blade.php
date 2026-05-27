<x-admin.layout title="Bank Accounts" heading="Bank Accounts" subheading="Operating, disbursement, collection accounts">
    <x-admin.index-toolbar route="admin.bank-accounts" label="New bank account" />
    @livewire('admin.bank-accounts-table')
</x-admin.layout>
