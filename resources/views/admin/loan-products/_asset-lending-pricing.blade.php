@php
    $lending = $lending ?? app(\App\Services\AssetLendingService::class);
    $depositTiers = old('deposit_tiers', $lending->depositTiers());
    $financingTiers = old('financing_tiers', $lending->financingTiers());
@endphp

<div class="md:col-span-2 space-y-4" x-data="{
    tab: 'deposit',
    deposit: @js(array_values($depositTiers)),
    financing: @js(array_values($financingTiers)),
    addDeposit() { this.deposit.push({ from: 0, to: null, percent: 0, active: true }) },
    addFinancing() { this.financing.push({ from: 0, to: null, monthly_rate_percent: 0, method: 'reducing_balance', max_tenure_months: 6, active: true }) },
}">
    <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 px-4 py-3">
        <p class="text-sm font-semibold text-gray-900">Asset Lending commercial model</p>
        <p class="text-xs text-gray-500 mt-1">Product rule (allowed mode) is separate from the supplier arrangement. The transaction snapshots one mode.</p>
        <dl class="mt-3 grid sm:grid-cols-2 gap-3 text-sm">
            <div>
                <dt class="text-xs text-gray-500">Default</dt>
                <dd class="font-semibold text-gray-900">Service / Collection</dd>
                <dd class="text-xs text-gray-500 mt-0.5">Capital Partner required: No</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">Alternative</dt>
                <dd class="font-semibold text-gray-900">Capital-funded purchase</dd>
                <dd class="text-xs text-gray-500 mt-0.5">Expose funding source when Capital Partner is Yes.</dd>
            </div>
        </dl>
    </div>

    <div class="flex items-center justify-between gap-3">
        <div class="inline-flex rounded-lg bg-slate-100 p-1">
            <button type="button" @click="tab = 'deposit'"
                    :class="tab === 'deposit' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600'"
                    class="px-3 py-1.5 rounded-md text-sm font-semibold">Deposit tiers</button>
            <button type="button" @click="tab = 'financing'"
                    :class="tab === 'financing' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600'"
                    class="px-3 py-1.5 rounded-md text-sm font-semibold">Financing tiers</button>
        </div>
        <button type="button" x-show="tab === 'deposit'" @click="addDeposit()" class="text-sm font-bold text-brand">+ Add tier</button>
        <button type="button" x-show="tab === 'financing'" x-cloak @click="addFinancing()" class="text-sm font-bold text-brand">+ Add tier</button>
    </div>

    <div x-show="tab === 'deposit'">
        <p class="text-xs text-gray-500 mb-2">Required customer deposit before the financed amount is calculated.</p>
        <template x-for="(row, i) in deposit" :key="'d'+i">
            <div class="grid grid-cols-12 gap-2 items-end mb-2">
                <div class="col-span-3">
                    <label class="text-xs font-medium text-gray-700">From</label>
                    <input type="text" inputmode="numeric" :name="'deposit_tiers['+i+'][from]'" x-model="row.from" class="mt-1 w-full rounded-lg border-gray-200 text-sm">
                </div>
                <div class="col-span-3">
                    <label class="text-xs font-medium text-gray-700">To</label>
                    <input type="text" inputmode="numeric" :name="'deposit_tiers['+i+'][to]'" x-model="row.to" class="mt-1 w-full rounded-lg border-gray-200 text-sm" placeholder="Open">
                </div>
                <div class="col-span-3">
                    <label class="text-xs font-medium text-gray-700">Deposit %</label>
                    <input type="text" inputmode="decimal" :name="'deposit_tiers['+i+'][percent]'" x-model="row.percent" class="mt-1 w-full rounded-lg border-gray-200 text-sm">
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

    <div x-show="tab === 'financing'" x-cloak>
        <p class="text-xs text-gray-500 mb-2">Monthly financing rate and longest tenure for the amount after deposit.</p>
        <template x-for="(row, i) in financing" :key="'f'+i">
            <div class="grid grid-cols-12 gap-2 items-end mb-2">
                <div class="col-span-3">
                    <label class="text-xs font-medium text-gray-700">From</label>
                    <input type="text" inputmode="numeric" :name="'financing_tiers['+i+'][from]'" x-model="row.from" class="mt-1 w-full rounded-lg border-gray-200 text-sm">
                </div>
                <div class="col-span-3">
                    <label class="text-xs font-medium text-gray-700">To</label>
                    <input type="text" inputmode="numeric" :name="'financing_tiers['+i+'][to]'" x-model="row.to" class="mt-1 w-full rounded-lg border-gray-200 text-sm" placeholder="Open">
                </div>
                <div class="col-span-2">
                    <label class="text-xs font-medium text-gray-700">Rate %</label>
                    <input type="text" inputmode="decimal" :name="'financing_tiers['+i+'][monthly_rate_percent]'" x-model="row.monthly_rate_percent" class="mt-1 w-full rounded-lg border-gray-200 text-sm">
                </div>
                <div class="col-span-2">
                    <label class="text-xs font-medium text-gray-700">Max tenure</label>
                    <input type="text" inputmode="numeric" :name="'financing_tiers['+i+'][max_tenure_months]'" x-model="row.max_tenure_months" class="mt-1 w-full rounded-lg border-gray-200 text-sm">
                </div>
                <div class="col-span-1 pb-2">
                    <input type="hidden" :name="'financing_tiers['+i+'][method]'" value="reducing_balance">
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
