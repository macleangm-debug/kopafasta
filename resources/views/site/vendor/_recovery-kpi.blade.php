@props(['kpi', 'wallet' => null, 'compact' => false])

@if ($kpi)
    <div class="{{ $compact ? 'mb-5' : 'mb-6' }} rounded-2xl border border-brand/200 bg-gradient-to-br from-indigo-50 to-white p-5 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <div>
                <h2 class="text-lg font-bold text-gray-900">Recovery performance</h2>
                <p class="text-sm text-gray-600">Your assigned cases, recovery rate, and commission summary.</p>
            </div>
            <a href="{{ route('site.partner.recovery-wallet') }}" class="text-sm font-semibold text-brand hover:underline shrink-0">
                Commission wallet →
            </a>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
            @foreach ([
                ['Assigned cases', $kpi['assigned_cases'] ?? 0],
                ['Recovered cases', $kpi['recovered_cases'] ?? 0],
                ['Recovery rate', ($kpi['recovery_rate'] ?? 0).'%'],
                ['Commission earned', format_money($kpi['commission_earned'] ?? 0)],
                ['Avg resolution', isset($kpi['avg_resolution_days']) ? $kpi['avg_resolution_days'].' days' : '—'],
            ] as [$label, $value])
                <div class="rounded-xl bg-white ring-1 ring-brand/100 p-3">
                    <p class="text-[11px] uppercase tracking-wide text-gray-500">{{ $label }}</p>
                    <p class="text-lg font-bold text-gray-900 mt-1">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        @if ($wallet)
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4 pt-4 border-t border-brand/100">
                <div class="rounded-lg bg-white/80 ring-1 ring-gray-100 px-3 py-2 text-sm">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500">Pending</p>
                    <p class="font-bold text-amber-700">{{ format_money($wallet['pending'] ?? 0) }}</p>
                </div>
                <div class="rounded-lg bg-white/80 ring-1 ring-gray-100 px-3 py-2 text-sm">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500">Approved</p>
                    <p class="font-bold text-blue-700">{{ format_money($wallet['approved'] ?? 0) }}</p>
                </div>
                <div class="rounded-lg bg-white/80 ring-1 ring-gray-100 px-3 py-2 text-sm">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500">Paid</p>
                    <p class="font-bold text-emerald-700">{{ format_money($wallet['paid'] ?? 0) }}</p>
                </div>
                <div class="rounded-lg bg-white/80 ring-1 ring-gray-100 px-3 py-2 text-sm">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500">Disputed</p>
                    <p class="font-bold text-red-700">{{ format_money($wallet['disputed'] ?? 0) }}</p>
                </div>
            </div>
        @endif
    </div>
@endif
