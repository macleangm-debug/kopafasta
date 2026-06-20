<x-admin.layout title="Suppliers" heading="Asset Suppliers" subheading="Partners who upload marketplace inventory">
    @livewire('admin.partners-table', ['category' => 'supplier', 'lockCategory' => true])
</x-admin.layout>
