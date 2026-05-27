<x-admin.layout title="Under Review" heading="Under Review" subheading="Applications in credit appraisal">
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    @livewire('admin.loan-applications-table', ['stage' => 'credit_appraisal', 'lockStage' => true])
</x-admin.layout>
