<x-admin.layout title="PAR Report" heading="Portfolio At Risk (PAR)" subheading="Outstanding balance in arrears buckets">

    <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 mb-4">
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-[10px] uppercase text-gray-500">Active portfolio</p>
            <p class="text-2xl font-bold">{{ format_money($portfolio) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $loanCount }} loans</p>
        </div>
        <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 p-4">
            <p class="text-[10px] uppercase text-amber-700">PAR 30</p>
            <p class="text-2xl font-bold text-amber-900">{{ format_number($rates[30], 2) }}%</p>
            <p class="text-xs text-amber-700 mt-1">{{ format_money($atRisk[30]) }} at risk</p>
        </div>
        <div class="rounded-xl bg-red-50 ring-1 ring-red-200 p-4">
            <p class="text-[10px] uppercase text-red-700">PAR 90</p>
            <p class="text-2xl font-bold text-red-900">{{ format_number($rates[90], 2) }}%</p>
            <p class="text-xs text-red-700 mt-1">{{ format_money($atRisk[90]) }} at risk</p>
        </div>
    </div>

    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-5 py-3">Bucket</th>
                    <th class="px-5 py-3 text-right">Outstanding at risk</th>
                    <th class="px-5 py-3 text-right">PAR %</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ([1 => 'PAR 1+', 7 => 'PAR 7+', 30 => 'PAR 30+', 60 => 'PAR 60+', 90 => 'PAR 90+', 180 => 'PAR 180+'] as $days => $label)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 font-medium">{{ $label }}</td>
                        <td class="px-5 py-3 text-right">{{ format_money($atRisk[$days]) }}</td>
                        <td class="px-5 py-3 text-right font-semibold">{{ format_number($rates[$days], 2) }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-admin.layout>
