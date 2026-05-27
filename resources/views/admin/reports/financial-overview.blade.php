<x-admin.layout title="Financial Overview" heading="Financial Overview" subheading="Year-to-date snapshot">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Portfolio outstanding</div>
            <div class="mt-2 text-2xl font-bold font-mono">{{ number_format($portfolioOutstanding, 2) }}</div>
            <div class="text-xs text-gray-500 mt-1">{{ number_format($activeLoanCount) }} active loans</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Disbursed YTD</div>
            <div class="mt-2 text-2xl font-bold text-indigo-600 font-mono">{{ number_format($disbursedYtd, 2) }}</div>
            <div class="text-xs text-gray-500 mt-1">This month: {{ number_format($disbursedMonth, 2) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Repaid YTD</div>
            <div class="mt-2 text-2xl font-bold text-emerald-600 font-mono">{{ number_format($repaidYtd, 2) }}</div>
            <div class="text-xs text-gray-500 mt-1">This month: {{ number_format($repaidMonth, 2) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Net income YTD</div>
            <div class="mt-2 text-2xl font-bold {{ $netIncomeYtd >= 0 ? 'text-emerald-600' : 'text-rose-600' }} font-mono">{{ number_format($netIncomeYtd, 2) }}</div>
            <div class="text-xs text-gray-500 mt-1">Income − expenses</div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 font-semibold">Income (YTD)</div>
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-gray-200">
                    <tr><td class="px-6 py-3">Interest income</td><td class="px-6 py-3 text-right font-mono">{{ number_format($interestYtd, 2) }}</td></tr>
                    <tr><td class="px-6 py-3">Penalty income</td><td class="px-6 py-3 text-right font-mono">{{ number_format($penaltyYtd, 2) }}</td></tr>
                    <tr class="bg-gray-50 font-semibold"><td class="px-6 py-3">Total income</td><td class="px-6 py-3 text-right font-mono">{{ number_format($interestYtd + $penaltyYtd, 2) }}</td></tr>
                </tbody>
            </table>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 font-semibold">Costs (YTD)</div>
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-gray-200">
                    <tr><td class="px-6 py-3">Operating expenses</td><td class="px-6 py-3 text-right font-mono">{{ number_format($expensesYtd, 2) }}</td></tr>
                    <tr><td class="px-6 py-3">Written off (YTD)</td><td class="px-6 py-3 text-right font-mono">{{ number_format($writtenOffYtd, 2) }}</td></tr>
                    <tr class="bg-gray-50 font-semibold"><td class="px-6 py-3">Total costs</td><td class="px-6 py-3 text-right font-mono">{{ number_format($expensesYtd + $writtenOffYtd, 2) }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Customers</div>
            <div class="mt-2 text-2xl font-bold font-mono">{{ number_format($totalCustomers) }}</div>
            <div class="text-xs text-gray-500 mt-1">Active borrowers: {{ number_format($activeCustomers) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Cash position (YTD net)</div>
            @php($cash = $repaidYtd - $disbursedYtd - $expensesYtd)
            <div class="mt-2 text-2xl font-bold {{ $cash >= 0 ? 'text-emerald-600' : 'text-rose-600' }} font-mono">{{ number_format($cash, 2) }}</div>
            <div class="text-xs text-gray-500 mt-1">Repayments − disbursements − expenses</div>
        </div>
    </div>
</x-admin.layout>
