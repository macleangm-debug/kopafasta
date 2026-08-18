<x-admin.layout title="Final Approvals" heading="" subheading="">
    <x-admin.letterhead kicker="Credit management" title="Final approvals" subtitle="Applications awaiting final sign-off — then move to disbursement to create the loan" />
@livewire('admin.loan-applications-table', ['stage' => 'approval', 'lockStage' => true])
</x-admin.layout>
