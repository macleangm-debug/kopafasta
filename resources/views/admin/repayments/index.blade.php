<x-admin.layout title="Repayments" heading="Repayments" subheading="All inbound repayment transactions">
    <x-admin.index-toolbar route="admin.repayments" label="New repayment" />
    @livewire('admin.repayments-table')
</x-admin.layout>
