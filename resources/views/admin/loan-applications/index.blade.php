<x-admin.layout title="Loan Applications" heading="Loan Applications" subheading="All submitted applications and unfinished drafts in one place">
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
               class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $route === $name ? 'bg-brand-gold text-brand' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <p class="text-xs text-brand mb-3 font-medium">Submitted applications (every product, including asset-backed)</p>
    @livewire('admin.loan-applications-table')

    <div class="mt-10 pt-8 border-t border-brand/10">
        <div class="flex flex-wrap items-end justify-between gap-3 mb-3">
            <div>
                <p class="text-xs uppercase tracking-widest text-brand font-bold">Incomplete drafts</p>
                <p class="text-sm text-gray-600 mt-0.5">Started in the borrower wizard but not fully submitted yet (often waiting on application fee).</p>
            </div>
            <a href="{{ route('admin.loan-applications.incomplete') }}" class="text-sm font-semibold text-brand hover:underline">Open drafts queue →</a>
        </div>
        @livewire('admin.loan-application-drafts-table')
    </div>
</x-admin.layout>
