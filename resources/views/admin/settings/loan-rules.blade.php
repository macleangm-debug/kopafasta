<x-admin.layout title="Loan Rules" heading="Loan Rules" subheading="Defaults applied to new loans">
    @include('admin.settings._tabs', ['active' => 'loan-rules'])
    @if (session('status'))<div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif

    <form method="POST" action="{{ route('admin.settings.loan-rules.save') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Grace & penalty (global defaults)</h3>
            <p class="text-xs text-gray-500 mb-4">
                Applied to new loan products and loans unless overridden per product. Aligns with Tanzania microfinance practice:
                grace after a missed instalment, then daily penalty on overdue balance. Bank of Tanzania rules cap cumulative penalties at 30% of the amount owed.
            </p>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <x-admin.input  name="default_grace_days"   label="Default grace after default (days)" type="number" :value="$values['default_grace_days'] ?? '7'" required />
                <x-admin.input  name="default_penalty_rate" label="Penalty rate (% of amount owed)" type="number" step="0.01" :value="$values['default_penalty_rate'] ?? '1'" required />
                <x-admin.select name="penalty_basis" label="Penalty basis" :options="['per_day'=>'Per day','per_month'=>'Per month','one_time'=>'One time']" :value="$values['penalty_basis'] ?? 'per_day'" required />
                <x-admin.input  name="penalty_cap_percent" label="BOT max cumulative penalty (%)" type="number" step="0.01" :value="$values['penalty_cap_percent'] ?? '30'" required />
            </div>
            <p class="mt-3 text-xs text-amber-800/90 rounded-lg bg-amber-50 ring-1 ring-amber-100 px-3 py-2">
                Default <strong>1% per day</strong> reaches the <strong>30%</strong> cap after 30 calendar days of continuous default on the same overdue balance.
            </p>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Tenure & amount limits</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-admin.input name="min_tenure_months" label="Min tenure (months)" type="number" :value="$values['min_tenure_months'] ?? '1'" required />
                <x-admin.input name="max_tenure_months" label="Max tenure (months)" type="number" :value="$values['max_tenure_months'] ?? '24'" required />
                <x-admin.input name="min_loan_amount"   label="Min loan amount"     type="number" step="0.01" :value="$values['min_loan_amount'] ?? '50000'" required />
                <x-admin.input name="max_loan_amount"   label="Max loan amount"     type="number" step="0.01" :value="$values['max_loan_amount'] ?? '50000000'" required />
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Guarantors & collateral</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-admin.input name="guarantor_required_above"  label="Guarantor required above" type="number" step="0.01" :value="$values['guarantor_required_above'] ?? '1000000'" />
                <x-admin.input name="collateral_required_above" label="Collateral required above" type="number" step="0.01" :value="$values['collateral_required_above'] ?? '5000000'" />
                <x-admin.input name="min_guarantors" label="Minimum guarantors" type="number" :value="$values['min_guarantors'] ?? '1'" required />
                <x-admin.input name="max_active_guarantees" label="Max active guarantees per guarantor" type="number" :value="$values['max_active_guarantees'] ?? '5'" required />
                <x-admin.input name="max_active_applications_per_product" label="Max active applications per product" type="number" :value="$values['max_active_applications_per_product'] ?? '1'" required />
                <x-admin.input name="top_up_min_successful_repayments" label="Top-up: min successful repayments" type="number" :value="$values['top_up_min_successful_repayments'] ?? '6'" required />
                <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2 md:col-span-3">
                    <input type="hidden" name="allow_asset_reuse" value="0">
                    <input type="checkbox" name="allow_asset_reuse" value="1" @checked(!empty($values['allow_asset_reuse'])) class="size-4 rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                    <span class="text-gray-800">Allow asset reuse across multiple active loans</span>
                </label>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Restructuring</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2 md:col-span-3">
                    <input type="hidden" name="allow_restructure" value="0">
                    <input type="checkbox" name="allow_restructure" value="1" @checked(!empty($values['allow_restructure'])) class="size-4 rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                    <span class="text-gray-800">Allow loan restructuring</span>
                </label>
                <x-admin.input name="max_restructures"           label="Max restructures per loan" type="number" :value="$values['max_restructures'] ?? '2'" required />
                <x-admin.input name="restructure_cooldown_days"  label="Cooldown (days)"            type="number" :value="$values['restructure_cooldown_days'] ?? '30'" required />
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Loan qualification (borrower dashboard limit)</h3>
            <p class="text-xs text-gray-500 mb-4">Controls how the dashboard displays “Your current loan limit is TZS X”.</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-admin.input name="qualification_income_multiplier" label="Income multiplier" type="number" step="0.1" :value="$values['qualification_income_multiplier'] ?? '4'" />
                <x-admin.input name="qualification_max_cap" label="Maximum cap (TZS)" type="number" :value="$values['qualification_max_cap'] ?? '5000000'" />
                <x-admin.input name="qualification_min_profile_percent" label="Min profile % to apply" type="number" :value="$values['qualification_min_profile_percent'] ?? '60'" />
                <x-admin.input name="qualification_good_history_multiplier" label="Repaid-loan bonus multiplier" type="number" step="0.1" :value="$values['qualification_good_history_multiplier'] ?? '1.5'" />
                <x-admin.input name="qualification_good_history_cap" label="Bonus cap (TZS)" type="number" :value="$values['qualification_good_history_cap'] ?? '7500000'" />
                <x-admin.input name="qualification_kyc_incomplete_factor" label="Incomplete profile factor (0–1)" type="number" step="0.05" :value="$values['qualification_kyc_incomplete_factor'] ?? '0.5'" />
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-5 py-2 rounded-lg shadow-sm">Save loan rules</button>
        </div>
    </form>
</x-admin.layout>
