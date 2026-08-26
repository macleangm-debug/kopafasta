@php
    $facts = $offerFacts ?? [];
@endphp

<div class="rounded-3xl bg-gradient-to-br from-brand via-brand to-brand-light text-white p-6 sm:p-8 mb-6 shadow-sm ring-1 ring-brand-gold/30">
    <p class="text-[10px] uppercase tracking-[0.2em] text-brand-gold font-semibold">{{ __('borrower.offer.label') }}</p>
    <h2 class="text-2xl sm:text-3xl font-bold mt-1">{{ __('borrower.offer.your_loan_offer') }}</h2>
    <p class="mt-5 text-[10px] uppercase tracking-widest text-white/70">{{ __('borrower.offer.amount_approved') }}</p>
    <p class="text-4xl sm:text-5xl font-extrabold tabular-nums mt-1">{{ format_money((float) ($facts['amount'] ?? 0)) }}</p>
</div>

<dl class="grid sm:grid-cols-2 gap-3 mb-6">
    <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-4">
        <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.offer.repayment_period') }}</dt>
        <dd class="text-xl font-bold text-gray-900 mt-1">{{ __('borrower.offer.tenure_months_value', ['count' => (int) ($facts['tenure_months'] ?? 0)]) }}</dd>
    </div>
    <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-4">
        <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.offer.payment_frequency') }}</dt>
        <dd class="text-xl font-bold text-gray-900 mt-1">{{ $facts['frequency_label'] ?? '—' }}</dd>
    </div>
    <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-4">
        <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.offer.instalment') }}</dt>
        <dd class="text-xl font-bold text-gray-900 mt-1 tabular-nums">{{ format_money((float) ($facts['installment'] ?? 0)) }}</dd>
    </div>
    <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-4">
        <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.offer.total_repayment') }}</dt>
        <dd class="text-xl font-bold text-gray-900 mt-1 tabular-nums">{{ format_money((float) ($facts['total_repayment'] ?? 0)) }}</dd>
    </div>
    <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-4 sm:col-span-2">
        <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.offer.first_payment') }}</dt>
        <dd class="text-lg font-bold text-gray-900 mt-1">{{ $facts['first_payment_label'] ?? '—' }}</dd>
    </div>
</dl>
