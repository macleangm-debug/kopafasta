<x-admin.layout title="Affiliate marketing" heading="Affiliate marketing" subheading="Performance view. Partner KYC and lifecycle stay in Partners.">
    <div class="grid grid-cols-2 gap-3 mb-5">
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Affiliate partners</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-brand">{{ \App\Support\MoneyFormat::compact($affiliateCount) }}</p>
        </div>
        <a href="{{ route('admin.reports.affiliate-marketing-attribution') }}" class="rounded-2xl bg-white ring-1 ring-brand/10 p-4 hover:ring-brand/30">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Attribution</p>
            <p class="mt-1 text-sm font-semibold text-brand">Open report →</p>
        </a>
    </div>
    <div class="grid sm:grid-cols-2 gap-4">
        <a href="{{ route('admin.reports.affiliate-marketing-attribution') }}" class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 hover:ring-brand/30">
            <p class="font-semibold">Marketing attribution</p>
            <p class="text-sm text-gray-600 mt-1">Clicks, registrations and campaign attribution. Same report engine as Reports.</p>
        </a>
        <a href="{{ route('admin.reports.partner-performance') }}" class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 hover:ring-brand/30">
            <p class="font-semibold">Affiliate performance</p>
            <p class="text-sm text-gray-600 mt-1">Top affiliates and conversion. Canonical partner records stay under Partners.</p>
        </a>
        <a href="{{ route('admin.reports.affiliate-fraud') }}" class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 hover:ring-brand/30">
            <p class="font-semibold">Affiliate fraud</p>
            <p class="text-sm text-gray-600 mt-1">Fraud thresholds are configured in Settings → Growth → Affiliates.</p>
        </a>
        <a href="{{ route('admin.partners.index') }}" class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 hover:ring-brand/30">
            <p class="font-semibold">Manage affiliate →</p>
            <p class="text-sm text-gray-600 mt-1">Deep-link into the Partner operational record for KYC and lifecycle.</p>
        </a>
    </div>
</x-admin.layout>
