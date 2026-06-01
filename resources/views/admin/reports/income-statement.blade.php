<x-admin.layout title="Income Statement" heading="Income Statement" :subheading="'Year-to-date · '.now()->startOfYear()->format('M Y').' – '.now()->format('M Y')">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Income</h3>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-100">
                    <tr><td class="py-2 text-gray-600">Interest income</td><td class="py-2 text-right font-mono">{{ number_format($interestIncome, 2) }}</td></tr>
                    <tr><td class="py-2 text-gray-600">Penalty income</td><td class="py-2 text-right font-mono">{{ number_format($penaltyIncome, 2) }}</td></tr>
                    <tr><td class="py-2 text-gray-600">Fee income</td><td class="py-2 text-right font-mono">{{ number_format($feeIncome, 2) }}</td></tr>
                    <tr><td class="py-2 text-gray-600">Other income</td><td class="py-2 text-right font-mono">{{ number_format($otherIncome, 2) }}</td></tr>
                    <tr class="font-semibold bg-emerald-50"><td class="py-2 px-2">Total income</td><td class="py-2 px-2 text-right font-mono">{{ number_format($totalIncome, 2) }}</td></tr>
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Expenses</h3>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-100">
                    @forelse ($expenses as $cat => $total)
                        <tr><td class="py-2 text-gray-600">{{ display_label($cat, 'account_type') }}</td><td class="py-2 text-right font-mono">{{ number_format($total, 2) }}</td></tr>
                    @empty
                        <tr><td class="py-2 text-gray-400 text-center" colspan="2">No expenses recorded YTD</td></tr>
                    @endforelse
                    <tr class="font-semibold bg-rose-50"><td class="py-2 px-2">Total expenses</td><td class="py-2 px-2 text-right font-mono">{{ number_format($totalExpense, 2) }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <div class="flex items-center justify-between">
            <span class="text-sm font-semibold uppercase tracking-wide text-gray-600">Net Income</span>
            <span class="text-3xl font-bold {{ $net >= 0 ? 'text-emerald-600' : 'text-rose-600' }} font-mono">{{ number_format($net, 2) }}</span>
        </div>
    </div>
</x-admin.layout>
