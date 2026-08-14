@php $loans = $loans ?? collect(); @endphp

<section>
    <h2 class="text-lg font-semibold mb-1">{{ __('borrower.loans_page.section_active') }}</h2>
    <p class="text-sm text-gray-500 mb-5">{{ __('borrower.loans_page.active_hint') }}</p>

    @if ($loans->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50/50 px-5 py-8 text-center text-sm text-gray-500">
            {{ __('borrower.loans_page.no_active_loans') }}
        </div>
    @else
        <div class="space-y-4">
            @foreach ($loans as $loan)
                @php
                    $statusBadge = match ($loan->status) {
                        'active','disbursed' => 'bg-emerald-100 text-emerald-700',
                        'arrears'            => 'bg-red-100 text-red-700',
                        default              => 'bg-amber-100 text-amber-700',
                    };
                @endphp
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <div class="flex items-start justify-between gap-3 mb-4 flex-wrap">
                        <div>
                            <p class="text-lg sm:text-xl font-bold text-gray-900 tracking-tight">{{ $loan->product?->localizedName() ?? $loan->product->name ?? __('borrower.apply.product_type.general') }}</p>
                            <p class="font-mono text-xs text-gray-500 mt-1">{{ $loan->loan_number }}</p>
                        </div>
                        <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $statusBadge }}">{{ ucfirst($loan->status) }}</span>
                    </div>

                    <div class="grid sm:grid-cols-3 gap-4 mb-4 text-sm">
                        @php
                            $owed = app(\App\Services\ActiveLoanServicingService::class)->forLoan($loan);
                            $bd = $owed['balance_breakdown'] ?? [];
                            $rec = $owed['recovery_charges']['total'] ?? ($bd['recovery_costs'] ?? 0);
                        @endphp
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loans_page.outstanding') }}</p>
                            <p class="font-semibold">{{ format_money($bd['total_outstanding'] ?? $loan->outstanding_balance) }}</p>
                            @if (((float) ($bd['penalty_outstanding'] ?? 0)) > 0 || (float) $rec > 0)
                                <p class="text-[11px] text-gray-500 mt-1">
                                    @if (((float) ($bd['penalty_outstanding'] ?? 0)) > 0)
                                        {{ __('borrower.loan_servicing.penalty_outstanding') }}: {{ format_money($bd['penalty_outstanding']) }}
                                    @endif
                                    @if (((float) ($bd['penalty_outstanding'] ?? 0)) > 0 && (float) $rec > 0) · @endif
                                    @if ((float) $rec > 0)
                                        {{ __('borrower.loan_servicing.recovery_total') }}: {{ format_money($rec) }}
                                    @endif
                                </p>
                            @endif
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loans_page.next_payment') }}</p>
                            <p class="font-semibold">{{ $loan->next_due_date ? \Carbon\Carbon::parse($loan->next_due_date)->format('d M Y') : '—' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loans_page.loan_status') }}</p>
                            <p class="font-semibold">{{ ucfirst($loan->status) }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap">
                        <a href="{{ route('site.borrower.schedule', $loan->id) }}" data-kf-motion="push" class="text-xs font-semibold text-amber-700 hover:underline">{{ __('borrower.loans_page.view_schedule') }}</a>
                        <span class="text-gray-300">·</span>
                        <a href="{{ route('site.borrower.payments.create', ['loan' => $loan->id]) }}" data-kf-motion="push" class="text-xs font-semibold text-gray-700 hover:underline">{{ __('borrower.loans_page.make_payment') }}</a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>
