<x-admin.layout title="Release queue" heading="" subheading="">
    <x-admin.letterhead kicker="Credit management" title="Release queue" subtitle="Applications ready for release — then payout on the loan record" />
@include('admin.loan-applications._pipeline-tabs', ['active' => 'disbursement'])

    @livewire('admin.loan-applications-table', ['pipeline' => 'disbursement', 'lockStage' => true])
</x-admin.layout>
