<x-admin.layout title="Closed Loans" heading="Closed Loans" subheading="Fully repaid or written off">
@livewire('admin.loans-table', ['status' => 'closed', 'lockStatus' => true])
</x-admin.layout>
