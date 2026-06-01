@php
    $r = $record ?? null;
    $displayed = app(\App\Services\DisplayedRateService::class)->breakdown($r ?? new \App\Models\LoanProduct([
        'interest_rate' => $r?->interest_rate ?? 0,
        'bot_regulated_rate' => $r?->bot_regulated_rate,
        'processing_fee_rate' => $r?->processing_fee_rate ?? 0,
        'service_fee_rate' => $r?->service_fee_rate ?? 0,
        'administration_fee_rate' => $r?->administration_fee_rate ?? 0,
        'min_amount' => $r?->min_amount ?? 0,
    ]));
@endphp

<x-admin.step title="BOT compliance & displayed rate">
    <p class="md:col-span-2 text-xs text-gray-500 mb-2">
        Separate BOT-regulated interest (max 3.5% / month) from internal fee components.
        The displayed rate is shown to borrowers on product cards, the apply wizard, and offer letters.
    </p>
    <x-admin.input name="interest_rate" label="Base rate (legacy / tier fallback, decimal)" type="number" step="0.0001" :value="$r?->interest_rate" required />
    <x-admin.input name="bot_regulated_rate" label="BOT regulated rate (decimal, max 0.035)" type="number" step="0.0001" :value="$r?->bot_regulated_rate ?? $r?->interest_rate" placeholder="Defaults to tier/base rate" />
    <x-admin.input name="processing_fee_rate" label="Processing fee rate (decimal / month)" type="number" step="0.0001" :value="$r?->processing_fee_rate ?? 0" />
    <x-admin.input name="service_fee_rate" label="Service fee rate (decimal / month)" type="number" step="0.0001" :value="$r?->service_fee_rate ?? 0" />
    <x-admin.input name="administration_fee_rate" label="Administration fee rate (decimal / month)" type="number" step="0.0001" :value="$r?->administration_fee_rate ?? 0" />
    <div class="md:col-span-2 rounded-xl bg-amber-50 ring-1 ring-amber-100 p-4 text-sm">
        <p class="font-semibold text-amber-900">Preview (at minimum amount)</p>
        <ul class="mt-2 space-y-1 text-amber-900/90 text-xs">
            <li>BOT regulated: {{ number_format($displayed['bot_regulated_rate'] * 100, 2) }}% / month</li>
            <li>Internal fees: {{ number_format($displayed['internal_fee_rate'] * 100, 2) }}% / month</li>
            <li><strong>Displayed rate: {{ number_format($displayed['displayed_monthly_rate'] * 100, 2) }}% / month</strong></li>
        </ul>
    </div>
</x-admin.step>

<x-admin.step title="Pricing & tenure">
    <x-admin.input  name="tenure_min_months"   label="Min tenure (months)" type="number" :value="$r?->tenure_min_months" required />
    <x-admin.input  name="tenure_max_months"   label="Max tenure (months)" type="number" :value="$r?->tenure_max_months" required />
    <x-admin.select name="repayment_cadence"   label="Repayment cadence"
                    :options="['weekly' => 'Weekly (tenure × 4 instalments)', 'monthly' => 'Monthly']"
                    :value="$r?->repayment_cadence ?? 'weekly'" required />
    <x-admin.input  name="min_amount"          label="Min amount (TZS)"    type="number" step="0.01" :value="$r?->min_amount" required />
    <x-admin.input  name="max_amount"          label="Max amount (TZS)"    type="number" step="0.01" :value="$r?->max_amount" required />
</x-admin.step>
