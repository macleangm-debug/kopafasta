<x-admin.show-page
    :title="$record->name"
    :heading="$record->name"
    :subheading="$record->vendor_number"
    :backUrl="route('admin.partners.index')"
    :editUrl="route('admin.partners.edit', $record)"
    :fields="array_filter([
        'Partner #'  => $record->vendor_number,
        'Category'  => ucfirst(str_replace('_', ' ', $record->category)),
        'Status'    => ucfirst($record->status ?? ''),
        'Performance' => isset($efficiency) && is_array($efficiency)
            ? ($efficiency['status_label'] ?? $efficiency['band_label'] ?? null)
            : null,
        'Can receive work' => isset($jobEligibility)
            ? (($jobEligibility['can_receive'] ?? false) ? 'Yes' : ('No — '.$jobEligibility['reason_label']))
            : null,
        'Phone'     => ['value' => $record->phone, 'phone' => true],
        'Membership' => isset($profileTabs['membership'])
            ? (($membership ?? $fieldMembership ?? null)['label'] ?? null)
            : null,
        'Open jobs' => isset($profileTabs['jobs'])
            ? ((($openTasks ?? collect())->count() > 0) ? $openTasks->count().' ongoing' : 'None')
            : null,
        'Jobs completed' => isset($profileTabs['jobs']) && is_array($efficiency ?? null)
            ? (string) ($efficiency['completed'] ?? 0)
            : null,
        'On-time rate' => isset($profileTabs['jobs']) && is_array($efficiency ?? null)
            ? (($efficiency['on_time_rate'] ?? 0).'%')
            : null,
        'SLA breaches' => is_array($efficiency ?? null) ? (string) ($efficiency['sla_breaches'] ?? 0) : null,
        'Reassignments' => isset($profileTabs['jobs']) && is_array($efficiency ?? null)
            ? (string) ($efficiency['reassignments'] ?? 0)
            : null,
        'Avg turnaround' => isset($profileTabs['jobs']) && is_array($efficiency ?? null) && ($efficiency['avg_turnaround_hours'] ?? null) !== null
            ? $efficiency['avg_turnaround_hours'].'h'
            : null,
        'Open cases' => isset($profileTabs['cases']) && ($recoveryAssignments ?? collect())->filter(fn ($a) => $a->isOpen())->count() > 0
            ? ($recoveryAssignments->filter(fn ($a) => $a->isOpen())->count()).' open'
            : (isset($profileTabs['cases']) ? 'None' : null),
        'Cases completed' => isset($profileTabs['cases']) && is_array($recoveryStats ?? null)
            ? (string) ($recoveryStats['completed_cases'] ?? 0)
            : null,
        'Escalations' => isset($profileTabs['cases']) && is_array($efficiency ?? null)
            ? (string) ($efficiency['escalated'] ?? 0)
            : null,
        'Applications' => isset($profileTabs['pipeline'])
            ? (string) (int) (($affiliateStats ?? [])['applications'] ?? 0)
            : null,
        'Listings' => isset($profileTabs['listings'])
            ? (($listings ?? collect())->count().' assets')
            : null,
        'Active loans' => isset($profileTabs['capital']) && is_array($capitalMetrics ?? null)
            ? (int) ($capitalMetrics['active_loans'] ?? 0)
            : null,
    ])">

@php
    $openTasks = $openTasks ?? collect();
    $openValuations = $openValuations ?? collect();
    $recentTasks = $recentTasks ?? collect();
    $recoveryAssignments = $recoveryAssignments ?? collect();
    $affiliatePipeline = $affiliatePipeline ?? collect();
    $listings = $listings ?? collect();
    $taskRows = $openTasks->isNotEmpty() ? $openTasks : $recentTasks;
    $enrollmentApplication = $enrollmentApplication ?? null;
    $profileTabs = $profileTabs ?? ['profile' => 'Profile', 'portal' => 'Portal', 'account' => 'Account'];
    $requestedTab = (string) request('tab', '');
        $startTab = in_array($requestedTab, array_keys($profileTabs), true)
            ? $requestedTab
            : ((session('partner_invite_ready') || session('partner_activation_url')) ? 'portal' : 'profile');
@endphp

