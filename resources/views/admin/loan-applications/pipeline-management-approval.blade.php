<x-admin.layout title="Management approval" heading="" subheading="">
    <x-admin.letterhead kicker="Credit management" title="Management approval" subtitle="Files where committee decided and the approval matrix requires management before the borrower offer" />
@include('admin.loan-applications._pipeline-tabs', ['active' => 'management_approval'])

    <div class="mb-4 rounded-xl bg-gradient-to-r from-brand-muted/60 to-white ring-1 ring-brand/15 px-5 py-4 text-sm text-gray-800">
        <p class="font-semibold">Approved by Credit Committee — awaiting Management approval</p>
        <p class="mt-1 text-gray-600">Only committee-approved files that the Settings Hub matrix sends to Management. Screening, committee, rejected, and incomplete files stay off this desk. Grade, Plus, and Trust do not skip this queue.</p>
    </div>

    @livewire('admin.loan-applications-table', ['pipeline' => 'management_approval', 'lockStage' => true])
</x-admin.layout>
