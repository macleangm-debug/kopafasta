<x-admin.layout title="Vendor Applications" heading="Vendor Applications" subheading="Vendors awaiting onboarding approval">
    @livewire('admin.vendors-table', ['status' => 'inactive', 'lockStatus' => true])
</x-admin.layout>
