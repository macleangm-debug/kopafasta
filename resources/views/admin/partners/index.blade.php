<x-admin.layout title="Partners" heading="Partners hub" subheading="Unified view of suppliers, affiliates, GPS, insurance, and other partner roles">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-600 max-w-2xl">One partner can hold multiple roles. Filter by role or search across all partner types.</p>
        <a href="{{ route('admin.vendors.create') }}" class="inline-flex bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-2 rounded-lg text-sm">+ New partner</a>
    </div>
    @livewire('admin.partners-table')
</x-admin.layout>
