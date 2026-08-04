@php
    $preview = $profile['schedule_preview'] ?? null;
@endphp

@if ($preview)
    <div class="glass-card overflow-hidden mb-6">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="font-semibold">{{ __('borrower.apply.review_step.schedule_section') }}</h2>
            <p class="text-xs text-gray-500 mt-0.5">{{ __('borrower.apply.review_step.schedule_before_disbursement') }}</p>
        </div>
        <div class="px-5 py-4 grid sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.apply.review_step.term') }}</p>
                <p class="font-semibold text-gray-900 mt-1">{{ $preview['term_months'] }} {{ __('borrower.apply.review_step.months') }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.apply.review_step.monthly_repayment') }}</p>
                <p class="font-semibold text-gray-900 mt-1">{{ format_money($preview['installment_amount']) }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_profile.repayment_frequency') }}</p>
                <p class="font-semibold text-gray-900 mt-1">{{ __('borrower.loan_profile.repayment_cadences.' . ($preview['frequency'] ?? 'weekly')) }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_profile.number_of_installments') }}</p>
                <p class="font-semibold text-gray-900 mt-1">{{ $preview['installment_count'] ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_profile.total_repayable') }}</p>
                <p class="font-semibold text-gray-900 mt-1">{{ format_money($preview['total_repayable'] ?? 0) }}</p>
            </div>
        </div>
    </div>
@endif
