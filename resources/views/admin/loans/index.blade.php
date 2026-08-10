<x-admin.layout title="Loans" heading="All loans" subheading="Portfolio — active, pending disbursement, closed, and written off">

@include('admin.loans._toolbar', ['showManualCreate' => true])

    @livewire('admin.loans-table')
</x-admin.layout>
