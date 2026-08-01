<x-admin.layout title="Partners" heading="Partners" subheading="Suppliers, affiliates, GPS, insurance, valuers, and field partners">
    <div class="mb-4">
        <a href="{{ route('admin.partners.index') }}" class="text-sm font-semibold text-brand hover:underline">← Partners hub</a>
    </div>
    <x-admin.index-toolbar route="admin.partners" label="New partner" />
    @livewire('admin.partners-table')
</x-admin.layout>
