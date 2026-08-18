<x-admin.layout title="Loans in Arrears" heading="" subheading="">
    <x-admin.letterhead kicker="Collections" title="Loans in arrears" subtitle="Accounts with overdue installments flagged by the daily arrears job" />
@livewire('admin.loans-table', ['status' => 'arrears', 'lockStatus' => true])
</x-admin.layout>
