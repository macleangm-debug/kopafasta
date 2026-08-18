<x-admin.layout title="Applications" heading="" subheading="">
    <x-admin.letterhead kicker="Credit screening" title="Credit screening" subtitle="Screening team queue — documents, face/ID, and credit appraisal before committee" />
@include('admin.loan-applications._pipeline-tabs', ['active' => 'under_review'])

    @livewire('admin.loan-applications-table', ['pipeline' => 'under_review', 'lockStage' => true])
</x-admin.layout>
