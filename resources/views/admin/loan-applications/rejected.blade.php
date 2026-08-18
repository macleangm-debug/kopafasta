<x-admin.layout title="Rejected Applications" heading="" subheading="">
    <x-admin.letterhead kicker="Applications" title="Rejected applications" subtitle="Applications that did not qualify" />
@livewire('admin.loan-applications-table', ['stage' => 'rejected', 'lockStage' => true])
</x-admin.layout>