<div class="mt-6 space-y-4"
     x-data="{
        tab: @js($startTab),
        setTab(next) {
            this.tab = next;
            const url = new URL(window.location.href);
            url.searchParams.set('tab', next);
            history.replaceState({}, '', url);
        },
     }">
    <div class="flex flex-wrap gap-2">
        @foreach ($profileTabs as $key => $label)
            <button type="button" @click="setTab(@js($key))"
                    :class="tab === @js($key) ? 'bg-brand text-white ring-brand' : 'bg-white text-gray-600 ring-gray-200 hover:bg-brand-muted/40'"
                    class="px-3 py-1.5 rounded-xl text-xs font-semibold ring-1 transition">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div x-show="tab === 'profile'" x-cloak class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-900">Profile</h3>
            <p class="text-xs text-gray-500 mt-1">View only. Use Edit to change name, coverage, rates, or contact details.</p>
            <dl class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">Email</dt>
                    <dd class="mt-1 font-semibold text-gray-900">{{ $record->email ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">Created</dt>
                    <dd class="mt-1 font-semibold text-gray-900">{{ $record->created_at?->format('Y-m-d H:i') ?: '—' }}</dd>
                </div>
                @if ($record->affiliate_code)
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">Affiliate code</dt>
                        <dd class="mt-1 font-semibold text-gray-900 font-mono">{{ $record->affiliate_code }}</dd>
                    </div>
                @endif
                @if ($record->isPremiumAffiliate())
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">Premium affiliate</dt>
                        <dd class="mt-1 font-semibold text-gray-900">Yes</dd>
                    </div>
                @endif
                @if ($record->deposit_markup_percent)
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">Deposit markup %</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ $record->deposit_markup_percent }}</dd>
                    </div>
                @endif
                @if ($record->registration_discount_percent)
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">Registration discount %</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ $record->registration_discount_percent }}</dd>
                    </div>
                @endif
                @if ($record->application_discount_percent)
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">Application discount %</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ $record->application_discount_percent }}</dd>
                    </div>
                @endif
                @if ($record->affiliate_commission_percent)
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">Commission %</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ $record->affiliate_commission_percent }}</dd>
                    </div>
                @endif
                @if ($record->recovery_commission_percent)
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">Recovery commission %</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ $record->recovery_commission_percent }}</dd>
                    </div>
                @endif
                @if ($record->recovery_markup_percent)
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">Recovery markup %</dt>
                        <dd class="mt-1 font-semibold text-gray-900">{{ $record->recovery_markup_percent }}</dd>
                    </div>
                @endif
                <div class="sm:col-span-2">
                    <dt class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">Address</dt>
                    <dd class="mt-1 font-semibold text-gray-900">{{ $record->address ?: '—' }}</dd>
                </div>
            </dl>
        </div>

@if ($enrollmentApplication)
    <a href="{{ route('admin.partner-applications.show', $enrollmentApplication) }}"
       class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-brand-muted/50 ring-1 ring-brand/15 px-5 py-4 hover:ring-brand/30">
        <div>
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Enrollment dossier</p>
            <p class="text-sm font-bold text-gray-900 mt-0.5">What this partner submitted</p>
            <p class="text-xs text-gray-500 mt-1">Profile, coverage, identity, and documents from their application.</p>
        </div>
        <span class="text-sm font-semibold text-brand">Open dossier →</span>
    </a>
@endif

@if ($record->isAffiliate() || $record->hasPartnerRole('affiliate'))
    @include('admin.partners._profile-affiliate-identity', ['record' => $record, 'membership' => $membership ?? null])
@endif
    </div>

    <div x-show="tab === 'performance'" x-cloak class="space-y-6">
@if ($efficiency ?? null)
    @php
        $effStatus = $efficiency['status'] ?? $efficiency['band'];
        $effStyles = [
            'excellent' => 'bg-emerald-50 text-emerald-800 ring-emerald-100',
            'good_standing' => 'bg-emerald-50 text-emerald-800 ring-emerald-100',
            'ramp_up' => 'bg-gray-100 text-gray-700 ring-gray-200',
            'needs_attention' => 'bg-amber-50 text-amber-800 ring-amber-100',
            'at_risk' => 'bg-rose-50 text-rose-800 ring-rose-100',
            'suspended' => 'bg-rose-50 text-rose-800 ring-rose-100',
            'strong' => 'bg-emerald-50 text-emerald-800 ring-emerald-100',
            'watch' => 'bg-amber-50 text-amber-800 ring-amber-100',
            'new' => 'bg-gray-100 text-gray-700 ring-gray-200',
        ];
    @endphp
    <div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-brand/15 p-6">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">Performance — {{ $efficiency['status_label'] ?? $efficiency['band_label'] }}</h3>
                <p class="text-xs text-gray-500 mt-0.5">Calculated from jobs and cases. Internal score still uses Partner Performance Settings.</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 {{ $effStyles[$effStatus] ?? $effStyles['ramp_up'] }}">
                    {{ $efficiency['status_label'] ?? $efficiency['band_label'] }}
                </span>
                <a href="{{ route('admin.partners.efficiency') }}" class="text-sm font-semibold text-brand hover:underline">Board →</a>
            </div>
        </div>
        <div class="grid sm:grid-cols-2 gap-3 mb-4">
            @foreach (($efficiency['kpi_rows'] ?? []) as $kpi)
                <div class="rounded-xl bg-gray-50 px-3 py-2 text-sm">
                    <span class="text-gray-500">{{ $kpi['label'] }}</span>
                    <p class="font-bold">{{ $kpi['actual'] }} / {{ $kpi['target'] }}
                        @if ($kpi['met'] === true) ✓ @elseif ($kpi['met'] === false) — below target @endif
                    </p>
                </div>
            @endforeach
        </div>
        <div class="grid sm:grid-cols-3 lg:grid-cols-6 gap-4 text-sm">
            <div><span class="text-gray-500">Score</span><p class="text-xl font-bold tabular-nums">{{ $efficiency['score'] ?? '—' }}</p></div>
            <div><span class="text-gray-500">Assigned</span><p class="text-xl font-bold tabular-nums">{{ $efficiency['assigned'] }}</p></div>
            <div><span class="text-gray-500">Accepted</span><p class="text-xl font-bold tabular-nums">{{ $efficiency['accepted'] ?? '—' }}</p></div>
            <div><span class="text-gray-500">Open</span><p class="text-xl font-bold tabular-nums">{{ $efficiency['open'] }}</p></div>
            <div><span class="text-gray-500">SLA breaches</span><p class="text-xl font-bold tabular-nums {{ ($efficiency['sla_breaches'] ?? 0) > 0 ? 'text-red-700' : '' }}">{{ $efficiency['sla_breaches'] ?? 0 }}</p></div>
            <div><span class="text-gray-500">Reassignments</span><p class="text-xl font-bold tabular-nums">{{ $efficiency['reassignments'] ?? 0 }}</p></div>
        </div>
        @if (! empty($efficiency['why']))
            <div class="mt-4 rounded-xl bg-brand-muted/40 px-4 py-3">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('partner_governance.why_this_status') }}</p>
                <p class="text-sm text-gray-800 mt-1">{{ $efficiency['why'] }}</p>
            </div>
        @endif
        @if (! empty($efficiency['next_action']))
            <p class="mt-3 text-xs text-gray-600">{{ __('partner_governance.next_system_action') }}: {{ $efficiency['next_action'] }}</p>
        @endif
        @if (($efficiency['consecutive_at_risk'] ?? 0) > 0)
            <p class="mt-3 text-xs text-rose-800">{{ $efficiency['consecutive_at_risk'] }} at-risk review(s) in a row.</p>
        @endif
    </div>
