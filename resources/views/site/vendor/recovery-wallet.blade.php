<x-site.vendor-layout title="Recovery commission wallet" active="recovery_wallet">
    @include('site.vendor._recovery-kpi', ['kpi' => $recoveryKpi, 'wallet' => $summary, 'compact' => true])

    <h1 class="text-2xl font-extrabold mb-1">Commission wallet</h1>
    <p class="text-sm text-gray-500 mb-5">Recovery commissions accrue when you complete a case. Dispute any incorrect entry before payout.</p>

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
            <p class="text-xs text-gray-500 uppercase">Pending</p>
            <p class="text-2xl font-extrabold text-amber-700 mt-1">{{ format_money($summary['pending'] ?? 0) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $summary['counts']['pending'] ?? 0 }} payment(s)</p>
        </div>
        <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
            <p class="text-xs text-gray-500 uppercase">Approved</p>
            <p class="text-2xl font-extrabold text-blue-700 mt-1">{{ format_money($summary['approved'] ?? 0) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $summary['counts']['approved'] ?? 0 }} payment(s)</p>
        </div>
        <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
            <p class="text-xs text-gray-500 uppercase">Paid</p>
            <p class="text-2xl font-extrabold text-emerald-700 mt-1">{{ format_money($summary['paid'] ?? 0) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $summary['counts']['paid'] ?? 0 }} payment(s)</p>
        </div>
        <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
            <p class="text-xs text-gray-500 uppercase">Disputed</p>
            <p class="text-2xl font-extrabold text-red-700 mt-1">{{ format_money($summary['disputed'] ?? 0) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $summary['counts']['disputed'] ?? 0 }} payment(s)</p>
        </div>
    </div>

    </div>

    <form method="POST" action="{{ route('site.partner.recovery-wallet.payout-request') }}" class="mb-6 glass-card rounded-2xl ring-1 ring-brand/10 p-5 grid sm:grid-cols-3 gap-3 items-end">
        @csrf
        <div>
            <label class="block text-xs text-gray-600 mb-1">Request payout (TZS)</label>
            <input type="number" name="amount" min="1" step="1" required class="w-full rounded-lg border-gray-300 text-sm px-3 py-2">
            @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-2">
            <label class="block text-xs text-gray-600 mb-1">Notes (optional)</label>
            <input type="text" name="notes" class="w-full rounded-lg border-gray-300 text-sm px-3 py-2">
        </div>
        <button type="submit" class="sm:col-span-3 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm w-full sm:w-auto">Submit payout request</button>
    </form>

    <div class="glass-card rounded-2xl ring-1 ring-brand/10 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="text-left px-4 py-3">Invoice</th>
                    <th class="text-left px-4 py-3">Description</th>
                    <th class="text-left px-4 py-3">Amount</th>
                    <th class="text-left px-4 py-3">Status</th>
                    <th class="text-left px-4 py-3">Date</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($payments as $payment)
                    <tr class="hover:bg-gray-50 align-top">
                        <td class="px-4 py-3 font-mono text-xs">{{ $payment->invoice_number }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $payment->description ?? 'Recovery commission' }}</td>
                        <td class="px-4 py-3 font-semibold">{{ format_money($payment->amount) }}</td>
                        <td class="px-4 py-3">
                            @php
                                $statusClass = match ($payment->status) {
                                    'paid' => 'bg-emerald-100 text-emerald-700',
                                    'approved' => 'bg-blue-100 text-blue-700',
                                    'disputed' => 'bg-red-100 text-red-700',
                                    'cancelled' => 'bg-gray-100 text-gray-600',
                                    default => 'bg-amber-100 text-amber-700',
                                };
                            @endphp
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $statusClass }}">
                                {{ ucfirst($payment->status) }}
                            </span>
                            @if ($payment->status === 'disputed' && $payment->dispute_reason)
                                <p class="text-xs text-gray-500 mt-1">{{ Str::limit($payment->dispute_reason, 80) }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $payment->paid_at?->format('d M Y') ?? $payment->approved_at?->format('d M Y') ?? $payment->created_at?->format('d M Y') }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if (in_array($payment->status, ['pending', 'approved'], true))
                                <details class="text-left">
                                    <summary class="text-xs font-semibold text-red-700 cursor-pointer">Dispute</summary>
                                    <form method="POST" action="{{ route('site.partner.recovery-wallet.dispute', $payment) }}" class="mt-2 space-y-2">
                                        @csrf
                                        <textarea name="reason" rows="2" required maxlength="1000"
                                                  class="w-full min-w-[12rem] rounded-lg border-gray-300 text-xs"
                                                  placeholder="Explain why this commission is incorrect…"></textarea>
                                        <button type="submit" class="text-xs font-semibold text-red-700 underline">Submit dispute</button>
                                    </form>
                                </details>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">No recovery commissions yet. Complete cases to earn commission.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $payments->links() }}</div>
</x-site.vendor-layout>
