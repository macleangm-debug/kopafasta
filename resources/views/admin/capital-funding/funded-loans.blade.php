<x-admin.layout
    :title="__('admin.capital_funding.funded_loans')"
    :heading="__('admin.capital_funding.funded_loans')"
    :subheading="__('admin.capital_funding.subtitle')"
    :back-url="route('admin.capital-funding.index')"
    back-label="Capital funding">

    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        @if ($loans === [])
            <p class="p-6 text-sm text-gray-500">{{ __('admin.capital_funding.no_allocations') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase text-gray-500 border-b border-gray-200 bg-gray-50">
                        <tr>
                            <th class="text-left py-2 px-4">Loan</th>
                            <th class="text-left py-2 px-4">Borrower</th>
                            <th class="text-right py-2 px-4">Approved</th>
                            <th class="text-left py-2 px-4">Funding date</th>
                            <th class="text-left py-2 px-4">Partner contribution</th>
                            <th class="text-right py-2 px-4">Outstanding</th>
                            <th class="text-right py-2 px-4">Interest</th>
                            <th class="text-right py-2 px-4">Partner share</th>
                            <th class="text-right py-2 px-4">Company share</th>
                            <th class="text-left py-2 px-4">Status</th>
                            <th class="text-right py-2 px-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($loans as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 px-4 font-mono text-xs">{{ $row['loan_number'] }}</td>
                                <td class="py-2 px-4">{{ $row['borrower'] }}</td>
                                <td class="py-2 px-4 text-right font-mono">{{ format_money($row['approved_amount']) }}</td>
                                <td class="py-2 px-4 text-xs text-gray-600">{{ $row['funding_date']?->format('d M Y') ?? '—' }}</td>
                                <td class="py-2 px-4 text-xs max-w-xs truncate" title="{{ $row['partner_contributions'] }}">{{ $row['partner_contributions'] }}</td>
                                <td class="py-2 px-4 text-right font-mono">{{ format_money($row['outstanding_principal']) }}</td>
                                <td class="py-2 px-4 text-right font-mono">{{ format_money($row['interest_collected']) }}</td>
                                <td class="py-2 px-4 text-right font-mono text-emerald-800">{{ format_money($row['partner_profit_share']) }}</td>
                                <td class="py-2 px-4 text-right font-mono text-sky-800">{{ format_money($row['company_profit_share']) }}</td>
                                <td class="py-2 px-4 capitalize text-xs">{{ display_label($row['status'], 'loan_status') }}</td>
                                <td class="py-2 px-4 text-right">
                                    <a href="{{ route('admin.loans.show', $row['id']) }}" class="text-xs font-semibold text-brand hover:underline">View →</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-admin.layout>
