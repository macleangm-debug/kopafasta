{{-- Loan-profile-style preview for a pending Accept/Decline guarantee request. --}}
@php
    $link = $row->link;
    $borrower = $row->borrower;
    $application = $row->application;
    $borrowerName = $borrower?->legalDisplayName()
        ?? (trim(($borrower->first_name ?? '').' '.($borrower->last_name ?? '')) ?: '—');
    $productName = $application?->product?->name
        ?? $row->invitation?->product?->name
        ?? __('borrower.guarantor.loan');
    $amount = $application?->requested_amount
        ?? $row->invitation?->requested_amount;
    $reference = $application?->application_number
        ?? $application?->draft_reference
        ?? ($row->invitation?->short_code ? strtoupper((string) $row->invitation->short_code) : '—');
    $detailUrl = route('site.borrower.guarantor-requests.show', $link);
@endphp

<div class="max-w-3xl">
    <div class="mb-6">
        <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-1">{{ __('borrower.loans_page.guarantor_badge') }}</p>
        <h2 class="text-2xl sm:text-3xl font-bold text-brand tracking-tight">{{ $productName }}</h2>
        <p class="text-sm text-gray-500 mt-1">{{ $borrowerName }} · {{ $reference }}</p>
    </div>

    <div class="mb-6 glass-card overflow-hidden">
        <div class="bg-gradient-to-br from-brand-muted/50 to-white px-5 sm:px-6 py-5 border-b border-gray-100/80">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.guaranteed.detail_eyebrow') }}</p>
                    <h3 class="text-lg sm:text-xl font-bold text-gray-900 mt-1">{{ __('borrower.guaranteed.detail_glance_title') }}</h3>
                    <p class="text-sm text-gray-600 mt-1">{{ __('borrower.guarantor.awaiting_your_decision') }}</p>
                </div>
                <a href="{{ $detailUrl }}"
                   class="inline-flex items-center justify-center font-bold px-8 py-3.5 rounded-xl text-sm shrink-0 bg-brand-gold hover:bg-yellow-400 text-brand shadow-sm">
                    {{ __('borrower.guarantor.your_decision') }}
                </a>
            </div>

            <div class="mt-5 rounded-xl bg-white ring-1 ring-gray-200/80 px-4 py-3">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_profile.application_progress') }}</p>
                <div class="flex items-center gap-3 mt-2">
                    <div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                        <div class="h-full rounded-full bg-brand" style="width: 10%"></div>
                    </div>
                    <span class="text-sm font-bold tabular-nums text-gray-900">10%</span>
                </div>
                <p class="text-xs text-gray-500 mt-1">{{ __('borrower.guarantor.action_required') }}</p>
            </div>

            <div class="mt-4 rounded-2xl overflow-hidden ring-1 ring-brand/15 bg-gradient-to-br from-brand-muted/40 via-white to-white px-4 py-3">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.loans_page.loan_amount') }}</p>
                <p class="text-sm text-gray-700 mt-0.5">
                    {{ $amount !== null ? format_money((float) $amount) : '—' }}
                    · {{ __('borrower.loans_page.not_disbursed') }}
                </p>
            </div>
        </div>
    </div>

    <div class="glass-card p-5 mb-2 ring-1 ring-brand/15">
        <div class="mb-4">
            <h3 class="font-semibold">{{ __('borrower.loan_profile.summary_title') }}</h3>
        </div>
        <div class="grid sm:grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.applications_list.amount') }}</p>
                <p class="font-semibold mt-1">{{ $amount !== null ? format_money((float) $amount) : '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.borrower') }}</p>
                <p class="font-semibold mt-1">{{ $borrowerName }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.guaranteed.current_step') }}</p>
                <p class="font-semibold mt-1">{{ __('borrower.guarantor.awaiting_your_decision') }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.loan_status') }}</p>
                <p class="font-semibold mt-1">{{ __('borrower.loans_page.not_disbursed') }}</p>
            </div>
        </div>
    </div>
</div>
