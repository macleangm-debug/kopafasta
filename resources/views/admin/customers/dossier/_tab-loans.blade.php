<div class="space-y-6">
    <div>
        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-brand">Loans</p>
        <h4 class="text-base font-bold text-gray-900 mt-0.5">Financing lifecycle</h4>
        <p class="text-xs text-gray-500 mt-0.5">Loans are disbursed financing — distinct from applications (requests).</p>
    </div>

    @if ($dossier['loans']->isEmpty())
        <p class="text-sm text-gray-500">No loans on record. Applications are listed under the Applications tab.</p>
    @else
        <div class="space-y-3">
            @foreach ($dossier['loans'] as $loan)
                <article class="rounded-2xl ring-1 ring-brand/10 bg-white overflow-hidden">
                    <div class="px-4 py-4 sm:px-5 flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="font-mono text-xs text-gray-500">{{ $loan->loan_number }}</p>
                            <h4 class="text-base font-bold text-gray-900 mt-0.5">{{ $loan->product?->name ?? 'Loan' }}</h4>
                            <p class="text-xs text-gray-500 mt-1">{{ display_label($loan->status, 'loan_status') }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-gray-500">Principal</p>
                            <p class="text-lg font-bold tabular-nums text-gray-900">{{ format_money((float) $loan->principal_amount) }}</p>
                            <p class="text-xs text-gray-500 mt-1">Outstanding <span class="font-semibold text-gray-800">{{ format_money((float) $loan->outstanding_balance) }}</span></p>
                        </div>
                    </div>
                    <div class="px-4 sm:px-5 py-3 bg-brand-muted/20 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
                        <div class="text-xs text-gray-600">
                            @if ($loan->application)
                                Originating application
                                <a href="{{ route('admin.loan-applications.show', $loan->application) }}" class="font-semibold text-brand hover:underline ml-1">
                                    {{ $loan->application->application_number }} →
                                </a>
                            @else
                                <span class="text-gray-400">No originating application link</span>
                            @endif
                        </div>
                        <a href="{{ route('admin.loans.show', $loan) }}" class="text-xs font-semibold text-brand hover:underline">Open loan →</a>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
