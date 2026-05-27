<x-admin.layout title="Loan Rules" heading="Loan Rules" subheading="Defaults applied to new loans">
    @include('admin.settings._tabs', ['active' => 'loan-rules'])
    @if (session('status'))<div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif

    <form method="POST" action="{{ route('admin.settings.loan-rules.save') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Grace & penalty</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-admin.input  name="default_grace_days"   label="Default grace (days)" type="number" :value="$values['default_grace_days'] ?? '3'" required />
                <x-admin.input  name="default_penalty_rate" label="Penalty rate (%)"     type="number" step="0.01" :value="$values['default_penalty_rate'] ?? '1'" required />
                <x-admin.select name="penalty_basis" label="Penalty basis" :options="['per_day'=>'Per day','per_month'=>'Per month','one_time'=>'One time']" :value="$values['penalty_basis'] ?? 'per_day'" required />
            </div>
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

        <div class="flex justify-end">
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-5 py-2 rounded-lg shadow-sm">Save loan rules</button>
        </div>
    </form>
</x-admin.layout>
