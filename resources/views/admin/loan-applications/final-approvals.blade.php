<x-admin.layout title="Final Approvals" heading="Final approvals" subheading="Applications awaiting final sign-off — then move to disbursement to create the loan">
@livewire('admin.loan-applications-table', ['stage' => 'approval', 'lockStage' => true])
</x-admin.layout>
