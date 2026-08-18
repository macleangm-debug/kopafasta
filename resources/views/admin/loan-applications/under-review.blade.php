<x-admin.layout title="Under Review" heading="" subheading="">
    <x-admin.letterhead kicker="Credit screening" title="Under review" subtitle="Applications in credit appraisal" />
@livewire('admin.loan-applications-table', ['stage' => 'credit_appraisal', 'lockStage' => true])
</x-admin.layout>
