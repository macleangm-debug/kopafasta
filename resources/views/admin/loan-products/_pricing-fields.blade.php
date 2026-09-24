@php
    $r = $record ?? null;
    $isAssetLending = $isAssetLending ?? false;
@endphp

<x-admin.step title="Amounts & limits">
    <p class="md:col-span-2 text-sm font-semibold text-gray-800">{{ $isAssetLending ? 'Application fee, tenure, and limits' : 'Amounts, fees, and repayment' }}</p>
    <p class="md:col-span-2 text-xs text-gray-500">
        @if ($isAssetLending)
            Deposit and financing rates are on the next step. This product does not use the Individual Loan amount-band rate editor.
        @else
            Set the commercial limits staff will use. Tiered monthly rates are on the next step.
        @endif
    </p>
    <x-admin.money-input name="application_fee_amount" label="Application fee (TZS)" :value="$r?->application_fee_amount"
                         placeholder="e.g. 5,000" help="Charged before the borrower can proceed beyond the application-fee gate. Leave blank to use the global application fee." />
    <x-admin.select name="uses_capital_partner" label="Uses capital partner?"
                    x-model="usesCapitalPartner"
                    :options="['1' => 'Yes', '0' => 'No']"
                    :value="old('uses_capital_partner', ($r?->uses_capital_partner ?? true) ? '1' : '0')"
                    help="When Yes, approved loans are funded from active capital partner pools. Service / Collection Asset Lending uses No." />
    <p class="md:col-span-2 text-xs text-gray-500" x-show="usesCapitalPartner === '1'" x-cloak>
        Capital Partner funding applies after approval. Do not also send customer principal to the supplier once the supplier has been settled.
    </p>
    <div class="md:col-span-2 grid sm:grid-cols-2 gap-4">
        <x-admin.input name="tenure_min_months" label="Minimum tenure (months)" type="number" :value="$r?->tenure_min_months" required help="Shortest repayment period." />
        <x-admin.input name="tenure_max_months" label="Maximum tenure (months)" type="number" :value="$r?->tenure_max_months" required help="Longest repayment period." />
    </div>
    <x-admin.select name="repayment_cadence" label="Repayment cadence"
                    :options="['weekly' => 'Weekly (tenure × 4 instalments)', 'monthly' => 'Monthly']"
                    :value="$r?->repayment_cadence ?? 'weekly'" required />
    @if ($isAssetLending)
        <input type="hidden" name="interest_method" value="{{ old('interest_method', $r?->interest_method ?? 'reducing') }}">
        <input type="hidden" name="hides_interest" value="{{ old('hides_interest', ($r?->hides_interest ?? false) ? '1' : '0') }}">
    @else
        <x-admin.select name="interest_method" label="Interest calculation method"
                        :options="['reducing' => 'Reducing balance', 'flat' => 'Flat rate']"
                        :value="old('interest_method', $r?->interest_method ?? 'reducing')" required
                        help="Reducing balance is the default. Flat charges interest on the full principal each period. For Sharia products this stays in the background — borrowers see total repayable language instead." />
        <x-admin.select name="hides_interest" label="Hide interest language (Sharia)"
                        :options="['1' => 'Yes — show charge / total repayable wording', '0' => 'No — show interest wording']"
                        :value="old('hides_interest', ($r?->hides_interest ?? false) ? '1' : '0')"
                        help="When Yes, borrower-facing screens and letters avoid the word interest; pricing still uses the same rate engine as Individual Loan." />
    @endif
    <div class="md:col-span-2 grid sm:grid-cols-2 gap-4">
        <x-admin.money-input name="min_amount" label="Minimum amount (TZS)" :value="$r?->min_amount" required />
        <x-admin.money-input name="max_amount" label="Maximum amount (TZS)" :value="$r?->max_amount" required />
    </div>
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
