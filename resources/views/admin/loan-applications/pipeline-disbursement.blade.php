<x-admin.layout title="Disbursement" heading="Disbursement pipeline" subheading="Pending, processing, and disbursed applications">
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    @include('admin.loan-applications._pipeline-tabs', ['active' => 'disbursement'])

    @livewire('admin.loan-applications-table', ['pipeline' => 'disbursement', 'lockStage' => true])
</x-admin.layout>
