<x-admin.layout
    :title="$record->name"
    :heading="$record->name"
    :subheading="$record->code">

    <div class="flex flex-wrap items-center gap-3 mb-6">
        <a href="{{ route('admin.capital-funding.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-800">← {{ __('admin.capital_funding.title') }}</a>
        <a href="{{ route('admin.lenders.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700">All partners</a>
        <a href="{{ route('admin.lenders.adjust-capital', $record) }}" class="text-sm font-semibold text-amber-800 bg-amber-50 hover:bg-amber-100 px-3 py-1.5 rounded-lg ring-1 ring-amber-200">{{ __('admin.capital_partner.adjust_capital') }}</a>
        <a href="{{ route('admin.lenders.edit', $record) }}" class="ml-auto text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 px-4 py-2 rounded-lg">Edit</a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 text-emerald-900 text-sm px-4 py-3 ring-1 ring-emerald-200">{{ session('status') }}</div>
    @endif

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
        @foreach ([
            'Capital contributed' => $metrics['capital_invested'],
            'Capital utilized' => $metrics['capital_utilized'],
            'Available capital' => $metrics['capital_available'],
            'Outstanding exposure' => $metrics['outstanding_exposure'],
            'Interest earned' => $metrics['interest_earned_total'],
            'Active loans' => $metrics['active_loans'],
        ] as $label => $value)
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-4">
                <p class="text-[10px] uppercase tracking-wider text-gray-500">{{ $label }}</p>
                <p class="mt-1 text-lg font-bold text-gray-900">
                    @if (is_numeric($value) && $label !== 'Active loans')
                        {{ format_money($value) }}
                    @else
                        {{ format_number($value) }}
                    @endif
                </p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        <div class="lg:col-span-2 bg-white rounded-xl ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-800 mb-4">Revenue share (interest)</h3>
            <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                <div class="rounded-lg bg-emerald-50 ring-1 ring-emerald-200 p-4">
                    <dt class="text-xs text-emerald-800">Partner share ({{ format_number($partnerSharePercent, 2) }}%)</dt>
                    <dd class="mt-1 text-xl font-bold text-emerald-900">{{ format_money($metrics['interest_earned_partner']) }}</dd>
                </div>
                <div class="rounded-lg bg-sky-50 ring-1 ring-sky-200 p-4">
                    <dt class="text-xs text-sky-800">Company share ({{ format_number($companySharePercent, 2) }}%)</dt>
                    <dd class="mt-1 text-xl font-bold text-sky-900">{{ format_money($metrics['interest_earned_company']) }}</dd>
                </div>
            </dl>
            @if ($record->revenue_share_percent !== null)
                <p class="mt-3 text-xs text-gray-500">This partner uses a custom revenue share. New loan allocations will use {{ format_number($partnerSharePercent, 2) }}% / {{ format_number($companySharePercent, 2) }}%.</p>
            @endif
        </div>

        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">Partner profile</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-gray-500">{{ __('admin.capital_partner.reference') }}</dt><dd class="font-mono font-medium">{{ $record->code }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Type</dt><dd class="font-medium capitalize">{{ $record->type }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Funding source</dt><dd class="font-medium capitalize">{{ ($record->funding_source ?? 'external') === 'internal' ? 'Internal (balance sheet)' : 'External (partner)' }}</dd></div>
                @if ($record->isExternalPartner())
                    <div class="flex justify-between gap-4"><dt class="text-gray-500">KYC status</dt><dd class="font-medium capitalize">{{ $record->kyc_status ?? 'pending' }}</dd></div>
                    @if ($record->registration_number)
                        <div class="flex justify-between gap-4"><dt class="text-gray-500">Registration</dt><dd class="font-medium font-mono text-xs">{{ $record->registration_number }}</dd></div>
                    @endif
                    @if ($record->tax_id)
                        <div class="flex justify-between gap-4"><dt class="text-gray-500">TIN</dt><dd class="font-medium font-mono text-xs">{{ $record->tax_id }}</dd></div>
                    @endif
                    @if ($record->license_number)
                        <div class="flex justify-between gap-4"><dt class="text-gray-500">License</dt><dd class="font-medium font-mono text-xs">{{ $record->license_number }}</dd></div>
                    @endif
                    @if ($record->kyc_verified_at)
                        <div class="flex justify-between gap-4"><dt class="text-gray-500">KYC verified</dt><dd class="font-medium">{{ $record->kyc_verified_at->format('d M Y') }}</dd></div>
                    @endif
                @endif
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Status</dt><dd class="font-medium capitalize">{{ $record->status }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-gray-500">Contact</dt><dd class="font-medium">{{ $record->contact_person ?: '—' }}</dd></div>
            </dl>
            @if ($record->status === 'active')
                <form method="post" action="{{ route('admin.lenders.withdrawal-request', $record) }}" class="mt-4 pt-4 border-t border-gray-100 space-y-2">
                    @csrf
                    <p class="text-xs font-semibold text-gray-700">{{ __('admin.capital_partner.request_withdrawal') }}</p>
                    <input type="number" name="amount" min="1" step="1" required placeholder="Amount" class="w-full text-sm rounded-lg border-gray-300">
                    <input type="text" name="notes" placeholder="Notes (optional)" class="w-full text-sm rounded-lg border-gray-300">
                    <button type="submit" class="w-full text-xs font-semibold text-amber-800 bg-amber-50 hover:bg-amber-100 py-2 rounded-lg ring-1 ring-amber-200">Submit for approval</button>
                </form>
            @endif
        </div>
    </div>

    @if ($pendingWithdrawals->isNotEmpty())
        <div class="mb-6 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm">
            <strong>{{ $pendingWithdrawals->count() }}</strong> pending withdrawal request(s).
            <a href="{{ route('admin.capital-funding.withdrawals') }}" class="font-semibold text-amber-800 hover:underline ml-1">Review →</a>
        </div>
    @endif

    <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 mb-6">
        <h3 class="text-sm font-semibold text-gray-800 mb-3">{{ __('admin.capital_partner.investor_ledger') }}</h3>
        @if ($ledger['entries'] === [])
            <p class="text-sm text-gray-500">{{ __('admin.capital_partner.no_ledger') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="uppercase text-gray-500 border-b">
                        <tr>
                            <th class="text-left py-2 pr-3">Date</th>
                            <th class="text-left py-2 pr-3">Category</th>
                            <th class="text-left py-2 pr-3">Description</th>
                            <th class="text-right py-2 pr-3">Credit</th>
                            <th class="text-right py-2 pr-3">Debit</th>
                            <th class="text-right py-2 pr-3">Available</th>
                            <th class="text-right py-2 pr-3">In use</th>
                            <th class="text-right py-2">Earnings</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-mono">
                        @foreach ($ledger['entries'] as $e)
                            <tr>
                                <td class="py-1.5 pr-3">{{ $e['at']?->format('d M Y') }}</td>
                                <td class="py-1.5 pr-3">{{ $e['category'] }}</td>
                                <td class="py-1.5 pr-3 max-w-[12rem] truncate" title="{{ $e['description'] }}">{{ $e['description'] }}</td>
                                <td class="py-1.5 pr-3 text-right text-emerald-700">{{ $e['credit'] > 0 ? format_money($e['credit']) : '—' }}</td>
                                <td class="py-1.5 pr-3 text-right text-red-700">{{ $e['debit'] > 0 ? format_money($e['debit']) : '—' }}</td>
                                <td class="py-1.5 pr-3 text-right">{{ format_money($e['available_capital']) }}</td>
                                <td class="py-1.5 pr-3 text-right">{{ format_money($e['capital_in_use']) }}</td>
                                <td class="py-1.5 text-right">{{ format_money($e['total_earnings']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">{{ __('admin.capital_partner.loan_allocations') }}</h3>
            @if ($allocations->isEmpty())
                <p class="text-sm text-gray-500">{{ __('admin.capital_partner.no_allocations') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-xs uppercase text-gray-500 border-b">
                            <tr>
                                <th class="text-left py-2">Loan</th>
                                <th class="text-right py-2">Allocated</th>
                                <th class="text-right py-2">Exposure</th>
                                <th class="text-right py-2">Partner int.</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($allocations as $a)
                                <tr>
                                    <td class="py-2">
                                        @if ($a->loan)
                                            <a href="{{ route('admin.loans.show', $a->loan) }}" class="font-mono text-xs text-amber-700 hover:underline">{{ $a->loan->loan_number }}</a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="py-2 text-right font-mono">{{ format_money($a->allocated_principal) }}</td>
                                    <td class="py-2 text-right font-mono">{{ format_money($a->outstanding_exposure) }}</td>
                                    <td class="py-2 text-right font-mono">{{ format_money($a->interest_earned_partner) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">{{ __('admin.capital_partner.funding_history') }}</h3>
            @if ($fundingHistory->isEmpty())
                <p class="text-sm text-gray-500">No deposits or withdrawals yet.</p>
            @else
                <ul class="space-y-2 text-sm">
                    @foreach ($fundingHistory as $txn)
                        <li class="flex justify-between gap-2 border-b border-gray-50 pb-2">
                            <span class="capitalize">{{ str_replace('_', ' ', $txn->type) }}</span>
                            <span class="font-mono">{{ format_money($txn->amount) }}</span>
                            <span class="text-xs text-gray-500">{{ $txn->processed_at?->format('d M Y') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 mb-6">
        <h3 class="text-sm font-semibold text-gray-800 mb-3">Funding pools (proportional allocation)</h3>
        @if ($pools === [])
            <p class="text-sm text-gray-500">No funding pools configured.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase text-gray-500 border-b border-gray-200">
                        <tr>
                            <th class="text-left py-2 pr-4">Pool</th>
                            <th class="text-left py-2 pr-4">Status</th>
                            <th class="text-right py-2 pr-4">Committed</th>
                            <th class="text-right py-2 pr-4">Deployed</th>
                            <th class="text-right py-2 pr-4">Available</th>
                            <th class="text-right py-2">Weight</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($pools as $pool)
                            <tr>
                                <td class="py-2 pr-4 font-medium">{{ $pool['name'] }}</td>
                                <td class="py-2 pr-4 capitalize text-xs">{{ $pool['status'] }}</td>
                                <td class="py-2 pr-4 text-right font-mono">{{ format_money($pool['committed']) }}</td>
                                <td class="py-2 pr-4 text-right font-mono">{{ format_money($pool['deployed']) }}</td>
                                <td class="py-2 pr-4 text-right font-mono">{{ format_money($pool['available']) }}</td>
                                <td class="py-2 text-right font-mono">{{ format_number($pool['share_pct'], 2) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-800 mb-3">{{ __('admin.capital_partner.audit_trail') }}</h3>
        @if ($auditTrail->isEmpty())
            <p class="text-sm text-gray-500">{{ __('admin.capital_partner.no_audit') }}</p>
        @else
            <ul class="space-y-2 text-sm">
                @foreach ($auditTrail as $log)
                    <li class="flex flex-wrap gap-x-3 gap-y-1 border-b border-gray-50 pb-2">
                        <span class="text-xs text-gray-500">{{ $log->created_at?->format('d M Y H:i') }}</span>
                        <span class="font-medium">{{ $log->event }}</span>
                        <span class="text-gray-600">{{ $log->user?->name ?? 'System' }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-admin.layout>
