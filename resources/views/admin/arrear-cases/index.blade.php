<x-admin.layout
    title="Collection cases"
    heading="Collection cases"
    subheading="Open arrears cases with follow-up history and assigned collectors">

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-[10px] uppercase tracking-wider text-gray-500">Open cases</p>
            <p class="text-2xl font-bold text-gray-900">{{ $counts['open'] }}</p>
        </div>
        <div class="rounded-xl bg-red-50 ring-1 ring-red-200 p-4">
            <p class="text-[10px] uppercase tracking-wider text-red-600">Arrears exposure</p>
            <p class="text-2xl font-bold text-red-800">{{ format_money($totals['amount_in_arrears']) }}</p>
        </div>
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-[10px] uppercase tracking-wider text-gray-500">Penalties accrued</p>
            <p class="text-2xl font-bold text-gray-900">{{ format_money($totals['penalties']) }}</p>
        </div>
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4 flex items-end">
            <a href="{{ route('admin.loans.arrears') }}" class="text-sm font-semibold text-amber-700 hover:text-amber-800">View loans in arrears →</a>
        </div>
    </div>

    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ([
            'open' => $counts['open'].' open',
            'resolved' => 'Resolved',
            'escalated' => 'Escalated',
            'all' => 'All',
        ] as $key => $label)
            <a href="{{ route('admin.arrear-cases.index', ['status' => $key]) }}"
               class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $status === $key ? 'bg-amber-500 text-gray-900' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-5 py-3">Loan</th>
                        <th class="px-5 py-3">Customer</th>
                        <th class="px-5 py-3">Days past due</th>
                        <th class="px-5 py-3 text-right">In arrears</th>
                        <th class="px-5 py-3">Assigned</th>
                        <th class="px-5 py-3">Last follow-up</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($cases as $case)
                        @php
                            $loan = $case->loan;
                            $customer = $loan?->customer;
                            $name = trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));
                        @endphp
                        <tr class="hover:bg-gray-50 align-top">
                            <td class="px-5 py-3 font-mono text-xs font-semibold">
                                @if ($loan)
                                    <a href="{{ route('admin.loans.show', $loan) }}" class="text-amber-700 hover:text-amber-800">{{ $loan->loan_number }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="font-medium">{{ $name ?: '—' }}</div>
                                <div class="text-xs text-gray-500">{{ $loan?->product?->name }}</div>
                            </td>
                            <td class="px-5 py-3">{{ $case->days_past_due }}</td>
                            <td class="px-5 py-3 text-right font-semibold text-red-700">{{ format_money($case->amount_in_arrears) }}</td>
                            <td class="px-5 py-3 text-xs">{{ $case->assignee?->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-xs text-gray-500">{{ optional($case->last_follow_up_at)->format('d M Y H:i') ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <span class="text-xs font-semibold rounded-full px-2.5 py-1 capitalize
                                    {{ $case->status === 'open' ? 'bg-red-100 text-red-800' : ($case->status === 'escalated' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800') }}">
                                    {{ $case->status }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('admin.arrear-cases.show', $case) }}" class="text-amber-700 font-semibold text-xs hover:underline">Open case</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-10 text-center text-gray-500">No collection cases found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($cases->hasPages())
            <div class="px-5 py-3 border-t border-gray-100">{{ $cases->links() }}</div>
        @endif
    </div>
</x-admin.layout>
