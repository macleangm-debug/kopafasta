@php
    $draftService = app(\App\Services\LoanApplicationDraftService::class);
@endphp

<div class="space-y-8">
    <div>
        <h4 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Applications</h4>
        @if ($dossier['applications']->isEmpty() && ($dossier['application_drafts'] ?? collect())->isEmpty())
            <p class="text-sm text-gray-500">No applications yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-xs uppercase text-gray-500 border-b border-gray-100">
                        <tr>
                            <th class="py-2 text-left">App #</th>
                            <th class="py-2 text-left">Product</th>
                            <th class="py-2 text-right">Amount</th>
                            <th class="py-2 text-left">Status</th>
                            <th class="py-2 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($dossier['applications'] as $app)
                            <tr>
                                <td class="py-3 font-mono text-xs">{{ $app->application_number }}</td>
                                <td class="py-3">{{ $app->product?->name ?? '—' }}</td>
                                <td class="py-3 text-right">{{ format_money((float) ($app->offered_amount ?: $app->requested_amount)) }}</td>
                                <td class="py-3">{{ display_label($app->status, 'application_status') }}</td>
                                <td class="py-3 text-right"><a href="{{ route('admin.loan-applications.show', $app) }}" class="text-xs font-semibold text-brand">Open →</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div>
        <h4 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Loans</h4>
        @if ($dossier['loans']->isEmpty())
            <p class="text-sm text-gray-500">No loans on record.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-xs uppercase text-gray-500 border-b border-gray-100">
                        <tr>
                            <th class="py-2 text-left">Loan #</th>
                            <th class="py-2 text-left">Product</th>
                            <th class="py-2 text-right">Principal</th>
                            <th class="py-2 text-right">Outstanding</th>
                            <th class="py-2 text-left">Status</th>
                            <th class="py-2 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($dossier['loans'] as $loan)
                            <tr>
                                <td class="py-3 font-mono text-xs">{{ $loan->loan_number }}</td>
                                <td class="py-3">{{ $loan->product?->name ?? '—' }}</td>
                                <td class="py-3 text-right">{{ format_money((float) $loan->principal_amount) }}</td>
                                <td class="py-3 text-right font-semibold">{{ format_money((float) $loan->outstanding_balance) }}</td>
                                <td class="py-3">{{ display_label($loan->status, 'loan_status') }}</td>
                                <td class="py-3 text-right"><a href="{{ route('admin.loans.show', $loan) }}" class="text-xs font-semibold text-brand">Open →</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
