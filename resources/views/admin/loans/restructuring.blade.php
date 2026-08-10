<x-admin.layout title="Loan Restructuring" heading="Loan Restructuring" subheading="Loans under modification or restructuring">
@livewire('admin.loans-table', ['status' => 'restructuring', 'lockStatus' => true])
</x-admin.layout>
