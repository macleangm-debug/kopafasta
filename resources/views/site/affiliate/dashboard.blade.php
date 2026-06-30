<x-site.affiliate-layout title="Affiliate dashboard" active="dashboard">
    @if (! $canShare)
        <div class="mb-6 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900">
            Sharing is paused while your affiliate account is on watchlist or suspended.
        </div>
    @endif

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white p-6">
                <h1 class="text-2xl font-bold text-gray-900">Affiliate dashboard</h1>
                <p class="text-sm text-gray-600 mt-1">Share your referral link and track conversions.</p>
                <p class="mt-4 font-mono text-sm font-semibold text-amber-800">{{ $links['affiliate_code'] ?? $vendor->affiliate_code }}</p>
                @if ($canShare)
                    <div class="mt-4 space-y-2 text-sm">
                        <p class="text-gray-700 break-all">{{ $links['affiliate_link'] ?? '' }}</p>
                        <p class="text-gray-500 break-all">{{ $links['registration_link'] ?? '' }}</p>
                    </div>
                    <p class="mt-4 text-sm bg-white rounded-lg ring-1 ring-gray-200 p-3" id="affiliate-share-text">{{ $shareMessage }}</p>
                @endif
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                @foreach ([
                    ['Clicks', $stats['clicks'] ?? 0],
                    ['Registrations', $stats['registrations'] ?? 0],
                    ['Applications', $stats['applications'] ?? 0],
                    ['Commissions', format_money($stats['commissions'] ?? 0)],
                ] as [$label, $value])
                    <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
                        <p class="text-[11px] uppercase tracking-wide text-gray-500">{{ $label }}</p>
                        <p class="text-xl font-bold text-gray-900 mt-1">{{ $value }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <aside class="space-y-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <p class="text-xs uppercase tracking-wide text-gray-500">Status</p>
                <p class="text-lg font-bold mt-1">{{ $lifecycleLabel }}</p>
                @if ($leaderboardRank)
                    <p class="text-sm text-gray-500 mt-2">Leaderboard rank: #{{ $leaderboardRank }}</p>
                @endif
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <p class="text-xs uppercase tracking-wide text-gray-500">Wallet snapshot</p>
                <p class="text-sm text-gray-600 mt-2">Pending: {{ format_money($wallet['pending'] ?? 0) }}</p>
                <p class="text-sm text-gray-600">Approved: {{ format_money($wallet['approved'] ?? 0) }}</p>
                <a href="{{ route('site.affiliate.wallet') }}" class="inline-flex mt-4 text-sm font-semibold text-amber-700 hover:underline">Open wallet →</a>
            </div>
        </aside>
    </div>
</x-site.affiliate-layout>
