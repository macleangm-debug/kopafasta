<x-admin.layout title="Under Review" heading="Applications under review" subheading="Screening, credit review, and committee review">
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    @include('admin.loan-applications._pipeline-tabs', ['active' => 'under_review'])

    @livewire('admin.loan-applications-table', ['pipeline' => 'under_review', 'lockStage' => true])
</x-admin.layout>
