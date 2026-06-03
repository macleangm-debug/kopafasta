<x-admin.layout title="Cash Flow" heading="Cash Flow" :subheading="'Month to date · '.now()->startOfMonth()->format('M j').' – '.now()->format('M j, Y')">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Inflows (repayments)</div>
            <div class="mt-2 text-2xl font-bold text-emerald-600 font-mono">{{ format_number($inflows, 2) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Outflows (disbursements + expenses)</div>
            <div class="mt-2 text-2xl font-bold text-rose-600 font-mono">{{ format_number($outflows, 2) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Net cash flow</div>
            <div class="mt-2 text-2xl font-bold {{ $net >= 0 ? 'text-emerald-600' : 'text-rose-600' }} font-mono">{{ format_number($net, 2) }}</div>
        </div>
    </div>
</x-admin.layout>
