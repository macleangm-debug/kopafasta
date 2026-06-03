@php($r = $record ?? null)

<x-admin.step title="Pricing & limits">
    <p class="md:col-span-2 text-xs text-gray-500">
        Configure limits here; tiered monthly rates and rate components (BOT, processing, risk, insurance) are set in the next step.
    </p>
    <x-admin.money-input name="application_fee_amount" label="Application fee (TZS)" :value="$r?->application_fee_amount"
                         placeholder="e.g. 5,000" help="Charged when a borrower applies for this product. Leave blank to use the global application fee." />
    <x-admin.input name="tenure_min_months" label="Min tenure (months)" type="number" :value="$r?->tenure_min_months" required />
    <x-admin.input name="tenure_max_months" label="Max tenure (months)" type="number" :value="$r?->tenure_max_months" required />
    <x-admin.select name="repayment_cadence" label="Repayment cadence"
                    :options="['weekly' => 'Weekly (tenure × 4 instalments)', 'monthly' => 'Monthly']"
                    :value="$r?->repayment_cadence ?? 'weekly'" required />
    <x-admin.money-input name="min_amount" label="Min amount (TZS)" :value="$r?->min_amount" required />
    <x-admin.money-input name="max_amount" label="Max amount (TZS)" :value="$r?->max_amount" required />
    <input type="hidden" name="interest_rate" value="{{ old('interest_rate', $r?->interest_rate ?? 0) }}">
</x-admin.step>
