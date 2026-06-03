<x-admin.layout title="Balance Sheet" heading="Balance Sheet" :subheading="'As at '.now()->format('Y-m-d')">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Total assets</div>
            <div class="mt-2 text-2xl font-bold text-emerald-600 font-mono">{{ format_number($assets, 2) }}</div>
            <p class="mt-3 text-xs text-gray-500">Includes loans outstanding: <span class="font-mono">{{ format_number($loansOutstanding, 2) }}</span></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Total liabilities</div>
            <div class="mt-2 text-2xl font-bold text-rose-600 font-mono">{{ format_number($liabilities, 2) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Equity</div>
            <div class="mt-2 text-2xl font-bold text-indigo-600 font-mono">{{ format_number($equity, 2) }}</div>
        </div>
    </div>

    <div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 flex items-center justify-between">
        <span class="text-sm font-semibold uppercase tracking-wide text-gray-600">Assets − (Liabilities + Equity)</span>
        @php($diff = $assets - $liabilities - $equity)
        <span class="text-2xl font-bold {{ abs($diff) < 0.01 ? 'text-emerald-600' : 'text-amber-600' }} font-mono">{{ format_number($diff, 2) }}</span>
    </div>
</x-admin.layout>
