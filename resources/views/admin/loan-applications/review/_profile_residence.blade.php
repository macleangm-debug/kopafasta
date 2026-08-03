@php
    $customer = $review['customer'];
@endphp

<div class="rounded-2xl ring-1 ring-brand/10 bg-white p-5">
    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold mb-1">Residence information</p>
    <p class="text-sm text-gray-500 mb-4">Current address and local government officer</p>
    @include('admin.loan-applications.review._field-grid', [
        'fields' => [
            ['label' => 'Region', 'value' => $customer->region],
            ['label' => 'District', 'value' => $customer->district],
            ['label' => 'Ward', 'value' => $customer->ward],
            ['label' => 'Street / address', 'value' => $customer->street ?: $customer->address, 'span' => true],
            ['label' => 'LGA officer name', 'value' => $customer->lga_officer_name],
            ['label' => 'Officer position', 'value' => $customer->lga_officer_position],
            ['label' => 'Officer phone', 'value' => $customer->lga_officer_phone, 'span' => true],
        ],
    ])
</div>
