<x-site.supplier-layout title="Delivered assets" active="delivered">
    <h1 class="text-2xl font-bold mb-6">Delivered assets</h1>
    <p class="text-sm text-gray-600 mb-4">Assets handed over to borrowers under managed-loan arrangements.</p>
    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Asset</th>
                    <th class="px-4 py-3">Borrower</th>
                    <th class="px-4 py-3">Handed over</th>
                    <th class="px-4 py-3">Loan</th>
                    <th class="px-4 py-3">Outstanding</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($reservations as $row)
                    @php
                        $loan = $row->loanApplication?->loan;
                        $outstanding = $loan && in_array($loan->status, ['active', 'disbursed', 'arrears'], true)
                            ? app(\App\Services\LoanBalanceService::class)->breakdown($loan)['total_outstanding']
                            : null;
                    @endphp
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $row->asset?->title }}</td>
                        <td class="px-4 py-3">{{ $row->customer?->full_name }}</td>
                        <td class="px-4 py-3">{{ optional($row->released_at)->format('d M Y') ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $loan?->loan_number ?? '—' }}</td>
                        <td class="px-4 py-3 font-semibold">{{ $outstanding !== null ? format_money($outstanding) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No delivered assets yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $reservations->links() }}</div>
</x-site.supplier-layout>
