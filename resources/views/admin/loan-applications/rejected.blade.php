<x-admin.layout title="Rejected Applications" heading="" subheading="">
    @php
        abort_unless(
            app(\App\Services\CreditDeskAssignmentService::class)->canViewRejected(auth()->user()),
            403,
            'Rejected applications are for screening and committee only.'
        );
    @endphp
    <x-admin.letterhead kicker="Credit screening · Committee" title="Rejected applications" subtitle="View-only files with the decision reason and the feedback letter sent to the applicant" />
@livewire('admin.loan-applications-table', ['stage' => 'rejected', 'lockStage' => true])
</x-admin.layout>
