<x-admin.layout title="Rewards" heading="Rewards" subheading="Operate the catalogue, watch points, and spot redemptions. Rules live in Settings.">
    <div class="flex flex-wrap gap-2 mb-6">
        <a href="{{ route('admin.settings.engagement.loyalty-points') }}" class="inline-flex items-center rounded-xl bg-white ring-1 ring-brand/15 text-sm font-semibold px-4 py-2.5">Edit catalogue in Settings</a>
        <a href="{{ route('admin.settings.referrals') }}" class="inline-flex items-center rounded-xl bg-white ring-1 ring-brand/15 text-sm font-semibold px-4 py-2.5">Referral earning rules</a>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Points issued</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-brand">{{ number_format($issued) }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Points redeemed</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-brand">{{ number_format($redeemed) }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Active entitlements</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-brand">{{ number_format($active) }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Applied at payment</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-brand">{{ number_format($used) }}</p>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-5">
        <section class="rounded-2xl bg-white ring-1 ring-brand/10 p-5">
            <h2 class="text-sm font-bold text-gray-900">Catalogue (from Settings)</h2>
            <ul class="mt-3 space-y-2">
                @foreach ($catalog as $row)
                    <li class="rounded-xl ring-1 ring-gray-100 px-3 py-2">
                        <p class="text-sm font-semibold">{{ $row['label'] }}</p>
                        <p class="text-xs text-gray-500">{{ $row['points'] }} pts · {{ $row['audience'] }} · {{ $row['benefit_type'] }}</p>
                    </li>
                @endforeach
            </ul>
        </section>
        <section class="rounded-2xl bg-white ring-1 ring-brand/10 p-5">
            <h2 class="text-sm font-bold text-gray-900">Recent unlocks</h2>
            <ul class="mt-3 space-y-2">
                @forelse ($redemptions as $row)
                    <li class="rounded-xl ring-1 ring-gray-100 px-3 py-2 text-sm">
                        <span class="font-semibold">{{ $row->label }}</span>
                        <span class="text-gray-500">· {{ $row->status }} · {{ $row->points_spent }} pts</span>
                    </li>
                @empty
                    <li class="text-sm text-gray-500">No redemptions yet.</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-admin.layout>
