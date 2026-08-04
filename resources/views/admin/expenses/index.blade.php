<x-admin.layout
    title="Expenses"
    heading="Operational expenses"
    subheading="Manual operating costs — rent, payroll, marketing, utilities. Partner payouts are automated on the payout ledger.">
    <x-admin.index-toolbar route="admin.expenses" label="New expense" />
    @livewire('admin.expenses-table')
</x-admin.layout>
