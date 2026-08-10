<x-admin.layout title="Applications" heading="Credit screening" subheading="Screening team queue — documents, face/ID, and credit appraisal before committee">
@include('admin.loan-applications._pipeline-tabs', ['active' => 'under_review'])

    @livewire('admin.loan-applications-table', ['pipeline' => 'under_review', 'lockStage' => true])
</x-admin.layout>
