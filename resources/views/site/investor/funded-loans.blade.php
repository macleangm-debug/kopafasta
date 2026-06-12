<x-site.investor-layout title="Funded loans — Investor" active="funded">
    <div class="mb-6">
        <h1 class="text-2xl lg:text-3xl font-bold tracking-tight">Funded loans</h1>
        <p class="text-slate-500 text-sm mt-1">Capital deployed into live loans, outstanding exposure, and interest earned.</p>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wider text-slate-500 font-semibold">Capital invested</p>
            <p class="text-xl font-bold text-slate-900 mt-1">TZS {{ number_format($capitalMetrics['capital_invested'], 0) }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wider text-slate-500 font-semibold">Outstanding exposure</p>
            <p class="text-xl font-bold text-amber-700 mt-1">TZS {{ number_format($capitalMetrics['outstanding_exposure'], 0) }}</p>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wider text-emerald-800 font-semibold">Interest earned (you)</p>
            <p class="text-xl font-bold text-emerald-900 mt-1">TZS {{ number_format($capitalMetrics['interest_earned_partner'], 0) }}</p>
            <p class="text-[10px] text-emerald-700 mt-1">{{ \App\Services\CapitalPartnerAllocationService::PARTNER_INTEREST_SHARE }}% partner share</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wider text-slate-500 font-semibold">Active loans</p>
            <p class="text-xl font-bold text-slate-900 mt-1">{{ $capitalMetrics['active_loans'] }}</p>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        @if ($allocations->isEmpty())
            <p class="p-8 text-sm text-slate-500 text-center">No loan allocations yet. Capital is deployed when Finance disburses approved loans.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="text-left px-4 py-3">Loan</th>
                            <th class="text-left px-4 py-3">Borrower</th>
                            <th class="text-right px-4 py-3">Allocated</th>
                            <th class="text-right px-4 py-3">Exposure</th>
                            <th class="text-right px-4 py-3">Your interest</th>
                            <th class="text-left px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($allocations as $row)
                            <tr>
                                <td class="px-4 py-3 font-mono font-semibold">{{ $row['loan_number'] }}</td>
                                <td class="px-4 py-3">{{ $row['borrower'] }}</td>
                                <td class="px-4 py-3 text-right font-mono">{{ format_money($row['allocated_principal']) }}</td>
                                <td class="px-4 py-3 text-right font-mono">{{ format_money($row['outstanding_exposure']) }}</td>
                                <td class="px-4 py-3 text-right font-mono text-emerald-700">{{ format_money($row['interest_earned_partner']) }}</td>
                                <td class="px-4 py-3 capitalize">{{ $row['status'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-site.investor-layout>
