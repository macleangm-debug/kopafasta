<x-admin.layout title="Pending Documents" heading="" subheading="">
    <x-admin.letterhead kicker="Credit screening" title="Pending documents" subtitle="Applications awaiting document verification (screening)" />
@livewire('admin.loan-applications-table', ['stage' => 'screening', 'lockStage' => true])
</x-admin.layout>
