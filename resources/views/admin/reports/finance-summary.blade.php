<x-admin.layout title="Finance Summary" heading="Finance Summary" subheading="Fees, interest, portfolio, and capital partner exposure">

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-[10px] uppercase text-gray-500">Fees collected</p>
            <p class="text-2xl font-bold">{{ format_money($feesCollected) }}</p>
        </div>
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-[10px] uppercase text-gray-500">Interest income</p>
            <p class="text-2xl font-bold">{{ format_money($interestIncome) }}</p>
        </div>
        <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 p-4">
            <p class="text-[10px] uppercase text-amber-700">Outstanding portfolio</p>
            <p class="text-2xl font-bold text-amber-900">{{ format_money($outstanding) }}</p>
            <p class="text-xs text-amber-700 mt-1">GL loans receivable: {{ format_money($loansReceivableGl) }}</p>
        </div>
        <div class="rounded-xl bg-sky-50 ring-1 ring-sky-200 p-4">
            <p class="text-[10px] uppercase text-sky-700">Partner exposure</p>
            <p class="text-2xl font-bold text-sky-900">{{ format_money($partnerExposure) }}</p>
            <p class="text-xs text-sky-700 mt-1">Interest split {{ format_number($partnerSharePct, 0) }}% / {{ format_number(100 - $partnerSharePct, 0) }}%</p>
        </div>
    </div>
</x-admin.layout>
