<x-site.affiliate-layout title="Affiliate dashboard" active="dashboard">
    @if (! $canShare)
        <div class="mb-6 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900">
            Sharing is paused while your affiliate account is on watchlist or suspended.
        </div>
    @endif

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="glass-card overflow-hidden">
                <div class="bg-gradient-to-br from-amber-50 via-white to-brand-muted/30 p-6 sm:p-8">
                    <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-2">Affiliate programme</p>
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 tracking-tight">Affiliate dashboard</h1>
                    <p class="text-sm text-gray-600 mt-2">Share your referral link and track conversions.</p>

                    <div class="mt-5 inline-flex items-center gap-2 rounded-xl bg-white ring-1 ring-gray-200 px-4 py-3" x-data="{ copied: false }">
                        <span class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">Code</span>
                        <span class="font-mono text-sm font-bold text-brand">{{ $links['affiliate_code'] ?? $vendor->affiliate_code }}</span>
                        <button type="button"
                                @click="navigator.clipboard.writeText(@js($links['affiliate_code'] ?? $vendor->affiliate_code)); copied = true; setTimeout(() => copied = false, 2000)"
                                class="ml-2 text-xs font-semibold text-brand hover:underline">
                            <span x-show="!copied">Copy</span>
                            <span x-show="copied" x-cloak>Copied</span>
                        </button>
                    </div>

                    @if ($canShare)
                        <div class="mt-5 space-y-2 text-sm break-all">
                            <p class="text-gray-700"><span class="font-semibold text-gray-500">Link:</span> {{ $links['affiliate_link'] ?? '' }}</p>
                            <p class="text-gray-500"><span class="font-semibold">Register:</span> {{ $links['registration_link'] ?? '' }}</p>
                        </div>
                        <div class="mt-5">
                            <x-site.referral-share
                                :link="$links['affiliate_link'] ?? ''"
                                :code="$links['affiliate_code'] ?? $vendor->affiliate_code"
                                :message="$shareMessage"
                            />
                        </div>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                @foreach ([
                    ['Clicks', $stats['clicks'] ?? 0, 'sky'],
                    ['Registrations', $stats['registrations'] ?? 0, 'emerald'],
                    ['Applications', $stats['applications'] ?? 0, 'violet'],
                    ['Commissions', format_money($stats['commissions'] ?? 0), 'amber'],
                ] as [$label, $value, $tone])
                    <div class="glass-card p-4">
                        <p class="text-[11px] uppercase tracking-wide text-gray-500 font-semibold">{{ $label }}</p>
                        <p class="text-xl sm:text-2xl font-bold text-gray-900 mt-1 tabular-nums">{{ $value }}</p>
                    </div>
                @endforeach
            </div>

            <div class="grid sm:grid-cols-2 gap-3">
                <a href="{{ route('site.affiliate.referrals') }}" class="glass-card p-5 hover:shadow-md transition group">
                    <p class="text-sm font-bold text-gray-900 group-hover:text-brand">View referral activity →</p>
                    <p class="text-xs text-gray-500 mt-1">Clicks, sign-ups, and application events</p>
                </a>
                <a href="{{ route('site.affiliate.wallet') }}" class="glass-card p-5 hover:shadow-md transition group">
                    <p class="text-sm font-bold text-gray-900 group-hover:text-brand">Open commission wallet →</p>
                    <p class="text-xs text-gray-500 mt-1">Payout requests and dispute entries</p>
                </a>
            </div>
        </div>

        <aside class="space-y-4">
            <div class="glass-card p-5">
                <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Status</p>
                <p class="text-lg font-bold mt-1">{{ $lifecycleLabel }}</p>
                @if ($leaderboardRank)
                    <p class="text-sm text-gray-500 mt-2">Leaderboard rank: <span class="font-semibold text-brand">#{{ $leaderboardRank }}</span></p>
                @endif
            </div>
            <div class="glass-card p-5">
                <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Wallet snapshot</p>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Pending</dt><dd class="font-semibold tabular-nums">{{ format_money($wallet['pending'] ?? 0) }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-gray-500">Approved</dt><dd class="font-semibold tabular-nums">{{ format_money($wallet['approved'] ?? 0) }}</dd></div>
                </dl>
                <a href="{{ route('site.affiliate.wallet') }}" class="inline-flex mt-4 text-sm font-semibold text-brand hover:underline">Open wallet →</a>
            </div>
        </aside>
    </div>
</x-site.affiliate-layout>
