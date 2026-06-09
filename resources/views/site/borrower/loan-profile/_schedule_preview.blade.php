@php
    $preview = $profile['schedule_preview'] ?? null;
@endphp

@if ($preview)
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden mb-6">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="font-semibold">{{ __('borrower.apply.review_step.schedule_section') }}</h2>
            <p class="text-xs text-gray-500 mt-0.5">{{ __('borrower.apply.review_step.schedule_before_disbursement') }}</p>
        </div>
        <div class="px-5 py-4 grid sm:grid-cols-2 gap-4 text-sm border-b border-gray-100">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.apply.review_step.term') }}</p>
                <p class="font-semibold text-gray-900 mt-1">{{ $preview['term_months'] }} {{ __('borrower.apply.review_step.months') }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.apply.review_step.monthly_repayment') }}</p>
                <p class="font-semibold text-gray-900 mt-1">{{ format_money($preview['installment_amount']) }}</p>
            </div>
        </div>
        <ul class="divide-y divide-gray-100 max-h-64 overflow-y-auto">
            @foreach (($preview['installments'] ?? []) as $row)
                <li class="px-5 py-3 flex items-center justify-between gap-3 text-sm">
                    <span class="text-gray-700">{{ $row['label'] }}</span>
                    <span class="font-medium text-gray-900">{{ format_money($row['total_due']) }}</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
