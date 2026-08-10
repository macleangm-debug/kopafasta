<x-admin.layout title="Rejected Applications" heading="Rejected Applications" subheading="Applications that did not qualify">
@livewire('admin.loan-applications-table', ['stage' => 'rejected', 'lockStage' => true])
</x-admin.layout>
