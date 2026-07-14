<x-admin.layout title="Dashboard" heading="Operations dashboard" subheading="Credit pipeline, portfolio, and capital at a glance">

    @php
        $stageRoute = [
            'submitted' => route('admin.loan-applications.new'),
            'screening' => route('admin.loan-applications.pipeline.under-review'),
            'credit_appraisal' => route('admin.loan-applications.pipeline.under-review'),
            'pre_approval' => route('admin.loan-applications.pre-approvals'),
            'approval' => route('admin.loan-applications.pipeline.approved'),
            'disbursement' => route('admin.loan-applications.pipeline.disbursement'),
        ];
        $cards = [
            ['My queue', format_number($stats['my_assigned_queue'] ?? 0), 'bg-amber-500', route('admin.loan-applications.index', ['mine' => 1])],
            ['Credit review', format_number($stats['credit_review_queue'] ?? 0), 'bg-sky-600', route('admin.loan-applications.pipeline.under-review')],
            ['Committee', format_number($stats['committee_queue'] ?? 0), 'bg-amber-600', route('admin.loan-applications.pre-approvals')],
            ['Incomplete', format_number($stats['incomplete_applications']), 'bg-orange-500', route('admin.loan-applications.incomplete')],
            ['Applications', format_number($stats['applications']), 'bg-slate-700', route('admin.loan-applications.index')],
            ['Active loans', format_number($stats['active_loans']), 'bg-emerald-600', route('admin.loans.index')],
            ['Portfolio', format_money($stats['portfolio_tzs'] ?? 0), 'bg-slate-600', route('admin.loans.active')],
            ['Capital free', format_money($stats['capital_available']), 'bg-teal-600', route('admin.capital-funding.index')],
        ];
        $ops = [
            ['Restructures', (int) ($stats['pending_restructures'] ?? 0), route('admin.restructure-requests.index')],
            ['Top-ups pending', (int) ($stats['pending_top_ups'] ?? 0), route('admin.top-up-requests.index')],
            ['Top-ups to disburse', (int) ($stats['approved_top_ups'] ?? 0), route('admin.top-up-requests.index')],
            ['Customers', (int) ($stats['customers'] ?? 0), route('admin.customers.index')],
        ];
    @endphp

    <div class="mb-6 rounded-2xl bg-gradient-to-br from-slate-900 via-slate-800 to-amber-900 px-5 sm:px-6 py-5 text-white shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-amber-300 font-semibold">{{ brand_name() }}</p>
                <p class="text-lg font-semibold mt-1">Credit &amp; portfolio overview</p>
                <p class="text-sm text-white/70 mt-1">Jump into your assigned work, committee queue, or capital position.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.loan-applications.index', ['mine' => 1]) }}"
                   class="inline-flex items-center rounded-lg bg-amber-500 hover:bg-amber-400 text-gray-900 text-xs font-semibold px-3 py-2">
                    Open my queue
                </a>
                <a href="{{ route('admin.credit-team.index') }}"
                   class="inline-flex items-center rounded-lg bg-white/10 hover:bg-white/15 ring-1 ring-white/20 text-white text-xs font-semibold px-3 py-2">
                    Credit team
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        @foreach ($cards as [$label, $value, $accent, $url])
            <a href="{{ $url }}" class="bg-white rounded-xl p-5 shadow-sm ring-1 ring-gray-200 relative overflow-hidden hover:ring-amber-300 transition block">
                <div class="absolute top-0 left-0 w-1 h-full {{ $accent }}"></div>
                <div class="pl-2">
                    <div class="text-xs font-medium uppercase tracking-wider text-gray-500">{{ $label }}</div>
                    <div class="mt-2 text-2xl font-bold text-gray-900 tabular-nums">{{ $value }}</div>
                </div>
            </a>
        @endforeach
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        @foreach ($ops as [$label, $value, $url])
            <a href="{{ $url }}" class="rounded-xl bg-white ring-1 ring-gray-200 px-4 py-3 hover:ring-amber-300 transition">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ $label }}</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-gray-900">{{ format_number($value) }}</p>
            </a>
        @endforeach
    </div>

    @php
        $stageCounts = $stats['stage_counts'] ?? [];
        $stageLabels = [
            'submitted' => 'Submitted',
            'screening' => 'Screening',
            'credit_appraisal' => 'Credit',
            'pre_approval' => 'Committee',
            'approval' => 'Approval',
            'disbursement' => 'Disbursement',
        ];
        $maxStage = max(1, ...array_values($stageCounts ?: [0]));
        $capitalAvailable = (float) ($stats['capital_available'] ?? 0);
        $capitalUtilized = (float) ($stats['capital_utilized'] ?? 0);
        $capitalTotal = max(1, $capitalAvailable + $capitalUtilized);
    @endphp
    @php
        $submissions14d = $stats['submissions_14d'] ?? [];
        $disbursements14d = $stats['disbursements_14d'] ?? [];
        $decisions30d = $stats['decisions_30d'] ?? ['approved' => 0, 'rejected' => 0, 'withdrawn' => 0];
        $maxSubmissions = max(1, ...array_column($submissions14d ?: [['count' => 0]], 'count'));
        $maxDisbursements = max(1, ...array_column($disbursements14d ?: [['count' => 0]], 'count'));
        $decisionTotal = max(1, array_sum($decisions30d));
    @endphp
    <div class="grid lg:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5 lg:col-span-2">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Submissions · last 14 days</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Daily application intake for managers</p>
                </div>
                <span class="text-xs font-semibold tabular-nums text-slate-700 bg-slate-100 px-2.5 py-1 rounded-lg">
                    {{ format_number(array_sum(array_column($submissions14d, 'count'))) }} total
                </span>
            </div>
            <div class="flex items-end gap-1.5 h-36">
                @foreach ($submissions14d as $point)
                    @php $height = max(4, (int) round(($point['count'] / $maxSubmissions) * 100)); @endphp
                    <div class="flex-1 flex flex-col items-center justify-end h-full gap-1 group">
                        <span class="text-[10px] font-semibold tabular-nums text-slate-600 opacity-0 group-hover:opacity-100 transition">{{ $point['count'] }}</span>
                        <div class="w-full rounded-t-md bg-gradient-to-t from-amber-600 to-amber-400" style="height: {{ $height }}%"></div>
                        <span class="text-[9px] text-gray-400 {{ $loop->index % 2 === 1 ? 'hidden sm:inline' : '' }}">{{ $point['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5">
            <div class="mb-4">
                <h2 class="text-sm font-semibold text-gray-900">Decisions · 30 days</h2>
                <p class="text-xs text-gray-500 mt-0.5">Approved vs declined outcomes</p>
            </div>
            <div class="space-y-3">
                @foreach ([
                    ['Approved', $decisions30d['approved'] ?? 0, 'bg-emerald-500'],
                    ['Rejected', $decisions30d['rejected'] ?? 0, 'bg-rose-500'],
                    ['Withdrawn', $decisions30d['withdrawn'] ?? 0, 'bg-slate-400'],
                ] as [$label, $count, $bar])
                    <div>
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="font-medium text-gray-700">{{ $label }}</span>
                            <span class="font-bold tabular-nums text-gray-900">{{ format_number($count) }}</span>
                        </div>
                        <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full rounded-full {{ $bar }}" style="width: {{ (int) round(($count / $decisionTotal) * 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-5 pt-4 border-t border-gray-100">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold mb-2">Disbursements · 14d</p>
                <div class="flex items-end gap-1 h-16">
                    @foreach ($disbursements14d as $point)
                        @php $height = max(3, (int) round(($point['count'] / $maxDisbursements) * 100)); @endphp
                        <div class="flex-1 rounded-t bg-teal-500/80" style="height: {{ $height }}%" title="{{ $point['label'] }}: {{ $point['count'] }}"></div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-semibold text-gray-900">Applications by stage</h2>
                <a href="{{ route('admin.loan-applications.index') }}" class="text-xs font-medium text-amber-600 hover:text-amber-700">Pipeline →</a>
            </div>
            <div class="space-y-3">
                @foreach ($stageLabels as $key => $label)
                    @php
                        $count = (int) ($stageCounts[$key] ?? 0);
                        $width = (int) round(($count / $maxStage) * 100);
                        $href = $stageRoute[$key] ?? route('admin.loan-applications.index');
                    @endphp
                    <a href="{{ $href }}" class="block group">
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="font-medium text-gray-700 group-hover:text-amber-800">{{ $label }}</span>
                            <span class="font-bold tabular-nums text-gray-900">{{ format_number($count) }}</span>
                        </div>
                        <div class="h-2.5 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full rounded-full bg-amber-500 transition-all" style="width: {{ $width }}%"></div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-semibold text-gray-900">Capital KPIs</h2>
                <a href="{{ route('admin.capital-funding.index') }}" class="text-xs font-medium text-amber-600 hover:text-amber-700">Funding →</a>
            </div>
            <div class="grid grid-cols-2 gap-4 mb-5">
                <div>
                    <p class="text-xs uppercase tracking-wider text-gray-500">Available</p>
                    <p class="text-xl font-bold text-teal-700 mt-1 tabular-nums">{{ format_money($capitalAvailable) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-gray-500">Utilized</p>
                    <p class="text-xl font-bold text-emerald-700 mt-1 tabular-nums">{{ format_money($capitalUtilized) }}</p>
                </div>
            </div>
            <p class="text-xs text-gray-500 mb-2">Utilization mix</p>
            <div class="h-4 rounded-full bg-gray-100 overflow-hidden flex">
                <div class="h-full bg-emerald-500" style="width: {{ round(($capitalUtilized / $capitalTotal) * 100) }}%"></div>
                <div class="h-full bg-teal-400" style="width: {{ round(($capitalAvailable / $capitalTotal) * 100) }}%"></div>
            </div>
            <div class="flex justify-between text-[11px] text-gray-500 mt-2">
                <span>Utilized {{ round(($capitalUtilized / $capitalTotal) * 100) }}%</span>
                <span>Available {{ round(($capitalAvailable / $capitalTotal) * 100) }}%</span>
            </div>
            <svg viewBox="0 0 120 70" class="mt-4 w-full max-w-[200px] mx-auto" aria-hidden="true">
                @php $utilPct = $capitalUtilized / $capitalTotal; @endphp
                <path d="M10 60 A50 50 0 0 1 110 60" fill="none" stroke="#e5e7eb" stroke-width="12" stroke-linecap="round"/>
                <path d="M10 60 A50 50 0 0 1 110 60" fill="none" stroke="#10b981" stroke-width="12" stroke-linecap="round"
                      stroke-dasharray="{{ round($utilPct * 157) }} 157"/>
                <text x="60" y="52" text-anchor="middle" class="fill-gray-800" font-size="14" font-weight="700">{{ round($utilPct * 100) }}%</text>
                <text x="60" y="64" text-anchor="middle" class="fill-gray-500" font-size="8">utilized</text>
            </svg>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden mb-6">
        <div class="px-5 py-3 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-900">Recent applications</h2>
            <a href="{{ route('admin.loan-applications.index') }}" class="text-xs font-medium text-amber-600 hover:text-amber-700">View all →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-5 py-2.5">Reference</th>
                        <th class="px-5 py-2.5">Customer</th>
                        <th class="px-5 py-2.5">Product</th>
                        <th class="px-5 py-2.5">Analyst</th>
                        <th class="px-5 py-2.5">Amount</th>
                        <th class="px-5 py-2.5">Stage</th>
                        <th class="px-5 py-2.5">Submitted</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($recentApplications as $app)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 font-mono text-xs">
                                <a href="{{ route('admin.loan-applications.show', $app) }}" class="text-amber-700 hover:underline font-semibold">
                                    {{ $app->application_number ?? $app->id }}
                                </a>
                            </td>
                            <td class="px-5 py-3">
                                {{ trim(($app->customer?->first_name ?? '').' '.($app->customer?->last_name ?? '')) ?: '—' }}
                            </td>
                            <td class="px-5 py-3 text-xs text-gray-600">{{ $app->product?->code ?? '—' }}</td>
                            <td class="px-5 py-3 text-xs text-gray-600">{{ $app->assignedAnalyst?->name ?? '—' }}</td>
                            <td class="px-5 py-3 tabular-nums">{{ format_money((float) ($app->requested_amount ?? 0)) }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-50 text-amber-900 ring-1 ring-amber-100">
                                    {{ display_label($app->current_stage ?: $app->status, 'application_stage') }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-gray-500">{{ $app->created_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-8 text-center text-gray-500">No applications yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-900">Capital under management</h2>
            <a href="{{ route('admin.capital-funding.index') }}" class="text-xs font-medium text-amber-600 hover:text-amber-700">Capital funding →</a>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 text-sm">
            @foreach ([
                'Total capital' => format_money($stats['capital_invested']),
                'Loans funded' => format_number($stats['loans_funded']),
                'Interest generated' => format_money($stats['interest_total']),
                'Company revenue share' => format_money($stats['company_share']),
                'Partner revenue share' => format_money($stats['partner_share']),
                'Outstanding exposure' => format_money($stats['outstanding_exposure']),
                'Available capital' => format_money($stats['capital_available']),
                'Capital utilized' => format_money($stats['capital_utilized']),
            ] as $label => $value)
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wider">{{ $label }}</div>
                    <div class="mt-1 font-bold text-gray-900 tabular-nums">{{ $value }}</div>
                </div>
            @endforeach
        </div>
    </div>

</x-admin.layout>
