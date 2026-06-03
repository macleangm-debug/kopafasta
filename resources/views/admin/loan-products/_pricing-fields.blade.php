@php
    $r = $record ?? null;
@endphp

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

    <div class="md:col-span-2 mt-2 pt-4 border-t border-gray-100">
        <h3 class="text-sm font-semibold text-gray-800 mb-1">Default after missed payment</h3>
        <p class="text-xs text-gray-500 mb-4">
            Industry practice: a short grace period after default, then a daily penalty on the overdue balance.
            Bank of Tanzania rules cap cumulative penalties at <strong>30%</strong> of the amount owed; this platform uses
            <strong>1% per day</strong> by default (reaching the cap after 30 days of continuous default).
        </p>
        <div class="grid md:grid-cols-3 gap-3">
            @php
                $graceDefault = old('default_grace_days', $r?->default_grace_days ?? config('loan_product_defaults.default_grace_days', 7));
                $penaltyDefault = old('penalty_rate_percent', $r?->penalty_rate_percent ?? config('loan_product_defaults.penalty_rate_percent', 1));
                $basisDefault = old('penalty_basis', $r?->penalty_basis ?? config('loan_product_defaults.penalty_basis', 'per_day'));
                $penaltyRateLabel = 'Penalty rate (% of amount owed)';
                $penaltyRateHelp = 'Default: 1% per day on overdue balance (BOT max cumulative 30%).';
            @endphp
            <x-admin.input name="default_grace_days" label="Grace period after default (days)" type="number"
                           :value="$graceDefault" required
                           help="No penalty is charged until this many days after the instalment due date." />
            <x-admin.input name="penalty_rate_percent" :label="$penaltyRateLabel" type="number" step="0.01"
                           :value="$penaltyDefault" required
                           :help="$penaltyRateHelp" />
            <x-admin.select name="penalty_basis" label="Penalty basis"
                            :options="['per_day' => 'Per day', 'per_month' => 'Per month', 'one_time' => 'One time']"
                            :value="$basisDefault" required />
        </div>
    </div>
</x-admin.step>
