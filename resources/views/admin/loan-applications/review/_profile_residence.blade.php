@php
    $customer = $review['customer'] ?? null;
    $officerPhone = $customer
        ? \App\Support\PhoneNumber::format($customer->lga_officer_phone)
        : null;
@endphp
@if (! $customer)
    <p class="text-sm text-gray-500">Residence details are not available for this file.</p>
@else

<div class="rounded-2xl ring-1 ring-brand/10 bg-white p-5 space-y-5">
    <div>
        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold mb-1">Residence information</p>
        <p class="text-sm text-gray-500 mb-4">Current address</p>
        @include('admin.loan-applications.review._field-grid', [
            'fields' => [
                ['label' => 'Region', 'value' => $customer->region],
                ['label' => 'District', 'value' => $customer->district],
                ['label' => 'Ward', 'value' => $customer->ward],
                ['label' => 'Street / address', 'value' => $customer->street ?: $customer->address, 'span' => true],
            ],
        ])
    </div>

    <div class="rounded-xl bg-brand-muted/30 ring-1 ring-brand/10 px-4 py-4">
        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Residence verification · signed by</p>
        @include('admin.loan-applications.review._field-grid', [
            'fields' => [
                ['label' => 'Officer full name', 'value' => $customer->lga_officer_name],
                ['label' => 'Officer position', 'value' => $customer->lga_officer_position],
                ['label' => 'Officer phone', 'value' => $officerPhone ?: $customer->lga_officer_phone, 'span' => true],
            ],
        ])
    </div>
</div>
@endif
