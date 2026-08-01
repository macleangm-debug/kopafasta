<x-admin.review-section id="customer-loans" title="Loans" subtitle="Active and historic loans">
    @if ($dossier['loans']->isEmpty())
        <p class="text-sm text-gray-500">No loans on record for this customer.</p>
    @else
        <div class="overflow-x-auto -mx-6 px-6">
            <table class="min-w-full text-sm">
                <thead class="text-xs uppercase tracking-wider text-gray-500 border-b border-gray-100">
                    <tr>
                        <th class="py-2 text-left font-semibold">Loan #</th>
                        <th class="py-2 text-left font-semibold">Product</th>
                        <th class="py-2 text-right font-semibold">Principal</th>
                        <th class="py-2 text-right font-semibold">Outstanding</th>
                        <th class="py-2 text-left font-semibold">Status</th>
                        <th class="py-2 text-right font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($dossier['loans'] as $loan)
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 font-mono text-xs">{{ $loan->loan_number }}</td>
                            <td class="py-3">{{ $loan->product?->name ?? '—' }}</td>
                            <td class="py-3 text-right">{{ format_money((float) $loan->principal_amount) }}</td>
                            <td class="py-3 text-right font-semibold">{{ format_money((float) $loan->outstanding_balance) }}</td>
                            <td class="py-3">{{ display_label($loan->status, 'loan_status') }}</td>
                            <td class="py-3 text-right">
                                <a href="{{ route('admin.loans.show', $loan) }}" class="text-xs font-semibold text-brand hover:text-brand-light">Open →</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-admin.review-section>
