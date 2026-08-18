<x-admin.layout title="Active Loans" heading="" subheading="">
    <x-admin.letterhead kicker="Loan book" title="Active loans" subtitle="Disbursed and currently being repaid" />
@include('admin.loans._toolbar')

    @livewire('admin.loans-table', ['status' => 'active', 'lockStatus' => true])
</x-admin.layout>
