<x-admin.layout title="Management queue" heading="" subheading="">
    <x-admin.letterhead kicker="Credit management" title="Management queue" subtitle="Credit management team — offer, fees, destination, contract, then ready for disbursement" />
@include('admin.loan-applications._pipeline-tabs', ['active' => 'approved'])

    @livewire('admin.loan-applications-table', ['pipeline' => 'approved', 'lockStage' => true])
</x-admin.layout>
