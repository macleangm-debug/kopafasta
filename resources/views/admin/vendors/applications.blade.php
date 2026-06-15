<x-admin.layout title="Partner Applications" heading="Partner Applications" subheading="Partners awaiting onboarding approval">
    @livewire('admin.vendors-table', ['status' => 'inactive', 'lockStatus' => true])
</x-admin.layout>
