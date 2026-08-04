{{-- Loan-profile-style preview for a disbursed guaranteed loan. --}}
@php
    $borrowerName = $row->borrower?->legalDisplayName() ?? __('borrower.loans_page.borrower');
    $productName = $row->product?->name ?? __('borrower.guarantor.loan');
    $loanStatuses = __('borrower.loans_page.loan_statuses');
    $detailUrl = route('site.borrower.guaranteed.show', $row->link);
    $repaid = (int) min(100, max(0, $row->repaid_percent ?? 0));
    $isTerminal = (bool) ($row->is_terminal ?? false);
@endphp

<div class="max-w-3xl {{ $isTerminal ? 'opacity-80' : '' }}">
    <div class="mb-6">
        <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-1">{{ __('borrower.loans_page.guarantor_badge') }}</p>
        <h2 class="text-2xl sm:text-3xl font-bold text-brand tracking-tight">{{ $productName }}</h2>
        <p class="text-sm text-gray-500 mt-1">{{ $borrowerName }} · {{ $row->reference }}</p>
    </div>

    <div class="mb-6 glass-card overflow-hidden">
        <div class="bg-gradient-to-br from-brand-muted/50 to-white px-5 sm:px-6 py-5 border-b border-gray-100/80">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.guaranteed.detail_eyebrow') }}</p>
                    <h3 class="text-lg sm:text-xl font-bold text-gray-900 mt-1">{{ __('borrower.guaranteed.detail_glance_title') }}</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        {{ $loanStatuses[$row->loan_status] ?? ucfirst((string) $row->loan_status) }}
                        @if ($row->in_arrears)
                            · {{ __('borrower.guaranteed.in_arrears') }}
                        @endif
                    </p>
                </div>
                <a href="{{ $detailUrl }}"
                   class="inline-flex items-center justify-center font-bold px-8 py-3.5 rounded-xl text-sm shrink-0 bg-brand-gold hover:bg-yellow-400 text-brand shadow-sm">
                    {{ __('borrower.guaranteed.view_details') }}
                </a>
            </div>

            <div class="mt-5 rounded-xl bg-white ring-1 ring-gray-200/80 px-4 py-3">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.guaranteed.repayment_progress') }}</p>
                <div class="flex items-center gap-3 mt-2">
                    <div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                        <div class="h-full rounded-full {{ $row->in_arrears ? 'bg-red-500' : 'bg-brand' }}" style="width: {{ $repaid }}%"></div>
                    </div>
                    <span class="text-sm font-bold tabular-nums text-gray-900">{{ $repaid }}%</span>
                </div>
                <p class="text-xs text-gray-500 mt-1">{{ __('borrower.loans_page.repaid_pct', ['pct' => format_number($row->repaid_percent, 0)]) }}</p>
            </div>
        </div>
    </div>

    <div class="glass-card p-5 mb-2 ring-1 ring-brand/15">
        <div class="mb-4">
            <h3 class="font-semibold">{{ __('borrower.loan_profile.summary_title') }}</h3>
        </div>
        <div class="grid sm:grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.loan_amount') }}</p>
                <p class="font-semibold mt-1">{{ format_money($row->amount) }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.outstanding') }}</p>
                <p class="font-semibold mt-1">{{ format_money($row->outstanding ?? 0) }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.borrower') }}</p>
                <p class="font-semibold mt-1">{{ $borrowerName }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.next_due') }}</p>
                <p class="font-semibold mt-1">{{ $row->next_due_date ? \Carbon\Carbon::parse($row->next_due_date)->format('d M Y') : '—' }}</p>
            </div>
        </div>
    </div>
</div>
