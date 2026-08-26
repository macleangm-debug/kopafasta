<x-admin.layout title="Growth" heading="Growth" subheading="Reach the right customers and understand what's working.">
    <div class="flex flex-wrap gap-2 mb-6">
        @can('marketing.campaigns.create')
            <a href="{{ route('admin.promotions.create') }}" class="inline-flex items-center rounded-xl bg-brand-gold text-brand text-sm font-bold px-4 py-2.5">+ Campaign</a>
        @endcan
        @can('marketing.demos.create')
            <a href="{{ route('admin.growth.demos.create') }}" class="inline-flex items-center rounded-xl bg-white ring-1 ring-brand/15 text-sm font-semibold px-4 py-2.5">+ Demo</a>
        @endcan
        @can('marketing.offers.manage')
            <a href="{{ route('admin.growth.offers.index') }}" class="inline-flex items-center rounded-xl bg-white ring-1 ring-brand/15 text-sm font-semibold px-4 py-2.5">+ Offer</a>
        @endcan
        @can('marketing.personas.manage')
            <a href="{{ route('admin.growth.personas.index') }}" class="inline-flex items-center rounded-xl bg-white ring-1 ring-brand/15 text-sm font-semibold px-4 py-2.5">+ Persona</a>
        @endcan
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-3">
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Active campaigns</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-brand">{{ \App\Support\MoneyFormat::compact($stats['campaigns']) }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Customers reached</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-brand">{{ \App\Support\MoneyFormat::compact($stats['reached']) }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Campaign engagement</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-brand">{{ $stats['engagement'] }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Conversions</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-brand">{{ \App\Support\MoneyFormat::compact($stats['conversions']) }}</p>
        </div>
    </div>
    <div class="grid grid-cols-3 gap-3 mb-6">
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Active offers</p>
            <p class="mt-1 text-xl font-bold tabular-nums text-brand">{{ \App\Support\MoneyFormat::compact($stats['offers']) }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Affiliate performance</p>
            <p class="mt-1 text-xl font-bold tabular-nums text-brand">{{ \App\Support\MoneyFormat::compact($stats['affiliates']) }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Active demos</p>
            <p class="mt-1 text-xl font-bold tabular-nums text-brand">{{ \App\Support\MoneyFormat::compact($stats['demos']) }}</p>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-5 mb-6">
        <section class="rounded-2xl bg-white ring-1 ring-brand/10 p-5">
            <h2 class="text-sm font-bold text-gray-900">Running now</h2>
            <div class="mt-3 space-y-2">
                @forelse ($running as $campaign)
                    <a href="{{ route('admin.promotions.show', $campaign) }}" class="block rounded-xl ring-1 ring-gray-100 px-3 py-2 hover:bg-brand-muted/30">
                        <p class="text-sm font-semibold">{{ $campaign->name }}</p>
                        <p class="text-xs text-gray-500">{{ $campaign->code }} · {{ $campaign->status }} · reach {{ \App\Support\MoneyFormat::compact($campaign->metadata['results']['reach'] ?? 0) }}</p>
                    </a>
                @empty
                    <p class="text-sm text-gray-500">No active campaigns.</p>
                @endforelse
                @foreach ($runningOffers as $offer)
                    <a href="{{ route('admin.growth.offers.index') }}" class="block rounded-xl ring-1 ring-gray-100 px-3 py-2 hover:bg-brand-muted/30">
                        <p class="text-sm font-semibold">{{ $offer->title }}</p>
                        <p class="text-xs text-gray-500">Offer · {{ $offer->tier }}</p>
                    </a>
                @endforeach
            </div>
        </section>
        <section class="rounded-2xl bg-white ring-1 ring-brand/10 p-5">
            <h2 class="text-sm font-bold text-gray-900">Needs attention</h2>
            <ul class="mt-3 space-y-2 text-sm text-gray-700">
                @forelse (collect($attention)->where('show', true) as $item)
                    <li>
                        <a href="{{ $item['url'] ?? '#' }}" class="block rounded-xl bg-amber-50 ring-1 ring-amber-100 px-3 py-2">{{ $item['label'] }}</a>
                    </li>
                @empty
                    <li class="text-gray-500">Nothing needs attention right now.</li>
                @endforelse
            </ul>
        </section>
    </div>

    <p class="text-xs text-gray-500">Rules, commissions, eligibility and channels stay in Settings Hub → Growth. This workspace only performs the work.</p>
</x-admin.layout>
