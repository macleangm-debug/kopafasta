<x-site.affiliate-layout title="Commission wallet" active="wallet">
    <div class="mb-6">
        <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-1">Payouts</p>
        <h1 class="text-2xl font-bold">Commission wallet</h1>
        <p class="text-sm text-gray-500 mt-1">Request payout of approved commissions. Dispute incorrect entries before payout.</p>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'paid' => 'Paid', 'disputed' => 'Disputed'] as $key => $label)
            <div class="glass-card p-5">
                <p class="text-xs text-gray-500 uppercase font-semibold">{{ $label }}</p>
                <p class="text-xl sm:text-2xl font-extrabold mt-1 tabular-nums">{{ format_money($summary[$key] ?? 0) }}</p>
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('site.affiliate.wallet.payout-request') }}" class="mb-6 glass-card p-5 grid sm:grid-cols-3 gap-3 items-end">
        @csrf
        <div>
            <x-site.numeric-input name="amount" label="Request payout (TZS)" :required="true" min="1" step="1" :money="true" />
        </div>
        <div class="sm:col-span-2">
            <label class="block text-xs text-gray-600 mb-1">Notes (optional)</label>
            <input type="text" name="notes" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
        </div>
        <button type="submit" class="sm:col-span-3 bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-5 py-3 rounded-xl text-sm w-full sm:w-auto">Submit payout request</button>
    </form>

    <div class="glass-card overflow-hidden">
        <div class="hidden md:block overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50/80 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="text-left px-4 py-3">Invoice</th>
                        <th class="text-left px-4 py-3">Amount</th>
                        <th class="text-left px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($payments as $payment)
                        <tr class="align-top">
                            <td class="px-4 py-3 font-mono text-xs">{{ $payment->invoice_number }}</td>
                            <td class="px-4 py-3 font-semibold tabular-nums">{{ format_money($payment->amount) }}</td>
                            <td class="px-4 py-3">{{ ucfirst($payment->status) }}</td>
                            <td class="px-4 py-3">
                                @if (in_array($payment->status, ['pending', 'approved'], true))
                                    <form method="POST" action="{{ route('site.affiliate.wallet.dispute', $payment) }}" class="space-y-1">
                                        @csrf
                                        <input type="text" name="reason" required placeholder="Dispute reason" class="w-full rounded border-gray-300 text-xs px-2 py-1">
                                        <button type="submit" class="text-xs font-semibold text-red-600 hover:underline">Dispute</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No commission payments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile cards --}}
        <div class="md:hidden divide-y divide-gray-100">
            @forelse ($payments as $payment)
                <div class="p-4 space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-mono text-xs text-gray-500">{{ $payment->invoice_number }}</p>
                            <p class="text-lg font-bold tabular-nums mt-1">{{ format_money($payment->amount) }}</p>
                        </div>
                        <span class="text-xs font-semibold rounded-full px-2.5 py-1 bg-gray-100 text-gray-700">{{ ucfirst($payment->status) }}</span>
                    </div>
                    @if (in_array($payment->status, ['pending', 'approved'], true))
                        <form method="POST" action="{{ route('site.affiliate.wallet.dispute', $payment) }}" class="space-y-2">
                            @csrf
                            <input type="text" name="reason" required placeholder="Dispute reason" class="w-full rounded-lg border-gray-300 text-sm px-3 py-2">
                            <button type="submit" class="text-sm font-semibold text-red-600">Dispute entry</button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="p-8">
                    <x-site.empty-state icon="💰" title="No commission payments yet" description="Commissions appear here once referrals convert." class="!p-6 border-0 shadow-none" />
                </div>
            @endforelse
        </div>
    </div>
    <div class="mt-4">{{ $payments->links() }}</div>
</x-site.affiliate-layout>
