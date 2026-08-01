<x-admin.layout title="Financial Overview" heading="Financial Overview" subheading="Year-to-date snapshot">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Portfolio outstanding</div>
            <div class="mt-2 text-2xl font-bold font-mono">{{ format_number($portfolioOutstanding, 2) }}</div>
            <div class="text-xs text-gray-500 mt-1">{{ format_number($activeLoanCount) }} active loans</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Disbursed YTD</div>
            <div class="mt-2 text-2xl font-bold text-brand font-mono">{{ format_number($disbursedYtd, 2) }}</div>
            <div class="text-xs text-gray-500 mt-1">This month: {{ format_number($disbursedMonth, 2) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Repaid YTD</div>
            <div class="mt-2 text-2xl font-bold text-emerald-600 font-mono">{{ format_number($repaidYtd, 2) }}</div>
            <div class="text-xs text-gray-500 mt-1">This month: {{ format_number($repaidMonth, 2) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Net income YTD</div>
            <div class="mt-2 text-2xl font-bold {{ $netIncomeYtd >= 0 ? 'text-emerald-600' : 'text-rose-600' }} font-mono">{{ format_number($netIncomeYtd, 2) }}</div>
            <div class="text-xs text-gray-500 mt-1">Income − expenses</div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 font-semibold">Income (YTD)</div>
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-gray-200">
                    <tr><td class="px-6 py-3">Interest income</td><td class="px-6 py-3 text-right font-mono">{{ format_number($interestYtd, 2) }}</td></tr>
                    <tr><td class="px-6 py-3">Penalty income</td><td class="px-6 py-3 text-right font-mono">{{ format_number($penaltyYtd, 2) }}</td></tr>
                    <tr class="bg-gray-50 font-semibold"><td class="px-6 py-3">Total income</td><td class="px-6 py-3 text-right font-mono">{{ format_number($interestYtd + $penaltyYtd, 2) }}</td></tr>
                </tbody>
            </table>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 font-semibold">Costs (YTD)</div>
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-gray-200">
                    <tr><td class="px-6 py-3">Operating expenses</td><td class="px-6 py-3 text-right font-mono">{{ format_number($expensesYtd, 2) }}</td></tr>
                    <tr><td class="px-6 py-3">Written off (YTD)</td><td class="px-6 py-3 text-right font-mono">{{ format_number($writtenOffYtd, 2) }}</td></tr>
                    <tr class="bg-gray-50 font-semibold"><td class="px-6 py-3">Total costs</td><td class="px-6 py-3 text-right font-mono">{{ format_number($expensesYtd + $writtenOffYtd, 2) }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Customers</div>
            <div class="mt-2 text-2xl font-bold font-mono">{{ format_number($totalCustomers) }}</div>
            <div class="text-xs text-gray-500 mt-1">Active borrowers: {{ format_number($activeCustomers) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Cash position (YTD net)</div>
            @php($cash = $repaidYtd - $disbursedYtd - $expensesYtd)
            <div class="mt-2 text-2xl font-bold {{ $cash >= 0 ? 'text-emerald-600' : 'text-rose-600' }} font-mono">{{ format_number($cash, 2) }}</div>
            <div class="text-xs text-gray-500 mt-1">Repayments − disbursements − expenses</div>
        </div>
    </div>
</x-admin.layout>
