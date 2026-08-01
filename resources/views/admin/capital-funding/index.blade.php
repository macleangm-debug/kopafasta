<x-admin.layout
    :title="__('admin.capital_funding.title')"
    :heading="__('admin.capital_funding.title')"
    :subheading="__('admin.capital_funding.subtitle')">

    <div class="mb-6 rounded-2xl bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-5 text-white shadow-sm ring-1 ring-brand/20">
        <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">How capital funding works</p>
        <ol class="mt-3 space-y-1.5 text-sm text-white/90 list-decimal list-inside">
            <li><strong class="text-white">Capital partners</strong> commit money into the platform.</li>
            <li><strong class="text-white">Funding pools</strong> are buckets of that capital (why you add a pool: to ring-fence a partner’s commitment for deployment).</li>
            <li>When a loan is funded, the system auto-creates a <strong class="text-white">loan allocation</strong> from a pool and splits interest (partner vs company) using Settings → Finance defaults.</li>
            <li>This page = dashboard. Setup lives under Capital partners / Funding pools. Allocations list is the ledger of deployed capital.</li>
        </ol>
    </div>

    <div class="mb-6 rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-3 text-sm text-sky-900">
        {{ __('admin.capital_funding.hint', [
            'partner' => format_number($partnerSharePct, 0),
            'company' => format_number($companySharePct, 0),
        ]) }}
        <a href="{{ route('admin.settings.finance') }}" class="ml-1 font-semibold underline">Edit interest share in settings →</a>
    </div>

    <div class="mb-4 flex flex-wrap gap-2 text-xs">
        <a href="{{ route('admin.capital-funding.funded-loans') }}" class="font-semibold text-brand hover:underline">{{ __('admin.capital_funding.funded_loans') }} →</a>
        <a href="{{ route('admin.capital-funding.withdrawals') }}" class="font-semibold text-brand hover:underline">
            {{ __('admin.capital_funding.withdrawals') }}
            @if (($summary['pending_withdrawals'] ?? 0) > 0)
                <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-amber-200 text-amber-900">{{ $summary['pending_withdrawals'] }}</span>
            @endif
            →
        </a>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 mb-6">
        @foreach ([
            __('admin.capital_funding.invested') => $summary['capital_invested'],
            __('admin.capital_funding.utilized') => $summary['capital_utilized'],
            __('admin.capital_funding.available') => $summary['capital_available'],
            __('admin.capital_funding.exposure') => $summary['outstanding_exposure'],
            __('admin.capital_funding.interest_total') => $summary['interest_earned_total'],
            __('admin.capital_funding.partner_share_payable') => $summary['interest_earned_partner'],
            __('admin.capital_funding.company_share_earned') => $summary['interest_earned_company'],
            __('admin.capital_funding.active_loans') => $summary['active_loans'],
            __('admin.capital_funding.loans_funded') => $summary['loans_funded'],
            __('admin.capital_funding.default_ratio') => format_number($summary['default_ratio_pct'], 2).'%',
            __('admin.capital_funding.active_partners') => $summary['active_partners'],
        ] as $label => $value)
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-4">
                <p class="text-[10px] uppercase tracking-wider text-gray-500">{{ $label }}</p>
                <p class="mt-1 text-lg font-bold text-gray-900">
                    @if (in_array($label, [__('admin.capital_funding.active_loans'), __('admin.capital_funding.active_partners'), __('admin.capital_funding.loans_funded'), __('admin.capital_funding.default_ratio')], true) || is_string($value))
                        {{ is_string($value) ? $value : format_number($value) }}
                    @else
                        {{ format_money($value) }}
                    @endif
                </p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-800 mb-4">{{ __('admin.capital_funding.revenue_share') }}</h3>
            <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                <div class="rounded-lg bg-emerald-50 ring-1 ring-emerald-200 p-4">
                    <dt class="text-xs text-emerald-800">{{ __('admin.capital_funding.partner_share', ['pct' => format_number($partnerSharePct, 0)]) }}</dt>
                    <dd class="mt-1 text-xl font-bold text-emerald-900">{{ format_money($summary['interest_earned_partner']) }}</dd>
                </div>
                <div class="rounded-lg bg-sky-50 ring-1 ring-sky-200 p-4">
                    <dt class="text-xs text-sky-800">{{ __('admin.capital_funding.company_share', ['pct' => format_number($companySharePct, 0)]) }}</dt>
                    <dd class="mt-1 text-xl font-bold text-sky-900">{{ format_money($summary['interest_earned_company']) }}</dd>
                </div>
            </dl>
        </div>

        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-800 mb-2">{{ __('admin.capital_funding.allocation_method') }}</h3>
            <p class="text-sm text-gray-600">{{ __('admin.capital_funding.proportional_note') }}</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route('admin.lenders.index') }}" class="text-xs font-semibold text-brand hover:underline">{{ __('admin.capital_funding.manage_partners') }} →</a>
                <a href="{{ route('admin.funding-pools.index') }}" class="text-xs font-semibold text-brand hover:underline">{{ __('admin.capital_funding.manage_pools') }} →</a>
                <a href="{{ route('admin.loan-products.index') }}" class="text-xs font-semibold text-brand hover:underline">{{ __('admin.capital_funding.loan_products') }} →</a>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-gray-800">{{ __('admin.capital_funding.partners_table') }}</h3>
            <a href="{{ route('admin.lenders.create') }}" class="text-xs font-semibold text-brand bg-brand-gold hover:brightness-95 px-3 py-1.5 rounded-lg">+ {{ __('admin.capital_funding.new_partner') }}</a>
        </div>
        @if ($partners === [])
            <p class="text-sm text-gray-500">{{ __('admin.capital_funding.no_partners') }}</p>
            <p class="text-xs text-gray-500 mt-2">{{ __('admin.capital_funding.seed_hint') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase text-gray-500 border-b border-gray-200">
                        <tr>
                            <th class="text-left py-2 pr-4">Partner</th>
                            <th class="text-right py-2 pr-4">Invested</th>
                            <th class="text-right py-2 pr-4">Utilized</th>
                            <th class="text-right py-2 pr-4">Available</th>
                            <th class="text-right py-2 pr-4">Exposure</th>
                            <th class="text-right py-2 pr-4">Partner int.</th>
                            <th class="text-right py-2 pr-4">Company int.</th>
                            <th class="text-right py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($partners as $p)
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 pr-4">
                                    <div class="font-medium">{{ $p['name'] }}</div>
                                    <div class="text-[10px] font-mono text-gray-500">{{ $p['code'] }}</div>
                                </td>
                                <td class="py-2 pr-4 text-right font-mono">{{ format_money($p['capital_invested']) }}</td>
                                <td class="py-2 pr-4 text-right font-mono">{{ format_money($p['capital_utilized']) }}</td>
                                <td class="py-2 pr-4 text-right font-mono">{{ format_money($p['capital_available']) }}</td>
                                <td class="py-2 pr-4 text-right font-mono">{{ format_money($p['outstanding_exposure']) }}</td>
                                <td class="py-2 pr-4 text-right font-mono text-emerald-800">{{ format_money($p['interest_earned_partner']) }}</td>
                                <td class="py-2 pr-4 text-right font-mono text-sky-800">{{ format_money($p['interest_earned_company']) }}</td>
                                <td class="py-2 text-right">
                                    <a href="{{ route('admin.lenders.show', $p['id']) }}" class="text-xs font-semibold text-brand hover:underline">{{ __('admin.capital_funding.view') }} →</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-800 mb-4">{{ __('admin.capital_funding.recent_allocations') }}</h3>
        @if ($recentAllocations->isEmpty())
            <p class="text-sm text-gray-500">{{ __('admin.capital_funding.no_allocations') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase text-gray-500 border-b border-gray-200">
                        <tr>
                            <th class="text-left py-2 pr-4">Loan</th>
                            <th class="text-left py-2 pr-4">Partner</th>
                            <th class="text-left py-2 pr-4">Pool</th>
                            <th class="text-right py-2 pr-4">Allocated</th>
                            <th class="text-right py-2 pr-4">%</th>
                            <th class="text-right py-2 pr-4">Exposure</th>
                            <th class="text-right py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($recentAllocations as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 pr-4 font-mono text-xs">{{ $row->loan?->loan_number ?? '—' }}</td>
                                <td class="py-2 pr-4">{{ $row->lender?->name ?? '—' }}</td>
                                <td class="py-2 pr-4 text-xs text-gray-600">{{ $row->pool?->name ?? '—' }}</td>
                                <td class="py-2 pr-4 text-right font-mono">{{ format_money($row->allocated_principal) }}</td>
                                <td class="py-2 pr-4 text-right font-mono">{{ format_number($row->allocation_percent, 2) }}%</td>
                                <td class="py-2 pr-4 text-right font-mono">{{ format_money($row->outstanding_exposure) }}</td>
                                <td class="py-2 text-right">
                                    @if ($row->loan)
                                        <a href="{{ route('admin.loans.show', $row->loan) }}" class="text-xs font-semibold text-brand hover:underline">{{ __('admin.capital_funding.view_loan') }} →</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-admin.layout>
