<x-admin.layout title="System sorted" heading="System sorted" subheading="Capacity auto-reject queue — screening can view; management confirms Send now or Keep in screening">
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    @include('admin.loan-applications._pipeline-tabs', ['active' => 'system_sorted'])

    <div class="mb-4 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-950">
        The system already decided these applications cannot cover repayment. Feedback is scheduled automatically.
        Screening focuses on the <a href="{{ route('admin.loan-applications.pipeline.under-review') }}" class="font-semibold underline">Credit screening</a> list.
        Only management can send rejection early or keep an application in screening.
    </div>

    @livewire('admin.loan-applications-table', ['pipeline' => 'system_sorted', 'lockStage' => true, 'hideSystemSorted' => false])
</x-admin.layout>
