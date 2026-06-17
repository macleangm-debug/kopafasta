<x-site.vendor-layout title="Partner dashboard" active="dashboard">
    @php
        $catLabels = [
            'gps_installer'      => 'GPS Installer',
            'insurance'          => 'Insurance Provider',
            'valuer'             => 'Valuer',
            'towing'             => 'Towing',
            'yard'               => 'Yard',
            'auctioneer'         => 'Auctioneer',
            'affiliate'          => 'Affiliate Partner',
        ];
    @endphp

    @if ($vendor->category === 'affiliate' && $affiliateStats)
        @php
            $affiliateKycApproved = in_array($vendor->affiliate_kyc_status, ['verified', 'approved'], true);
            $affiliateKycSubmitted = in_array($vendor->affiliate_kyc_status, ['submitted', 'verified', 'approved'], true);
        @endphp
        <div class="mb-6 rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white p-6">
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">Affiliate program</h2>
                    <p class="text-sm text-gray-600 mt-1">Share your code and earn commission on referred customer fees.</p>
                    <p class="mt-3 font-mono text-sm font-semibold text-amber-800">{{ $affiliateLinks['affiliate_code'] ?? $vendor->affiliate_code }}</p>
                    <p class="text-xs text-gray-500 mt-1">KYC: {{ ucfirst($vendor->affiliate_kyc_status ?? 'pending') }}</p>
                </div>
                <a href="{{ route('site.partner.profile') }}" class="text-sm font-semibold text-indigo-600 hover:underline shrink-0">Manage profile & KYC →</a>
            </div>

            @if (! $affiliateKycSubmitted)
                <div class="mt-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-900">
                    <p class="font-semibold">Complete affiliate KYC to activate sharing</p>
                    <p class="mt-1 text-xs">Upload your selfie and national ID on your profile. Public verification stays disabled until our team approves your documents.</p>
                </div>
            @elseif (! $affiliateKycApproved)
                <div class="mt-4 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900">
                    <p class="font-semibold">KYC under review</p>
                    <p class="mt-1 text-xs">Your documents were submitted. Share links unlock after approval; the public verification page will show as verified once approved.</p>
                </div>
            @endif

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-5">
                @foreach ([
                    ['Clicks', $affiliateStats['clicks']],
                    ['Registrations', $affiliateStats['registrations']],
                    ['Applications', $affiliateStats['applications']],
                    ['Commissions', format_money($affiliateStats['commissions'])],
                ] as [$label, $value])
                    <div class="rounded-xl bg-white ring-1 ring-amber-100 p-3">
                        <p class="text-[11px] uppercase tracking-wide text-gray-500">{{ $label }}</p>
                        <p class="text-lg font-bold text-gray-900 mt-1">{{ $value }}</p>
                    </div>
                @endforeach
            </div>

            @if ($affiliateKycApproved && $affiliateShare && $affiliateLinks)
                <div class="mt-5 space-y-3">
                    <div>
                        <p class="text-xs font-medium text-gray-500 mb-1">Share message</p>
                        <p class="text-sm text-gray-800 bg-white rounded-lg ring-1 ring-gray-200 p-3" id="affiliate-share-text">{{ $affiliateShare }}</p>
                        <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('affiliate-share-text').textContent)"
                                class="mt-2 text-xs font-semibold text-amber-700 hover:underline">Copy message</button>
                    </div>
                    <div class="flex flex-wrap gap-3 text-sm">
                        <a href="{{ $affiliateLinks['registration_link'] }}" class="font-semibold text-indigo-600 hover:underline" target="_blank" rel="noopener">Registration link</a>
                        <a href="{{ $affiliateLinks['verify_link'] }}" class="font-semibold text-indigo-600 hover:underline" target="_blank" rel="noopener">Verification page</a>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight">Hi, {{ $vendor->name }}</h1>
            <p class="text-sm text-gray-500">{{ $catLabels[$vendor->category] ?? ucfirst($vendor->category) }} · <span class="font-mono">{{ $vendor->vendor_number }}</span></p>
        </div>
        <a href="{{ route('site.partner.tasks') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 text-white font-semibold px-5 py-3 hover:bg-indigo-700">
            View tasks
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
        </a>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
        @foreach ([
            ['Assigned',        $stats['assigned'],            'text-amber-700'],
            ['In Progress',     $stats['in_progress'],         'text-indigo-700'],
            ['Done this month', $stats['completed_mo'],        'text-emerald-700'],
            ['Pending Pay',     $fmt($stats['payments_pend']), 'text-orange-700'],
            ['Total Earnings',  $fmt($stats['earnings']),      'text-sky-700'],
        ] as [$label, $value, $color])
            <div class="rounded-2xl border border-gray-200 bg-white p-4">
                <p class="text-xs uppercase tracking-wide text-gray-500">{{ $label }}</p>
                <p class="text-xl font-extrabold {{ $color }} mt-1">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 rounded-2xl border border-gray-200 bg-white p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-bold">Upcoming tasks</h2>
                <a href="{{ route('site.partner.tasks') }}" class="text-sm text-indigo-600 hover:underline">All</a>
            </div>
            @if ($upcoming->isEmpty())
                <p class="text-sm text-gray-500">No assigned or in-progress tasks right now.</p>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach ($upcoming as $t)
                        @php
                            $badge = $t->status === 'assigned'
                                ? 'bg-amber-100 text-amber-700'
                                : ($t->status === 'in_progress' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-700');
                        @endphp
                        <a href="{{ route('site.partner.task', $t) }}" class="flex items-center justify-between py-3 hover:bg-gray-50 -mx-2 px-2 rounded-lg">
                            <div class="min-w-0">
                                <p class="font-semibold text-sm truncate">{{ ucfirst(str_replace('_',' ', $t->task_type)) }} · {{ $t->customer_name ?: '—' }}</p>
                                <p class="text-xs text-gray-500 truncate">{{ $t->location ?: '—' }} · Due {{ $t->due_at ? $t->due_at->format('d M H:i') : 'flexible' }}</p>
                            </div>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $badge }} shrink-0 ml-3">{{ str_replace('_',' ', $t->status) }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-bold">Notifications</h2>
                <a href="{{ route('site.partner.notifications') }}" class="text-sm text-indigo-600 hover:underline">All</a>
            </div>
            @if ($notifications->isEmpty())
                <p class="text-sm text-gray-500">No notifications yet.</p>
            @else
                <ul class="space-y-3">
                    @foreach ($notifications as $n)
                        <li class="text-sm">
                            <p class="text-gray-900">{{ $n->message ?? $n->subject ?? 'Notification' }}</p>
                            <p class="text-xs text-gray-500">{{ $n->created_at?->diffForHumans() }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-site.vendor-layout>
