@php
    $customer = $review['customer'];
    $purposeValue = format_loan_purpose_display(
        $record->purpose,
        data_get($record->screening_payload, 'purpose_other'),
        $record->screening_payload
    );
@endphp

<div class="rounded-2xl ring-1 ring-brand/10 bg-white p-5">
    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold mb-1">Activity information</p>
    <p class="text-sm text-gray-500 mb-4">How the borrower earns and what the loan is for</p>
    @include('admin.loan-applications.review._field-grid', [
        'fields' => [
            ['label' => 'Activity type', 'value' => $review['activity_label'] ?? null],
            ['label' => 'Income range', 'value' => $review['income_label'] ?? null],
            ['label' => 'Business / employer', 'value' => $review['business_name'] ?? null, 'span' => true],
            ['label' => 'Loan purpose', 'value' => $purposeValue, 'span' => true],
            ['label' => 'Monthly income (midpoint)', 'value' => $customer->monthly_income ? format_money((float) $customer->monthly_income) : null],
        ],
    ])
</div>
