<x-site.supplier-layout title="Delivered assets" active="assets">
    <x-site.borrower-page-header
        eyebrow="Supplier"
        title="Delivered assets"
        subtitle="Assets handed over to borrowers under managed-loan arrangements."
    >
        <x-slot:actions>
            <a href="{{ route('site.supplier.assets') }}" class="inline-flex bg-white ring-1 ring-gray-200 hover:ring-brand/30 text-gray-800 font-semibold px-4 py-2.5 rounded-xl text-sm">
                ← Back to assets
            </a>
        </x-slot:actions>
    </x-site.borrower-page-header>
    <div class="glass-card rounded-2xl ring-1 ring-brand/10 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-brand-muted/30 text-left text-xs uppercase tracking-widest text-brand">
                <tr>
                    <th class="px-4 py-3 font-semibold">Asset</th>
                    <th class="px-4 py-3 font-semibold">Borrower</th>
                    <th class="px-4 py-3 font-semibold">Handed over</th>
                    <th class="px-4 py-3 font-semibold">Loan</th>
                    <th class="px-4 py-3 font-semibold">Outstanding</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($reservations as $row)
                    @php
                        $loan = $row->loanApplication?->loan;
                        $outstanding = $loan && in_array($loan->status, ['active', 'disbursed', 'arrears'], true)
                            ? app(\App\Services\LoanBalanceService::class)->breakdown($loan)['total_outstanding']
                            : null;
                    @endphp
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $row->asset?->title }}</td>
                        <td class="px-4 py-3">{{ $row->customer?->full_name }}</td>
                        <td class="px-4 py-3">{{ optional($row->released_at)->format('d M Y') ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $loan?->loan_number ?? '—' }}</td>
                        <td class="px-4 py-3 font-semibold tabular-nums">{{ $outstanding !== null ? format_money($outstanding) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-gray-500">No delivered assets yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $reservations->links() }}</div>
</x-site.supplier-layout>
