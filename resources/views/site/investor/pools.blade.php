<x-site.investor-layout title="Funding pools — Investor" active="pools">
    <div class="mb-6">
        <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-1">Capital deployment</p>
        <h1 class="text-2xl lg:text-3xl font-bold tracking-tight">Funding pools</h1>
        <p class="text-gray-500 text-sm mt-1">Pick a pool that matches your risk appetite and target return.</p>
    </div>

    @php
        $activeFilters = collect([$risk ?? '', $type ?? ''])->filter(fn ($v) => filled($v))->count();
    @endphp

    <div x-data="{ filtersOpen: false }">
        <div class="lg:hidden flex items-center gap-2 mb-4">
            <button type="button" @click="filtersOpen = true"
                    class="inline-flex items-center gap-2 rounded-xl bg-white ring-1 ring-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-800">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                Filters
                @if ($activeFilters > 0)
                    <span class="min-w-[1.25rem] h-5 px-1.5 rounded-full bg-emerald-600 text-white text-[10px] font-bold grid place-items-center">{{ $activeFilters }}</span>
                @endif
            </button>
            @if ($activeFilters > 0)
                <a href="{{ route('site.investor.pools') }}" class="text-sm text-gray-500 hover:text-brand font-medium">Clear</a>
            @endif
        </div>

        <div class="hidden lg:flex flex-wrap gap-2 mb-6">
            @include('site.investor._pool-filters', ['risk' => $risk, 'type' => $type])
        </div>

        <x-site.bottom-sheet title="Pool filters" open="filtersOpen">
            @include('site.investor._pool-filters', ['risk' => $risk, 'type' => $type, 'stacked' => true])
        </x-site.bottom-sheet>
    </div>

    @if ($pools->isEmpty())
        <x-site.empty-state
            icon="🏦"
            title="No pools match your filters"
            description="Try a different risk level or pool type, or check back when new pools open."
            action-label="Clear filters"
            :action-url="route('site.investor.pools')"
        />
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            @foreach ($pools as $pool)
                <x-site.investor-pool-card :pool="$pool" />
            @endforeach
        </div>
    @endif
</x-site.investor-layout>
