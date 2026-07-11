<x-admin.layout title="Applications" heading="Credit review" subheading="Screening and credit appraisal before committee">
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    @include('admin.loan-applications._pipeline-tabs', ['active' => 'under_review'])

    @livewire('admin.loan-applications-table', ['pipeline' => 'under_review', 'lockStage' => true])
</x-admin.layout>
