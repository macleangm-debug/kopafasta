<x-admin.layout title="Loan Applications" heading="" subheading="">
    <x-admin.letterhead kicker="Applications" title="Loan applications" subtitle="Submitted applications and unfinished drafts" />
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
            ['Credit screening', 'admin.loan-applications.pipeline.under-review'],
            ['Committee', 'admin.loan-applications.pre-approvals'],
        ] as [$label, $name])
            <a href="{{ route($name) }}"
               class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $route === $name ? 'bg-brand-gold text-brand' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div x-data="{ tab: new URLSearchParams(window.location.search).get('tab') || 'submitted' }" class="space-y-4">
        <nav class="flex flex-wrap gap-2" aria-label="Application lists">
            <button type="button"
                    @click="tab = 'submitted'; const u = new URL(window.location.href); u.searchParams.set('tab', 'submitted'); history.replaceState({}, '', u)"
                    :class="tab === 'submitted' ? 'bg-brand text-white ring-brand' : 'bg-white text-gray-700 ring-gray-200 hover:bg-brand-muted/40'"
                    class="rounded-xl px-4 py-2.5 text-sm font-semibold ring-1 transition">
                Submitted applications
            </button>
            <button type="button"
                    @click="tab = 'drafts'; const u = new URL(window.location.href); u.searchParams.set('tab', 'drafts'); history.replaceState({}, '', u)"
                    :class="tab === 'drafts' ? 'bg-brand text-white ring-brand' : 'bg-white text-gray-700 ring-gray-200 hover:bg-brand-muted/40'"
                    class="rounded-xl px-4 py-2.5 text-sm font-semibold ring-1 transition">
                Incomplete drafts
            </button>
        </nav>

        <div x-show="tab === 'submitted'" x-cloak>
            <p class="text-xs text-brand mb-3 font-medium">Every product including asset-backed — fully submitted files.</p>
            @livewire('admin.loan-applications-table')
        </div>

        <div x-show="tab === 'drafts'" x-cloak>
            <div class="flex flex-wrap items-end justify-between gap-3 mb-3">
                <p class="text-sm text-gray-600">Started in the borrower wizard but not fully submitted (often waiting on application fee).</p>
                <a href="{{ route('admin.loan-applications.incomplete') }}" class="text-sm font-semibold text-brand hover:underline">Open drafts queue →</a>
            </div>
            @livewire('admin.loan-application-drafts-table')
        </div>
    </div>
</x-admin.layout>
