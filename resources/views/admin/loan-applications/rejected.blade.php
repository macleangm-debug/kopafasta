<x-admin.layout title="Rejected Applications" heading="Rejected Applications" subheading="Applications that did not qualify">
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    @livewire('admin.loan-applications-table', ['stage' => 'rejected', 'lockStage' => true])
</x-admin.layout>
