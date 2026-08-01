<x-admin.layout title="Loan Applications" heading="Loan Applications" subheading="Filter by status or open a credit pipeline stage">
    <x-admin.index-toolbar route="admin.loan-applications" label="New application" />

    @php
        $route = request()->route()?->getName();
    @endphp
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ([
            ['All', 'admin.loan-applications.index'],
            ['New / submitted', 'admin.loan-applications.new'],
            ['Rejected', 'admin.loan-applications.rejected'],
            ['Incomplete drafts', 'admin.loan-applications.incomplete'],
            ['Credit review', 'admin.loan-applications.pipeline.under-review'],
            ['Committee', 'admin.loan-applications.pre-approvals'],
        ] as [$label, $name])
            <a href="{{ route($name) }}"
               class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $route === $name ? 'bg-amber-500 text-gray-900' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @livewire('admin.loan-applications-table')
</x-admin.layout>
