<x-admin.layout title="Funding Pools" heading="Funding Pools" subheading="Committed capital buckets per capital partner — used when loans are allocated">
    @include('admin.settings._tabs', ['active' => 'finance'])

    <div class="mb-4 rounded-xl bg-brand-muted/50 ring-1 ring-brand/10 px-4 py-3 text-sm text-gray-700">
        A funding pool is a partner’s capital wallet. Adding a pool records how much they committed so the system can fund loans proportionally and track interest. Dashboard:
        <a href="{{ route('admin.capital-funding.index') }}" class="font-semibold text-brand hover:underline">Capital funding</a>.
    </div>

    <x-admin.index-toolbar route="admin.funding-pools" label="New funding pool" />
    @livewire('admin.funding-pools-table')
</x-admin.layout>
