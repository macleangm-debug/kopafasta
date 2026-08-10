<x-admin.layout title="Active Loans" heading="Active loans" subheading="Disbursed and currently being repaid">
@include('admin.loans._toolbar')

    @livewire('admin.loans-table', ['status' => 'active', 'lockStatus' => true])
</x-admin.layout>
