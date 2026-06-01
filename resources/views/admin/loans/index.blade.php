<x-admin.layout title="Loans" heading="All loans" subheading="Portfolio — active, pending disbursement, closed, and written off">

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    @include('admin.loans._toolbar', ['showManualCreate' => true])

    @livewire('admin.loans-table')
</x-admin.layout>
