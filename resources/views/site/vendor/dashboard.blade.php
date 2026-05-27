<x-site.vendor-layout title="Vendor dashboard" active="dashboard">
    @php
        $fmt = fn ($n) => 'TZS '.number_format((int) $n);
        $catLabels = [
            'gps_installer'      => 'GPS Installer',
            'insurance'          => 'Insurance Provider',
            'valuer'             => 'Valuer',
            'towing'             => 'Towing',
            'yard'               => 'Yard',
            'auctioneer'         => 'Auctioneer',
        ];
    @endphp

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight">Hi, {{ $vendor->name }}</h1>
            <p class="text-sm text-gray-500">{{ $catLabels[$vendor->category] ?? ucfirst($vendor->category) }} · <span class="font-mono">{{ $vendor->vendor_number }}</span></p>
        </div>
        <a href="{{ route('site.vendor.tasks') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 text-white font-semibold px-5 py-3 hover:bg-indigo-700">
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
                <a href="{{ route('site.vendor.tasks') }}" class="text-sm text-indigo-600 hover:underline">All</a>
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
                        <a href="{{ route('site.vendor.task', $t) }}" class="flex items-center justify-between py-3 hover:bg-gray-50 -mx-2 px-2 rounded-lg">
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
                <a href="{{ route('site.vendor.notifications') }}" class="text-sm text-indigo-600 hover:underline">All</a>
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
