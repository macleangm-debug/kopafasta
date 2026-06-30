<x-site.affiliate-layout title="Commission wallet" active="wallet">
    <h1 class="text-2xl font-bold mb-1">Commission wallet</h1>
    <p class="text-sm text-gray-500 mb-6">Request payout of approved commissions. Dispute incorrect entries before payout.</p>

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'paid' => 'Paid', 'disputed' => 'Disputed'] as $key => $label)
            <div class="rounded-2xl border border-gray-200 bg-white p-5">
                <p class="text-xs text-gray-500 uppercase">{{ $label }}</p>
                <p class="text-2xl font-extrabold mt-1">{{ format_money($summary[$key] ?? 0) }}</p>
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('site.affiliate.wallet.payout-request') }}" class="mb-6 rounded-2xl border border-gray-200 bg-white p-5 grid sm:grid-cols-3 gap-3 items-end">
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

    <div class="rounded-2xl border border-gray-200 bg-white overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
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
                        <td class="px-4 py-3 font-semibold">{{ format_money($payment->amount) }}</td>
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
                    <tr><td colspan="4" class="px-4 py-6 text-gray-500">No commission payments yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $payments->links() }}</div>
</x-site.affiliate-layout>
