@php
    $lending = $lending ?? app(\App\Services\AssetLendingService::class);
    $depositTiers = old('deposit_tiers', $lending->depositTiers());
    $financingTiers = old('financing_tiers', $lending->financingTiers());
@endphp

<div class="md:col-span-2 mt-2 pt-4 border-t border-gray-100 space-y-6" x-data="{
    deposit: @js(array_values($depositTiers)),
    financing: @js(array_values($financingTiers)),
    addDeposit() { this.deposit.push({ from: 0, to: null, percent: 0, active: true }) },
    addFinancing() { this.financing.push({ from: 0, to: null, monthly_rate_percent: 0, method: 'reducing_balance', max_tenure_months: 6, active: true }) },
}">
    <div>
        <p class="text-sm font-semibold text-gray-800">Funding / supplier arrangement</p>
        <p class="text-xs text-gray-500 mt-1">
            Controls whether Kopafasta only administers collections or the supplier is settled using financing capital.
            The supplier record holds the arrangement. Each application snapshots one mode.
        </p>
        <p class="text-xs text-gray-600 mt-2">
            Default: <strong>Service / Collection</strong> — no Capital Partner required. Valuation is not used for Marketplace / supplier assets.
        </p>
    </div>

    <div>
        <div class="flex items-center justify-between gap-3 mb-1">
            <p class="text-sm font-semibold text-gray-800">Deposit tiers</p>
            <button type="button" @click="addDeposit()" class="text-xs font-bold text-brand">Add tier</button>
        </div>
        <p class="text-xs text-gray-500 mb-2">The percentage the customer must pay before the financed amount is calculated.</p>
        <template x-for="(row, i) in deposit" :key="'d'+i">
            <div class="grid grid-cols-12 gap-2 items-end mb-2">
                <div class="col-span-3">
                    <label class="text-xs font-medium text-gray-700">From</label>
                    <input type="number" :name="'deposit_tiers['+i+'][from]'" x-model="row.from" class="mt-1 w-full rounded-lg border-gray-200 text-sm" min="0" step="1">
                </div>
                <div class="col-span-3">
                    <label class="text-xs font-medium text-gray-700">To</label>
                    <input type="number" :name="'deposit_tiers['+i+'][to]'" x-model="row.to" class="mt-1 w-full rounded-lg border-gray-200 text-sm" min="0" step="1" placeholder="Open">
                </div>
                <div class="col-span-3">
                    <label class="text-xs font-medium text-gray-700">Deposit %</label>
                    <input type="number" :name="'deposit_tiers['+i+'][percent]'" x-model="row.percent" class="mt-1 w-full rounded-lg border-gray-200 text-sm" min="0" max="100" step="0.01">
                </div>
                <div class="col-span-2 pb-2">
                    <input type="hidden" :name="'deposit_tiers['+i+'][active]'" :value="row.active ? 1 : 0">
                    <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-700">
                        <input type="checkbox" x-model="row.active">
                        Active
                    </label>
                </div>
                <div class="col-span-1 pb-2">
                    <button type="button" @click="deposit.splice(i,1)" class="text-xs font-semibold text-rose-700">Remove</button>
                </div>
            </div>
        </template>
    </div>

    <div>
        <div class="flex items-center justify-between gap-3 mb-1">
            <p class="text-sm font-semibold text-gray-800">Financing tiers</p>
            <button type="button" @click="addFinancing()" class="text-xs font-bold text-brand">Add tier</button>
        </div>
        <p class="text-xs text-gray-500 mb-2">Monthly charge and longest repayment period for the financed amount after deposit.</p>
        <template x-for="(row, i) in financing" :key="'f'+i">
            <div class="grid grid-cols-12 gap-2 items-end mb-2">
                <div class="col-span-2">
                    <label class="text-xs font-medium text-gray-700">From</label>
                    <input type="number" :name="'financing_tiers['+i+'][from]'" x-model="row.from" class="mt-1 w-full rounded-lg border-gray-200 text-sm" min="0" step="1">
                </div>
                <div class="col-span-2">
                    <label class="text-xs font-medium text-gray-700">To</label>
                    <input type="number" :name="'financing_tiers['+i+'][to]'" x-model="row.to" class="mt-1 w-full rounded-lg border-gray-200 text-sm" min="0" step="1" placeholder="Open">
                </div>
                <div class="col-span-2">
                    <label class="text-xs font-medium text-gray-700">Monthly %</label>
                    <input type="number" :name="'financing_tiers['+i+'][monthly_rate_percent]'" x-model="row.monthly_rate_percent" class="mt-1 w-full rounded-lg border-gray-200 text-sm" min="0" max="100" step="0.01">
                </div>
                <div class="col-span-2">
                    <label class="text-xs font-medium text-gray-700">Max months</label>
                    <input type="number" :name="'financing_tiers['+i+'][max_tenure_months]'" x-model="row.max_tenure_months" class="mt-1 w-full rounded-lg border-gray-200 text-sm" min="1" max="60">
                </div>
                <div class="col-span-2">
                    <input type="hidden" :name="'financing_tiers['+i+'][method]'" value="reducing_balance">
                    <p class="text-xs font-medium text-gray-700">Method</p>
                    <p class="text-xs font-semibold text-gray-800 mt-2">Reducing</p>
                </div>
                <div class="col-span-1 pb-2">
                    <input type="hidden" :name="'financing_tiers['+i+'][active]'" :value="row.active ? 1 : 0">
                    <label class="inline-flex items-center gap-1 text-xs font-medium text-gray-700">
                        <input type="checkbox" x-model="row.active">
                        On
                    </label>
                </div>
                <div class="col-span-1 pb-2">
                    <button type="button" @click="financing.splice(i,1)" class="text-xs font-semibold text-rose-700">Remove</button>
                </div>
            </div>
        </template>
    </div>
</div>
