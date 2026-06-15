<x-admin.layout title="Trial Balance" heading="Trial Balance" subheading="Posted journal balances including opening balances">
    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">As at date</label>
            <input type="date" name="as_of" value="{{ ($asOf ?? now())->format('Y-m-d') }}" class="rounded-lg border-gray-300 text-sm">
        </div>
        <button type="submit" class="bg-gray-900 text-white text-sm font-semibold px-4 py-2 rounded-lg">Update</button>
    </form>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-5 py-3">Code</th>
                    <th class="px-5 py-3">Account</th>
                    <th class="px-5 py-3">Type</th>
                    <th class="px-5 py-3 text-right">Debit</th>
                    <th class="px-5 py-3 text-right">Credit</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $r)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-2 font-mono text-xs">{{ $r->code }}</td>
                        <td class="px-5 py-2">{{ $r->name }}</td>
                        <td class="px-5 py-2 capitalize text-gray-600">{{ $r->type }}</td>
                        <td class="px-5 py-2 text-right font-mono">{{ $r->debit  ? format_number($r->debit, 2)  : '—' }}</td>
                        <td class="px-5 py-2 text-right font-mono">{{ $r->credit ? format_number($r->credit, 2) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-12 text-center text-gray-500">No accounts in Chart of Accounts.</td></tr>
                @endforelse
            </tbody>
            <tfoot class="bg-gray-50 font-semibold">
                <tr>
                    <td class="px-5 py-3" colspan="3">Totals</td>
                    <td class="px-5 py-3 text-right font-mono">{{ format_number($totalDebit, 2) }}</td>
                    <td class="px-5 py-3 text-right font-mono">{{ format_number($totalCredit, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    @if (round($totalDebit, 2) !== round($totalCredit, 2))
        <p class="mt-3 text-sm text-red-600">Trial balance is out of balance by {{ format_money(abs($totalDebit - $totalCredit)) }}.</p>
    @else
        <p class="mt-3 text-sm text-emerald-700">Trial balance is in balance.</p>
    @endif
</x-admin.layout>
