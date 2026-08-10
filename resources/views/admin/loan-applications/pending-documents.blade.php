<x-admin.layout title="Pending Documents" heading="Pending Documents" subheading="Applications awaiting document verification (screening)">
@livewire('admin.loan-applications-table', ['stage' => 'screening', 'lockStage' => true])
</x-admin.layout>
