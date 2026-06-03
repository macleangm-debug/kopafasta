<x-admin.layout title="Trial Balance" heading="Trial Balance" subheading="Account balances from Chart of Accounts (opening basis)">
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
                <tr class="{{ abs($totalDebit - $totalCredit) < 0.01 ? 'text-emerald-700' : 'text-rose-700' }}">
                    <td class="px-5 py-3" colspan="3">Difference</td>
                    <td class="px-5 py-3 text-right font-mono" colspan="2">{{ format_number($totalDebit - $totalCredit, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</x-admin.layout>
