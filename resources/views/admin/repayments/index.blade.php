<x-admin.layout title="Repayments" heading="Repayments" subheading="All inbound repayment transactions">
    <x-admin.index-toolbar route="admin.repayments" label="New repayment" :showCreate="admin_repayment_recording_allowed()" />
    @livewire('admin.repayments-table')
</x-admin.layout>
