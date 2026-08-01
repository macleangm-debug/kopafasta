<x-site.investor-layout title="Transactions — Investor" active="transactions">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl lg:text-3xl font-bold tracking-tight">Transactions</h1>
            <p class="text-gray-500 text-sm mt-1">Every deposit, withdrawal, investment, payout and fee.</p>
        </div>
    </div>

    <div class="flex flex-wrap gap-2 mb-4">
        @foreach (['' => 'All', 'deposit' => 'Deposits', 'withdrawal' => 'Withdrawals', 'investment' => 'Investments', 'return' => 'Returns', 'fee' => 'Fees'] as $k => $label)
            <a href="?type={{ $k }}" class="rounded-full px-3 py-1.5 text-xs font-semibold border
                {{ $type === $k ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-gray-200 hover:bg-brand-muted/30' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="glass-card rounded-2xl ring-1 ring-brand/10 overflow-hidden">
        @if ($transactions->isEmpty())
            <div class="p-10 text-center text-gray-500 text-sm">No transactions yet.</div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-brand-muted/30 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="text-left px-4 py-3">Date</th>
                        <th class="text-left px-4 py-3">Reference</th>
                        <th class="text-left px-4 py-3">Type</th>
                        <th class="text-left px-4 py-3">Channel</th>
                        <th class="text-left px-4 py-3">Status</th>
                        <th class="text-right px-4 py-3">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($transactions as $t)
                        <tr class="hover:bg-brand-muted/30">
                            <td class="px-4 py-3 text-slate-600">{{ $t->created_at->format('d M Y') }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $t->reference }}</td>
                            <td class="px-4 py-3 capitalize font-semibold">{{ $t->type }}</td>
                            <td class="px-4 py-3 capitalize text-slate-600">{{ str_replace('_',' ',$t->channel) }}</td>
                            <td class="px-4 py-3">
                                <span class="text-[11px] font-semibold uppercase rounded px-2 py-0.5
                                    {{ $t->status === 'completed' ? 'bg-emerald-100 text-brand' :
                                       ($t->status === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">{{ $t->status }}</span>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold {{ in_array($t->type, ['return','deposit']) ? 'text-brand' : '' }}">TZS {{ $fmt($t->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <div class="mt-6">{{ $transactions->links() }}</div>
</x-site.investor-layout>
