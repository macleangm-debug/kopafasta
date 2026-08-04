<x-admin.layout title="Management queue" heading="Management queue" subheading="Credit management team — offer, fees, destination, contract, then ready for disbursement">
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    @include('admin.loan-applications._pipeline-tabs', ['active' => 'approved'])

    @livewire('admin.loan-applications-table', ['pipeline' => 'approved', 'lockStage' => true])
</x-admin.layout>
