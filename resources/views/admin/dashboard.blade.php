<x-admin.layout title="Dashboard" heading="Dashboard" subheading="Overview of operations">

    {{-- KPI cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">
        @php
            $cards = [
                ['Customers', format_number($stats['customers']), 'bg-blue-500', null],
                ['Applications', format_number($stats['applications']), 'bg-violet-500', route('admin.loan-applications.index')],
                ['Incomplete apps', format_number($stats['incomplete_applications']), 'bg-amber-500', route('admin.loan-applications.incomplete')],
                ['Active loans', format_number($stats['active_loans']), 'bg-amber-600', route('admin.loans.index')],
                ['Capital available', format_money($stats['capital_available']), 'bg-teal-500', route('admin.capital-funding.index')],
                ['Capital utilized', format_money($stats['capital_utilized']), 'bg-emerald-500', route('admin.capital-funding.index')],
                ['Restructure queue', format_number($stats['pending_restructures'] ?? 0), 'bg-orange-500', route('admin.restructure-requests.index')],
                ['Top-up queue', format_number($stats['pending_top_ups'] ?? 0), 'bg-rose-500', route('admin.top-up-requests.index')],
                ['Top-ups to disburse', format_number($stats['approved_top_ups'] ?? 0), 'bg-sky-500', route('admin.top-up-requests.index', ['status' => 'approved'])],
            ];
        @endphp

        @foreach ($cards as [$label, $value, $accent, $url])
            @if ($url)
                <a href="{{ $url }}" class="bg-white rounded-xl p-5 shadow-sm ring-1 ring-gray-200 relative overflow-hidden hover:ring-amber-300 transition block">
            @else
                <div class="bg-white rounded-xl p-5 shadow-sm ring-1 ring-gray-200 relative overflow-hidden">
            @endif
                <div class="absolute top-0 left-0 w-1 h-full {{ $accent }}"></div>
                <div class="pl-2">
                    <div class="text-xs font-medium uppercase tracking-wider text-gray-500">{{ $label }}</div>
                    <div class="mt-2 text-2xl font-bold text-gray-900">{{ $value }}</div>
                </div>
            @if ($url)
                </a>
            @else
                </div>
            @endif
        @endforeach
    </div>

    {{-- Pipeline funnel + capital bars --}}
    @php
        $stageCounts = $stats['stage_counts'] ?? [];
        $stageLabels = [
            'submitted' => 'Submitted',
            'screening' => 'Screening',
            'credit_appraisal' => 'Credit',
            'pre_approval' => 'Pre-approval',
            'approval' => 'Approval',
            'disbursement' => 'Disbursement',
        ];
        $maxStage = max(1, ...array_values($stageCounts ?: [0]));
        $capitalAvailable = (float) ($stats['capital_available'] ?? 0);
        $capitalUtilized = (float) ($stats['capital_utilized'] ?? 0);
        $capitalTotal = max(1, $capitalAvailable + $capitalUtilized);
    @endphp
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
                    @endphp
                    <div>
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="font-medium text-gray-700">{{ $label }}</span>
                            <span class="font-bold tabular-nums text-gray-900">{{ format_number($count) }}</span>
                        </div>
                        <div class="h-2.5 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full rounded-full bg-amber-500 transition-all" style="width: {{ $width }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
            {{-- Simple SVG funnel silhouette --}}
            <svg viewBox="0 0 200 80" class="mt-5 w-full h-16 text-amber-500/30" aria-hidden="true">
                @php $n = count($stageLabels); $i = 0; @endphp
                @foreach ($stageLabels as $key => $label)
                    @php
                        $count = (int) ($stageCounts[$key] ?? 0);
                        $ratio = $count / $maxStage;
                        $half = 10 + (70 * (1 - $ratio));
                        $y = ($i / max(1, $n - 1)) * 70;
                        $nextI = min($n - 1, $i + 1);
                        $nextKey = array_keys($stageLabels)[$nextI] ?? $key;
                        $nextCount = (int) ($stageCounts[$nextKey] ?? 0);
                        $nextRatio = $nextCount / $maxStage;
                        $nextHalf = 10 + (70 * (1 - $nextRatio));
                        $nextY = ($nextI / max(1, $n - 1)) * 70;
                        $i++;
                    @endphp
                    @if ($i < $n)
                        <polygon points="{{ 100 - $half }},{{ $y }} {{ 100 + $half }},{{ $y }} {{ 100 + $nextHalf }},{{ $nextY }} {{ 100 - $nextHalf }},{{ $nextY }}"
                                 fill="currentColor" opacity="{{ 0.35 + ($ratio * 0.45) }}"/>
                    @endif
                @endforeach
            </svg>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-semibold text-gray-900">Capital KPIs</h2>
                <a href="{{ route('admin.capital-funding.index') }}" class="text-xs font-medium text-amber-600 hover:text-amber-700">Funding →</a>
            </div>
            <div class="grid grid-cols-2 gap-4 mb-5">
                <div>
                    <p class="text-xs uppercase tracking-wider text-gray-500">Available</p>
                    <p class="text-xl font-bold text-teal-700 mt-1">{{ format_money($capitalAvailable) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-gray-500">Utilized</p>
                    <p class="text-xl font-bold text-emerald-700 mt-1">{{ format_money($capitalUtilized) }}</p>
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
                @php
                    $utilPct = $capitalUtilized / $capitalTotal;
                    $availPct = $capitalAvailable / $capitalTotal;
                    $utilAngle = $utilPct * 180;
                @endphp
                <path d="M10 60 A50 50 0 0 1 110 60" fill="none" stroke="#e5e7eb" stroke-width="12" stroke-linecap="round"/>
                <path d="M10 60 A50 50 0 0 1 110 60" fill="none" stroke="#10b981" stroke-width="12" stroke-linecap="round"
                      stroke-dasharray="{{ round($utilPct * 157) }} 157"/>
                <text x="60" y="52" text-anchor="middle" class="fill-gray-800" font-size="14" font-weight="700">{{ round($utilPct * 100) }}%</text>
                <text x="60" y="64" text-anchor="middle" class="fill-gray-500" font-size="8">utilized</text>
            </svg>
        </div>
    </div>

    {{-- Recent applications --}}
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-900">Recent Applications</h2>
            <a href="{{ route('admin.loans.index') }}" class="text-xs font-medium text-amber-600 hover:text-amber-700">View all →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-5 py-2.5">Reference</th>
                        <th class="px-5 py-2.5">Customer</th>
                        <th class="px-5 py-2.5">Amount</th>
                        <th class="px-5 py-2.5">Status</th>
                        <th class="px-5 py-2.5">Submitted</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($recentApplications as $app)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 font-mono text-xs">{{ $app->application_number ?? $app->id }}</td>
                            <td class="px-5 py-3">
                                {{ trim(($app->customer?->first_name ?? '').' '.($app->customer?->last_name ?? '')) ?: '—' }}
                            </td>
                            <td class="px-5 py-3">{{ format_money((float) ($app->requested_amount ?? 0)) }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    @class([
                                        'bg-emerald-100 text-emerald-800' => $app->status === 'approved',
                                        'bg-red-100 text-red-800'         => $app->status === 'rejected',
                                        'bg-amber-100 text-amber-800'     => in_array($app->status, ['pending','submitted','under_review']),
                                        'bg-gray-100 text-gray-800'       => ! in_array($app->status, ['approved','rejected','pending','submitted','under_review']),
                                    ])">
                                    {{ display_label($app->status, 'application_status') }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-gray-500">{{ $app->created_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-gray-500">No applications yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5">
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
                    <div class="mt-1 font-bold text-gray-900">{{ $value }}</div>
                </div>
            @endforeach
        </div>
    </div>

</x-admin.layout>