@endif

@if ($recoveryStats ?? null)
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Recovery performance</h3>
        <div class="grid sm:grid-cols-3 lg:grid-cols-6 gap-4 text-sm">
            <div><span class="text-gray-500">Assignments</span><p class="text-xl font-bold">{{ format_number($recoveryStats['assignments']) }}</p></div>
            <div><span class="text-gray-500">Active</span><p class="text-xl font-bold">{{ format_number($recoveryStats['active_cases']) }}</p></div>
            <div><span class="text-gray-500">Completed</span><p class="text-xl font-bold">{{ format_number($recoveryStats['completed_cases']) }}</p></div>
            <div><span class="text-gray-500">SLA breaches</span><p class="text-xl font-bold text-red-700">{{ format_number($recoveryStats['sla_breaches']) }}</p></div>
            <div><span class="text-gray-500">Commission earned</span><p class="text-xl font-bold">{{ format_money($recoveryStats['commission_earned']) }}</p></div>
            <div><span class="text-gray-500">Commission paid</span><p class="text-xl font-bold">{{ format_money($recoveryStats['commission_paid']) }}</p></div>
        </div>
    </div>
@endif

@if ($affiliateStats ?? null)
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Affiliate performance</h3>
        @if ($affiliateStanding ?? null)
            <p class="text-lg font-bold text-gray-900 mb-1">{{ $affiliateStanding['status_label'] }} · {{ number_format((float) $affiliateStanding['score'], 0) }}/100</p>
            <p class="text-xs text-gray-500 mb-3">
                Policy v{{ $affiliateStanding['policy_version'] }}
                · {{ $affiliateStanding['period_start']->format('d M Y') }} – {{ $affiliateStanding['period_end']->format('d M Y') }}
            </p>
            @if ($affiliateEligibility ?? null)
                <p class="text-xs mb-3 {{ $affiliateEligibility['can_operate'] ? 'text-emerald-700' : 'text-amber-800' }}">
                    Operational eligibility: {{ $affiliateEligibility['can_operate'] ? 'Can operate' : implode(', ', $affiliateEligibility['reasons']) }}
                </p>
            @endif
            <div class="grid sm:grid-cols-2 gap-3 mb-4">
                @foreach ($affiliateStanding['kpi_results'] as $kpi)
                    @if ($kpi['enabled'])
                        <div class="rounded-xl bg-gray-50 px-3 py-2 text-sm">
                            <span class="text-gray-500">{{ $kpi['label'] }}</span>
                            @if ($affiliateStanding['premium'] ?? false)
                                <p class="font-bold">{{ number_format($kpi['actual'], $kpi['key'] === 'conversion' ? 1 : 0) }}</p>
                            @else
                                <p class="font-bold">{{ number_format($kpi['actual'], $kpi['key'] === 'conversion' ? 1 : 0) }} / {{ number_format($kpi['target'], 0) }} {{ $kpi['met'] ? '✓' : '— below target' }}</p>
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>
            @if (! empty($affiliateStanding['next_action']))
                <p class="text-sm text-gray-700 mb-4">{{ $affiliateStanding['next_action'] }}</p>
            @endif
        @endif
        <p class="text-xs text-gray-500 mb-3">Volume against the Settings-owned referral target, plus KPI / risk / fraud scores.</p>
        @if ($affiliateVolume ?? null)
            <div class="mb-4 rounded-xl {{ ($affiliateStanding['premium'] ?? false) ? 'bg-brand-muted/40 ring-brand/10' : ($affiliateVolume['missed'] ? 'bg-rose-50 ring-rose-100' : 'bg-brand-muted/40 ring-brand/10') }} ring-1 px-4 py-3">
                <p class="text-xs font-semibold text-gray-800">{{ ($affiliateStanding['premium'] ?? false) ? 'This period' : 'This period vs target' }}</p>
                <p class="text-sm text-gray-700 mt-1">
                    {{ $affiliateVolume['registrations'] }} new users
                    @if ($affiliateStanding['premium'] ?? false)
                        · KPI targets do not apply
                    @else
                        of {{ $affiliateVolume['target'] }}
                        @if ($affiliateVolume['onboarding'])
                            · still in ramp-up
                        @elseif ($affiliateVolume['missed'])
                            · below target · {{ $affiliateVolume['consecutive_misses'] }} missed period(s)
                        @else
                            · on target
                        @endif
                    @endif
                </p>
            </div>
        @endif
        @if ($record->affiliate_evaluation_snapshot)
            @php $snap = $record->affiliate_evaluation_snapshot; @endphp
            <div class="grid sm:grid-cols-3 gap-3 text-sm mb-4">
                <div><span class="text-gray-500">KPI</span><p class="font-bold">{{ number_format((float) ($snap['kpi_score'] ?? 0), 1) }}</p></div>
                <div><span class="text-gray-500">Risk</span><p class="font-bold">{{ number_format((float) ($snap['risk_score'] ?? 0), 1) }}</p></div>
                <div><span class="text-gray-500">Fraud</span><p class="font-bold">{{ number_format((float) ($snap['fraud_score'] ?? 0), 1) }}</p></div>
            </div>
            <p class="text-xs text-gray-500 mb-4">Last evaluated {{ $snap['evaluated_at'] ?? '—' }} · Recommendation: {{ ucfirst($snap['recommendation'] ?? 'none') }}</p>
        @endif
        @php $fraudService = app(\App\Services\AffiliateFraudDetectionService::class); @endphp
        <p class="text-sm text-gray-600 mb-2">
            Risk flag:
            <span class="font-semibold capitalize">{{ $fraudService->label((string) ($record->affiliate_risk_flag ?? 'low')) }}</span>
        </p>
        @if ($record->affiliate_fraud_snapshot)
            @php $fraudSnap = $record->affiliate_fraud_snapshot; @endphp
            <p class="text-xs text-gray-500 mb-3">Last scan {{ $fraudSnap['scanned_at'] ?? '—' }} · Score {{ $fraudSnap['score'] ?? 0 }}</p>
        @endif
        <p class="text-xs text-gray-500">Fraud score and risk flag are produced by the system. They cannot be overridden here.</p>
        @if (($affiliateEvaluations ?? collect())->isNotEmpty())
            <div class="mt-6 overflow-x-auto">
                <h4 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">Evaluation history</h4>
                <table class="min-w-full text-sm">
                    <thead class="text-xs uppercase text-gray-500">
                        <tr>
                            <th class="text-left py-2">Period</th>
                            <th class="text-left py-2">KPI</th>
                            <th class="text-left py-2">Risk</th>
                            <th class="text-left py-2">Fraud</th>
                            <th class="text-left py-2">Recommendation</th>
                            <th class="text-left py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($affiliateEvaluations as $evaluation)
                            <tr>
                                <td class="py-2">{{ $evaluation->period_start?->format('d M') }} – {{ $evaluation->period_end?->format('d M Y') }}</td>
                                <td class="py-2">{{ number_format((float) $evaluation->kpi_score, 1) }}</td>
                                <td class="py-2">{{ number_format((float) $evaluation->risk_score, 1) }}</td>
                                <td class="py-2">{{ number_format((float) $evaluation->fraud_score, 1) }}</td>
                                <td class="py-2 capitalize">{{ $evaluation->recommendation }}</td>
                                <td class="py-2 capitalize">{{ $evaluation->action_taken ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endif

@if (isset($profileTabs['listings']))
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Listing mix</h3>
        @php
            $listingCounts = ($listings ?? collect())->groupBy(fn ($asset) => $asset->availability_status ?? 'available')->map->count();
        @endphp
        <div class="grid sm:grid-cols-3 gap-4 text-sm">
            @forelse ($listingCounts as $status => $count)
                <div><span class="text-gray-500 capitalize">{{ str_replace('_', ' ', $status) }}</span><p class="text-xl font-bold">{{ $count }}</p></div>
            @empty
                <div class="sm:col-span-3">
                    <x-site.empty-state compact icon="🏷️" title="No listings yet" />
                </div>
            @endforelse
        </div>
    </div>
@endif

    </div>

    <div x-show="tab === 'jobs'" x-cloak class="space-y-6">
<div class="bg-white rounded-xl shadow-sm ring-1 {{ $openTasks->isNotEmpty() ? 'ring-amber-200' : 'ring-gray-200' }} p-6">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
        <div>
            <h3 class="text-sm font-semibold {{ $openTasks->isNotEmpty() ? 'text-amber-900' : 'text-gray-700' }}">Jobs</h3>
            <p class="text-xs text-gray-500 mt-0.5">
                {{ $openTasks->count() }} ongoing
                @if ($openValuations->isNotEmpty())
                    · {{ $openValuations->count() }} open valuation{{ $openValuations->count() === 1 ? '' : 's' }}
                @endif
            </p>
        </div>
        <a href="{{ route('admin.partners.tasks', ['partner' => $record->id]) }}"
           class="text-sm font-semibold text-brand hover:underline">All tasks →</a>
    </div>
    @if ($taskRows->isEmpty())
        <x-site.empty-state compact icon="📋" title="No jobs yet" />
    @else
        <ul class="text-sm text-gray-800 divide-y divide-gray-100">
            @foreach ($taskRows as $task)
                <li class="py-2.5 first:pt-0 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                    <span class="font-semibold">{{ ucfirst(str_replace('_', ' ', (string) $task->task_type)) }}</span>
                    <span class="text-xs font-semibold rounded-full px-2 py-0.5 {{ in_array($task->status, ['assigned', 'in_progress'], true) ? 'bg-amber-100 text-amber-900' : 'bg-gray-100 text-gray-600' }}">
                        {{ ucfirst(str_replace('_', ' ', (string) $task->status)) }}
                    </span>
                    @if ($task->loan_application_id)
                        <a href="{{ route('admin.loan-applications.show', $task->loan_application_id) }}" class="text-brand font-semibold hover:underline">Application #{{ $task->loan_application_id }}</a>
                    @endif
                    @if ($task->customer_name)
                        <span class="text-gray-500">{{ $task->customer_name }}</span>
                    @endif
                    @if ($task->due_at)
                        <span class="text-xs text-gray-500">Due {{ $task->due_at->format('d M Y') }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
    @if ($openTasks->isNotEmpty())
        <form method="POST" action="{{ route('admin.partners.halt-open-work', $record) }}"
              class="mt-4" x-data
              @submit.prevent="window.confirmForm($el, {
                  title: @js('Halt open tasks?'),
                  message: @js('Open jobs will be cancelled. If another valuer covers the region, the work is reassigned. Completed reports are not deleted.'),
                  confirmLabel: @js('Halt open tasks'),
                  confirmClass: 'bg-amber-500 hover:bg-amber-600 text-white',
                  tone: 'warning',
              })">
            @csrf
            <button type="submit"
                    class="inline-flex items-center gap-2 text-sm font-semibold text-amber-950 bg-amber-200 hover:bg-amber-300 ring-1 ring-amber-400 px-4 py-2 rounded-xl">
                Halt open tasks
            </button>
        </form>
    @endif
</div>
    </div>

    <div x-show="tab === 'cases'" x-cloak class="space-y-6">
@php $openCaseCount = $recoveryAssignments->filter(fn ($assignment) => $assignment->isOpen())->count(); @endphp
<div class="bg-white rounded-xl shadow-sm ring-1 {{ $openCaseCount > 0 ? 'ring-amber-200' : 'ring-gray-200' }} p-6">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
        <div>
            <h3 class="text-sm font-semibold {{ $openCaseCount > 0 ? 'text-amber-900' : 'text-gray-700' }}">Cases</h3>
            <p class="text-xs text-gray-500 mt-0.5">{{ $openCaseCount }} open · {{ $recoveryAssignments->count() }} listed</p>
        </div>
        <a href="{{ route('admin.recovery.assignments.index') }}" class="text-sm font-semibold text-brand hover:underline">All cases →</a>
    </div>
    @if ($recoveryAssignments->isEmpty())
        <x-site.empty-state compact icon="🛡️" title="No cases yet" />
    @else
        <ul class="text-sm text-gray-800 divide-y divide-gray-100">
            @foreach ($recoveryAssignments as $assignment)
                @php
                    $borrower = $assignment->arrearCase?->loan?->customer;
                    $borrowerName = trim((string) ($borrower?->full_name ?? ''));
                @endphp
                <li class="py-2.5 first:pt-0 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                    <a href="{{ route('admin.recovery.assignments.show', $assignment) }}" class="font-semibold text-brand hover:underline">
                        Case #{{ $assignment->id }}
                    </a>
                    <span class="text-xs font-semibold rounded-full px-2 py-0.5 {{ $assignment->isOpen() ? 'bg-amber-100 text-amber-900' : 'bg-gray-100 text-gray-600' }}">
                        {{ ucfirst(str_replace('_', ' ', (string) $assignment->status)) }}
                    </span>
                    @if ($assignment->partner_type)
                        <span class="text-gray-500">{{ display_label($assignment->partner_type, 'recovery_partner_type') }}</span>
                    @endif
                    @if ($borrowerName !== '')
                        <span class="text-gray-500">{{ $borrowerName }}</span>
                    @endif
                    @if ($assignment->sla_due_at)
                        <span class="text-xs {{ $assignment->slaBreached() ? 'text-red-700 font-semibold' : 'text-gray-500' }}">SLA {{ $assignment->sla_due_at->format('d M Y') }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
    </div>

    <div x-show="tab === 'pipeline'" x-cloak class="space-y-6">
<div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
    <h3 class="text-sm font-semibold text-gray-700 mb-1">Business</h3>
    <p class="text-xs text-gray-500 mb-4">Clicks, registrations, and loan applications this affiliate brought in. Commission is on Payouts.</p>
    @if ($affiliateStats ?? null)
        <div class="grid sm:grid-cols-3 gap-4 text-sm mb-4">
            <div><span class="text-gray-500">Clicks</span><p class="text-xl font-bold">{{ format_number($affiliateStats['clicks']) }}</p></div>
            <div><span class="text-gray-500">Registrations</span><p class="text-xl font-bold">{{ format_number($affiliateStats['registrations']) }}</p></div>
            <div><span class="text-gray-500">Applications</span><p class="text-xl font-bold">{{ format_number($affiliateStats['applications']) }}</p></div>
        </div>
    @endif
    @if ($record->affiliate_code)
        <p class="text-xs text-gray-500 mb-4">Link: {{ app(\App\Services\AffiliateService::class)->affiliateLink($record) }}</p>
    @endif
    @if ($affiliatePipeline->isEmpty())
        <x-site.empty-state compact icon="📈" title="No pipeline yet" />
    @else
        <ul class="text-sm text-gray-800 divide-y divide-gray-100">
            @foreach ($affiliatePipeline as $event)
                <li class="py-2.5 first:pt-0 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                    <span class="font-semibold capitalize">{{ str_replace('_', ' ', (string) $event->event_type) }}</span>
                    @if ($event->customer)
                        <span class="text-gray-500">{{ $event->customer->full_name ?? $event->customer->name ?? 'Customer' }}</span>
                    @endif
                    @if ($event->loan_application_id)
                        <a href="{{ route('admin.loan-applications.show', $event->loan_application_id) }}" class="text-brand font-semibold hover:underline">Application #{{ $event->loan_application_id }}</a>
                    @endif
                    <span class="text-xs text-gray-400">{{ $event->created_at?->format('d M Y') }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
    </div>

    <div x-show="tab === 'membership'" x-cloak class="space-y-6">
        @if ($membership ?? $fieldMembership ?? null)
            @php $mem = $membership ?? $fieldMembership; @endphp
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Membership</h3>
                <div class="grid sm:grid-cols-3 gap-4 text-sm">
                    <div><span class="text-gray-500">Status</span><p class="text-lg font-bold">{{ $mem['label'] }}</p></div>
                    <div><span class="text-gray-500">Fee</span><p class="text-lg font-bold">{{ format_money($mem['fee']) }}</p></div>
                    <div><span class="text-gray-500">Expires</span><p class="text-lg font-bold">{{ $mem['expires_at']?->format('d M Y') ?? '—' }}</p></div>
                </div>
            </div>
        @endif
    </div>

    <div x-show="tab === 'agreements'" x-cloak class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Agreements</h3>
            @if ($affiliateAgreement ?? null)
                <p class="text-sm text-gray-700">Accepted {{ $affiliateAgreement->accepted_at?->format('d M Y H:i') }} · agreement v{{ $affiliateAgreement->agreement_version }} · policy v{{ $affiliateAgreement->policy_version }} · {{ $affiliateAgreement->locale }}</p>
                <pre class="mt-4 text-xs whitespace-pre-wrap bg-gray-50 rounded-xl p-4 ring-1 ring-gray-100 max-h-96 overflow-y-auto">{{ $affiliateAgreement->rendered_text }}</pre>
            @elseif (($partnerAgreements ?? collect())->isNotEmpty())
                @foreach ($partnerAgreements as $agreement)
                    <div class="mb-6 last:mb-0">
                        <p class="text-sm text-gray-700">
                            {{ $loop->first ? 'Current' : 'Superseded' }}
                            · accepted {{ $agreement->accepted_at?->format('d M Y H:i') }}
                            · agreement v{{ $agreement->agreement_version }}
                            · policy v{{ $agreement->policy_version }}
                            · {{ $agreement->locale }}
                        </p>
                        <pre class="mt-3 text-xs whitespace-pre-wrap bg-gray-50 rounded-xl p-4 ring-1 ring-gray-100 max-h-72 overflow-y-auto">{{ $agreement->rendered_text }}</pre>
                    </div>
                @endforeach
            @else
                <x-site.empty-state compact icon="📄" title="No Terms accepted yet" />
            @endif
        </div>
    </div>

    <div x-show="tab === 'documents'" x-cloak class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Documents</h3>
            @forelse ($partnerDocuments ?? [] as $doc)
                <div class="flex flex-wrap items-center justify-between gap-2 py-2 border-b border-gray-100 last:border-0 text-sm">
                    <div>
                        <p class="font-semibold text-gray-900">{{ $doc['label'] }}</p>
                        <p class="text-xs text-gray-500">{{ $doc['source'] }}</p>
                    </div>
                    @if ($doc['url'])
                        <a href="{{ $doc['url'] }}" class="text-sm font-semibold text-brand hover:underline" target="_blank" rel="noopener">View</a>
                    @endif
                </div>
            @empty
                <x-site.empty-state compact icon="📁" title="No documents on file" />
            @endforelse
        </div>
    </div>

    <div x-show="tab === 'history'" x-cloak class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">History</h3>
            @forelse ($partnerHistory ?? [] as $event)
                <div class="py-2 border-b border-gray-100 last:border-0 text-sm">
                    <p class="font-semibold text-gray-900">{{ $event['label'] }}</p>
                    <p class="text-xs text-gray-500">{{ $event['at']?->format('d M Y H:i') }} @if ($event['detail']) · {{ $event['detail'] }} @endif</p>
                </div>
            @empty
                <x-site.empty-state compact icon="🕒" title="No history events yet" />
            @endforelse
        </div>
    </div>

    <div x-show="tab === 'compliance'" x-cloak class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Compliance</h3>
            <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                <div><dt class="text-gray-500">Account</dt><dd class="font-semibold">{{ ucfirst($record->status ?? '') }}</dd></div>
                <div><dt class="text-gray-500">Suspend kind</dt><dd class="font-semibold">{{ $record->suspend_kind ?: '—' }}</dd></div>
                <div><dt class="text-gray-500">Performance</dt><dd class="font-semibold">{{ $efficiency['status_label'] ?? ($record->performance_status ?: '—') }}</dd></div>
                <div>
                    <dt class="text-gray-500">Can receive work</dt>
                    <dd class="font-semibold">{{ ($jobEligibility['can_receive'] ?? false) ? 'Yes' : ('No — '.($jobEligibility['reason_label'] ?? '')) }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <div x-show="tab === 'listings'" x-cloak class="space-y-6">
<div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
        <div>
            <h3 class="text-sm font-semibold text-gray-700">Listings</h3>
            <p class="text-xs text-gray-500 mt-0.5">Marketplace assets from this supplier.</p>
        </div>
        <a href="{{ route('admin.marketplace-assets.index') }}" class="text-sm font-semibold text-brand hover:underline">Marketplace →</a>
    </div>
    @if ($listings->isEmpty())
        <x-site.empty-state compact icon="🏷️" title="No listings yet" />
    @else
        <ul class="text-sm text-gray-800 divide-y divide-gray-100">
            @foreach ($listings as $asset)
                <li class="py-2.5 first:pt-0 flex flex-wrap items-baseline justify-between gap-2">
                    <a href="{{ route('admin.marketplace-assets.show', $asset) }}" class="font-semibold text-brand hover:underline">{{ $asset->title }}</a>
                    <span class="text-xs font-semibold rounded-full px-2 py-0.5 bg-gray-100 text-gray-600 capitalize">{{ str_replace('_', ' ', (string) ($asset->availability_status ?? 'available')) }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
    </div>

    <div x-show="tab === 'capital'" x-cloak class="space-y-6">
<div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
    <h3 class="text-sm font-semibold text-gray-700 mb-1">Capital</h3>
    <p class="text-xs text-gray-500 mb-4">Snapshot of funds this partner has placed. Adjust capital, allocations, and withdrawals on the capital book.</p>
    @if ($linkedLender && ($capitalMetrics ?? null))
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm mb-4">
            <div><span class="text-gray-500">Invested</span><p class="text-xl font-bold">{{ format_money($capitalMetrics['capital_invested']) }}</p></div>
            <div><span class="text-gray-500">Deployed</span><p class="text-xl font-bold">{{ format_money($capitalMetrics['capital_utilized']) }}</p></div>
            <div><span class="text-gray-500">Available</span><p class="text-xl font-bold">{{ format_money($capitalMetrics['capital_available']) }}</p></div>
            <div><span class="text-gray-500">Active loans</span><p class="text-xl font-bold">{{ format_number($capitalMetrics['active_loans']) }}</p></div>
        </div>
        <a href="{{ route('admin.lenders.show', $linkedLender) }}" class="inline-flex text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2 rounded-xl">Open capital book →</a>
    @else
        <x-site.empty-state
            compact
            icon="🏦"
            title="No capital book yet"
            action-label="Capital partners"
            :action-url="route('admin.lenders.index')"
        />
    @endif
</div>
    </div>

    <div x-show="tab === 'payouts'" x-cloak class="space-y-6">
@php $payouts = $payouts ?? collect(); @endphp
@if (auth()->user()?->hasPermission('finance.operations'))
<div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
        <div>
            <h3 class="text-sm font-semibold text-gray-700">Payouts</h3>
            <p class="text-xs text-gray-500 mt-0.5">Partner wallet lines from completed jobs. PAID means cash has left the bank and the journal is posted.</p>
        </div>
        <a href="{{ route('admin.payments.ledger', ['direction' => 'out', 'tab' => 'partners']) }}"
           class="text-sm font-semibold text-brand hover:underline">Money ledger →</a>
    </div>
    @if ($payouts->isEmpty())
        <x-site.empty-state compact icon="💸" title="No payouts yet" />
    @else
        <ul class="text-sm divide-y divide-gray-100">
            @foreach ($payouts as $payout)
                <li class="py-2.5 first:pt-0 flex flex-wrap items-baseline justify-between gap-2">
                    <div class="min-w-0">
                        <a href="{{ route('admin.partner-payments.show', $payout) }}" class="font-semibold text-brand hover:underline">
                            {{ $payout->invoice_number }}
                        </a>
                        <span class="text-gray-500"> · {{ $payout->description ?: str_replace('_', ' ', (string) $payout->source_type) }}</span>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="tabular-nums font-semibold">{{ format_money((float) $payout->amount) }}</span>
                        <span class="text-xs font-semibold rounded-full px-2 py-0.5 {{ match ($payout->status) {
                            'paid' => 'bg-emerald-100 text-emerald-800',
                            'approved' => 'bg-sky-100 text-sky-800',
                            'pending' => 'bg-amber-100 text-amber-900',
                            'cancelled' => 'bg-gray-100 text-gray-600',
                            default => 'bg-gray-100 text-gray-600',
                        } }}">{{ strtoupper((string) $payout->status) }}</span>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endif
    </div>

@php
    $activationService = app(\App\Services\PartnerActivationService::class);
    $portalReady = (bool) ($record->activated_at && $record->user_id);
    $inviteUrl = $activationService->publicActivateUrl($record);
    $inviteText = $activationService->shareMessage($record);
    $inviteEncoded = rawurlencode($inviteText);
    $valuerCover = $record->isValuer()
        ? app(\App\Services\PartnerRegionCoverage::class)->label($record)
        : null;
@endphp

@if ($record->isValuer())
    <div x-show="tab === 'profile'" x-cloak class="rounded-xl px-5 py-4 text-sm ring-1 {{ $portalReady && $valuerCover !== 'No regions set' ? 'bg-brand-muted/40 ring-brand/10 text-brand' : 'bg-amber-50 ring-amber-200 text-amber-950' }}">
        @if (! $portalReady)
            Waiting valuation files match after this valuer is active and covers Nationwide or the borrower region. Leftover files: Assign valuer on the credit file.
        @elseif ($valuerCover === 'No regions set')
            This valuer has no region coverage yet. Set Nationwide or the borrower region so waiting files can match.
        @else
            Coverage is {{ $valuerCover }}. Waiting files that match auto-assign. If a credit file is still waiting, open Collateral → Assign valuer.
        @endif
    </div>
@endif

    <div x-show="tab === 'portal'" x-cloak class="space-y-6">
@if (! $portalReady)
<div class="bg-white rounded-xl shadow-sm ring-1 {{ session('partner_invite_ready') ? 'ring-brand' : 'ring-gray-200' }} p-6"
     x-data="{ copied: false }">
    <h3 class="text-sm font-semibold text-gray-900">Share activation</h3>
    <p class="text-xs text-gray-500 mt-1">
        Send the partner code and link. They open it, confirm this phone, then create a 4-digit PIN. No need to type the code by hand if they use the link.
    </p>
    <p class="mt-4 text-[10px] uppercase tracking-widest text-brand font-semibold">Partner code</p>
    <p class="mt-1 text-2xl font-extrabold tracking-widest font-mono text-brand">{{ $record->vendor_number }}</p>
    <p class="mt-2 text-sm text-gray-600">Registered phone: <span class="font-medium text-gray-900">{{ format_phone($record->phone) }}</span></p>
    <p class="mt-3 text-xs text-gray-500 break-all">{{ $inviteUrl }}</p>
    <div class="mt-4 flex flex-wrap gap-2">
        <button type="button"
                @click="navigator.clipboard.writeText(@js($inviteText)).then(() => { copied = true; setTimeout(() => copied = false, 2000) }).catch(() => window.prompt('Copy this message', @js($inviteText)))"
                class="inline-flex text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2 rounded-xl">
            Copy message
        </button>
        <a href="https://wa.me/?text={{ $inviteEncoded }}" target="_blank" rel="noopener"
           class="inline-flex text-sm font-semibold text-white bg-brand hover:bg-brand-light px-4 py-2 rounded-xl">
            WhatsApp
        </a>
        <a href="sms:?body={{ $inviteEncoded }}"
           class="inline-flex text-sm font-semibold text-slate-800 bg-white ring-1 ring-slate-200 hover:bg-slate-50 px-4 py-2 rounded-xl">
            SMS
        </a>
    </div>
    <p x-show="copied" x-cloak class="mt-2 text-xs font-medium text-emerald-700">Message copied. Paste it in WhatsApp or SMS.</p>
</div>
@endif
<div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
    <h3 class="text-sm font-semibold text-gray-700 mb-1">Portal PIN</h3>
    <p class="text-xs text-gray-500 mb-4">
        Partners sign in with phone and a 4-digit PIN. Set a new PIN here, or re-issue activation so they create it themselves.
    </p>
    @if (session('partner_activation_url'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-950 break-all">
            Activation link (valid 14 days): {{ session('partner_activation_url') }}
        </div>
    @endif
    <div class="grid sm:grid-cols-2 gap-4">
        <form method="POST" action="{{ route('admin.partners.reset-pin', $record) }}" class="space-y-2">
            @csrf
            <label class="block text-xs font-semibold uppercase tracking-widest text-gray-500">Set new PIN</label>
            <input name="pin" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" required
                   class="w-full rounded-xl border-gray-300 text-sm" placeholder="4 digits" autocomplete="off">
            <button type="submit" class="inline-flex text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2 rounded-xl">
                Save PIN
            </button>
        </form>
        <form method="POST" action="{{ route('admin.partners.reissue-activation', $record) }}" class="space-y-2">
            @csrf
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-500">Re-activation</p>
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="notify_partner" value="1" class="rounded border-gray-300 text-brand">
                Also SMS / email the link
            </label>
            <button type="submit" class="inline-flex text-sm font-semibold text-slate-800 bg-white ring-1 ring-slate-200 hover:bg-slate-50 px-4 py-2 rounded-xl">
                Re-issue activation link
            </button>
        </form>
    </div>
</div>
    </div>

    <div x-show="tab === 'account'" x-cloak>
<div class="bg-white rounded-xl shadow-sm ring-1 ring-red-200/80 p-6">
    <h3 class="text-sm font-semibold text-red-700 mb-1">Danger zone</h3>
    <p class="text-xs text-gray-500 mb-3">
        Create the replacement partner first. Halt open work, then deactivate. Delete is only for partners with no history.
    </p>
    <div class="flex flex-wrap items-center gap-3">
        <form method="POST" action="{{ route('admin.partners.destroy', $record) }}"
              id="partner-delete-form-{{ $record->id }}"
              x-data
              @submit.prevent="window.confirmForm($el, {
                  title: @js('Delete this partner?'),
                  message: @js('This permanently deletes the partner. Open or completed jobs cannot be deleted with them — halt open tasks, then Deactivate.'),
                  confirmLabel: @js('Delete'),
                  confirmClass: 'bg-red-600 hover:bg-red-700 text-white',
                  tone: 'warning',
              })">
            @csrf
            @method('DELETE')
            <button type="submit"
                    class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 px-4 py-2 rounded-xl shadow-sm transition">
                Delete
            </button>
        </form>
        <form method="POST" action="{{ route('admin.partners.deactivate', $record) }}"
              x-data
              @submit.prevent="window.confirmForm($el, {
                  title: @js('Deactivate this partner?'),
                  message: @js('Open jobs are halted and offered to another partner. This partner is suspended, login is disabled, and history is kept.'),
                  confirmLabel: @js('Deactivate'),
                  confirmClass: 'bg-amber-500 hover:bg-amber-600 text-white',
                  tone: 'warning',
              })">
            @csrf
            <button type="submit"
                    class="inline-flex items-center gap-2 text-sm font-semibold text-amber-900 bg-amber-100 hover:bg-amber-200 ring-1 ring-amber-300 px-4 py-2 rounded-xl shadow-sm transition">
                Deactivate
            </button>
        </form>
    </div>
</div>
    </div>
</div>
</x-admin.show-page>
