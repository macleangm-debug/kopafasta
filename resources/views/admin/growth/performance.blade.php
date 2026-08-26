<x-admin.layout title="Marketing performance" heading="Performance" subheading="Overview · Campaigns · Affiliates · Offers · Acquisition. One reporting engine, extra entry points.">
    <div class="flex flex-wrap gap-2 mb-5 text-sm">
        <span class="rounded-full bg-brand-muted text-brand px-3 py-1 font-semibold">Overview</span>
        <a href="{{ route('admin.promotions.index') }}" class="rounded-full bg-white ring-1 ring-gray-200 px-3 py-1">Campaigns</a>
        <a href="{{ route('admin.reports.affiliate-marketing-attribution') }}" class="rounded-full bg-white ring-1 ring-gray-200 px-3 py-1">Affiliates</a>
        <a href="{{ route('admin.growth.offers.index') }}" class="rounded-full bg-white ring-1 ring-gray-200 px-3 py-1">Offers</a>
        <a href="{{ route('admin.reports.customers') }}" class="rounded-full bg-white ring-1 ring-gray-200 px-3 py-1">Acquisition</a>
    </div>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Active campaigns</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-brand">{{ \App\Support\MoneyFormat::compact($stats['campaigns']) }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Reached</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-brand">{{ \App\Support\MoneyFormat::compact($stats['reached']) }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Conversions</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-brand">{{ \App\Support\MoneyFormat::compact($stats['converted']) }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Active offers</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-brand">{{ \App\Support\MoneyFormat::compact($stats['offers']) }}</p>
        </div>
    </div>
    <div class="grid sm:grid-cols-2 gap-4">
        <a href="{{ route('admin.reports.affiliate-marketing-attribution') }}" class="rounded-2xl bg-white ring-1 ring-brand/10 p-5">Marketing attribution report →</a>
        <a href="{{ route('admin.reports.affiliate-capital-attribution') }}" class="rounded-2xl bg-white ring-1 ring-brand/10 p-5">Capital attribution report →</a>
        <a href="{{ route('admin.reports.affiliate-fraud') }}" class="rounded-2xl bg-white ring-1 ring-brand/10 p-5">Affiliate fraud report →</a>
        <a href="{{ route('admin.reports.customers') }}" class="rounded-2xl bg-white ring-1 ring-brand/10 p-5">Customers report →</a>
    </div>
</x-admin.layout>
