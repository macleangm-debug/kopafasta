<x-admin.layout title="Loans" heading="" subheading="">
    <x-admin.letterhead kicker="Loan book" title="All loans" subtitle="Portfolio — active, pending disbursement, closed, and written off" />

@include('admin.loans._toolbar', ['showManualCreate' => true])

    @livewire('admin.loans-table')
</x-admin.layout>
