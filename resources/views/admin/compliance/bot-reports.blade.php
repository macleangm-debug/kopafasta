<x-admin.layout title="BOT Reports" heading="Bank of Tanzania Reports" subheading="Portfolio snapshot for regulator submission">

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        @php
            $cards = [
                ['Total outstanding', format_number($portfolio['total_outstanding'], 0), 'bg-indigo-50 text-indigo-700'],
                ['Disbursed YTD',     format_number($portfolio['total_disbursed_ytd'], 0), 'bg-emerald-50 text-emerald-700'],
                ['Active loans',      format_number($portfolio['active_loans']), 'bg-sky-50 text-sky-700'],
                ['Closed YTD',        format_number($portfolio['closed_loans_ytd']), 'bg-gray-50 text-gray-700'],
            ];
        @endphp
        @foreach ($cards as [$lbl, $val, $cls])
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5">
                <div class="text-xs text-gray-500 uppercase tracking-wide">{{ $lbl }}</div>
                <div class="mt-2 text-2xl font-bold {{ $cls }} inline-block rounded-md px-2">{{ $val }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Portfolio at Risk (PAR)</h3>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-100">
                    @php
                        $buckets = [
                            'PAR 1-30 days'  => $par['1_30'],
                            'PAR 31-60 days' => $par['31_60'],
                            'PAR 61-90 days' => $par['61_90'],
                            'PAR 90+ days'   => $par['90_plus'],
                        ];
                    @endphp
                    @foreach ($buckets as $lbl => $v)
                        <tr><td class="py-2 text-gray-600">{{ $lbl }}</td><td class="py-2 text-right font-mono">{{ format_number($v, 2) }}</td></tr>
                    @endforeach
                    <tr class="font-semibold border-t-2"><td class="py-2">Total PAR</td><td class="py-2 text-right font-mono">{{ format_number($par['total_par'], 2) }}</td></tr>
                    <tr class="font-semibold text-rose-600"><td class="py-2">PAR ratio</td><td class="py-2 text-right">{{ format_number($par['par_pct'], 2) }} %</td></tr>
                </tbody>
            </table>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">This month</h3>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-100">
                    <tr><td class="py-2 text-gray-600">Disbursements</td><td class="py-2 text-right font-mono">{{ format_number($monthly['disbursements'], 2) }}</td></tr>
                    <tr><td class="py-2 text-gray-600">Repayments collected</td><td class="py-2 text-right font-mono">{{ format_number($monthly['repayments'], 2) }}</td></tr>
                    <tr><td class="py-2 text-gray-600">Interest income</td><td class="py-2 text-right font-mono">{{ format_number($monthly['interest_income'], 2) }}</td></tr>
                    <tr><td class="py-2 text-gray-600">Penalty income</td><td class="py-2 text-right font-mono">{{ format_number($monthly['penalty_income'], 2) }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <p class="mt-6 text-xs text-gray-500">Written-off YTD: <span class="font-mono">{{ format_number($portfolio['written_off_ytd'], 2) }}</span></p>
</x-admin.layout>
