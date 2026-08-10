<x-admin.layout title="Loans in Arrears" heading="Loans in Arrears" subheading="Accounts with overdue installments flagged by the daily arrears job">
@livewire('admin.loans-table', ['status' => 'arrears', 'lockStatus' => true])
</x-admin.layout>
