@php
    $deskPerson = request('review_person', request('person', 'borrower'));
    if (! in_array($deskPerson, ['borrower', 'guarantor', 'member'], true)) {
        $deskPerson = 'borrower';
    }
    $deskG = $deskPerson === 'guarantor' ? (int) request('review_g', request('g', 0)) : null;
    $deskM = $deskPerson === 'member' ? (int) request('review_m', request('m', 0)) : null;
    if ($deskPerson === 'guarantor' && (! $deskG || $deskG < 1)) {
        $firstG = collect($review['guarantors'] ?? [])->first();
        $deskG = (int) ($firstG['link_id'] ?? 0) ?: null;
        if (! $deskG) {
            $deskPerson = 'borrower';
        }
    }
    if ($deskPerson === 'member' && (! $deskM || $deskM < 1)) {
        $groupMembers = collect(is_array($groupReview ?? null) ? ($groupReview['members'] ?? []) : []);
        $firstM = $groupMembers
            ->first(fn ($row) => ($row['role'] ?? '') !== 'leader')
            ?? $groupMembers->first();
        $deskM = (int) ($firstM['id'] ?? 0) ?: null;
        if (! $deskM) {
            $deskPerson = 'borrower';
        }
    }

    $desk = app(\App\Services\ScreeningChecklistService::class)->deskViewModel(
        $record,
        $review,
        $groupReview ?? null,
        auth()->user(),
        $deskPerson,
        $deskG ?: null,
        $deskM ?: null,
    );
    $canEdit = (bool) ($desk['can_edit'] ?? false);
    $subjectUrl = function (array $s) use ($record) {
        return route('admin.loan-applications.show', array_filter([
            'loan_application' => $record,
            'workspace' => 'checklist',
            'review_person' => $s['person'],
            'review_g' => $s['g'],
            'review_m' => $s['m'],
        ])).'#review-desk';
    };

    $sequence = app(\App\Services\ScreeningSequenceService::class)->snapshot($record);
    $gates = app(\App\Services\ScreeningChecklistGateService::class)->regroup($desk['groups'] ?? [], $record);
    $sequence = app(\App\Services\ScreeningSequenceService::class)->snapshot($record, $gates);
    $gateKeys = array_keys($gates);

    $identitySubjectCustomer = $review['customer'] ?? $record->customer;
    if ($deskPerson === 'member' && $deskM) {
        $memberRow = collect($groupReview['members'] ?? [])
            ->first(fn ($row) => (int) ($row['id'] ?? 0) === (int) $deskM);
        $memberCustomerId = is_array($memberRow) ? (int) ($memberRow['customer_id'] ?? 0) : 0;
        $identitySubjectCustomer = $memberCustomerId > 0
            ? (\App\Models\Customer::query()->find($memberCustomerId) ?? $identitySubjectCustomer)
            : $identitySubjectCustomer;
        $identitySubjectCustomer = $identitySubjectCustomer instanceof \App\Models\Customer
            ? $identitySubjectCustomer
            : ($review['customer'] ?? $record->customer);
    }
    $identityCard = app(\App\Services\ScreeningChecklistService::class)->identityPeopleCard(
        $desk,
        $identitySubjectCustomer instanceof \App\Models\Customer ? $identitySubjectCustomer : ($review['customer'] ?? $record->customer),
    );

    $firstOpenGate = collect($gates)->first(fn ($g) => empty($g['locked']) && ! ($g['complete'] ?? false));
    $defaultGate = is_array($firstOpenGate) ? ($firstOpenGate['key'] ?? 'income') : ($firstOpenGate ?: ($gateKeys[0] ?? 'income'));

    $requestGate = (string) request('gate', '');
    $requestPhase = (string) request('desk_phase', '');
    $defaultCapacityTab = (string) request('capacity_tab', 'checks');
    if (! in_array($defaultCapacityTab, ['checks', 'documents', 'affordability', 'crb'], true)) {
        $defaultCapacityTab = 'checks';
    }
    $defaultSecurityTab = (string) request('security_tab', 'checks');
    if (! in_array($defaultSecurityTab, ['checks', 'group', 'wrapup'], true)) {
        $defaultSecurityTab = 'checks';
    }
    $isGroupFile = collect($groupReview['members'] ?? [])->isNotEmpty();
    if ($defaultSecurityTab === 'group' && ! $isGroupFile) {
        $defaultSecurityTab = 'checks';
    }

    if ($requestGate !== '' && isset($gates[$requestGate])) {
        $defaultGate = $requestGate;
    } elseif ($defaultCapacityTab === 'crb' || $requestPhase === 'capacity' && $defaultCapacityTab === 'crb') {
        $defaultGate = isset($gates['crb']) ? 'crb' : $defaultGate;
    } elseif ($defaultCapacityTab === 'documents') {
        $defaultGate = isset($gates['final']) ? 'final' : $defaultGate;
    } elseif ($defaultCapacityTab === 'affordability') {
        $defaultGate = isset($gates['income']) ? 'income' : $defaultGate;
    } elseif ($requestPhase === 'person') {
        $defaultGate = isset($gates['identity']) ? 'identity' : $defaultGate;
    } elseif ($requestPhase === 'capacity') {
        $defaultGate = isset($gates['income']) ? 'income' : $defaultGate;
    } elseif ($requestPhase === 'security' && $defaultSecurityTab === 'wrapup') {
        $defaultGate = isset($gates['final']) ? 'final' : $defaultGate;
    } elseif ($requestPhase === 'security') {
        $defaultGate = isset($gates['collateral']) ? 'collateral' : $defaultGate;
    }
    if (! isset($gates[$defaultGate])) {
        $defaultGate = $gateKeys[0] ?? 'identity';
    }

    $firstOpenByGate = [];
    foreach ($gates as $gKey => $gateRow) {
        $open = collect($gateRow['groups'] ?? [])->first(fn ($g) => ! ($g['complete'] ?? false))
            ?? collect($gateRow['groups'] ?? [])->first();
        $firstOpenByGate[$gKey] = $open['key'] ?? null;
    }

    $firstOpenGroup = collect($gates[$defaultGate]['groups'] ?? [])->first(fn ($g) => ! ($g['complete'] ?? false))
        ?? collect($gates[$defaultGate]['groups'] ?? [])->first();
    $defaultOpenGroup = (string) ($firstOpenGroup['key'] ?? '');
    $requestOpenGroup = (string) request('open_group', '');
    if ($requestOpenGroup !== '') {
        $defaultOpenGroup = $requestOpenGroup;
    }
    $requestOpenItem = (string) request('open_item', '');
    $readiness = $screeningReadiness ?? null;
    $continueDecisionUrl = route('admin.loan-applications.show', [
        'loan_application' => $record,
        'workspace' => 'decision',
    ]).'#review-recommendation';
@endphp

<section id="review-desk" class="rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm overflow-hidden scroll-mt-24"
         x-data="{
             gate: @js($defaultGate),
             openGroup: @js($defaultOpenGroup !== '' ? $defaultOpenGroup : null),
             openItem: @js($requestOpenItem !== '' ? $requestOpenItem : null),
             firstOpenByGate: @js($firstOpenByGate),
             setGate(key) {
                 this.gate = key;
                 this.openItem = null;
                 this.openGroup = this.firstOpenByGate[key] || null;
             },
             toggleGroup(key) {
                 this.openGroup = this.openGroup === key ? null : key;
                 this.openItem = null;
             },
             toggleItem(key) {
                 this.openItem = this.openItem === key ? null : key;
             },
             passRemaining(groupKey) {
                 this.openGroup = groupKey;
                 this.openItem = null;
                 this.$refs['items_' + groupKey]?.querySelectorAll('[data-checklist-item]').forEach((el) => {
                     const data = Alpine.$data(el);
                     if (! data || data.verdict || data.needsStatementTotals || data.systemLocked) {
                         return;
                     }
                     data.verdict = 'pass';
                 });
             }
         }">
    @include('admin.loan-applications.review._early_eligibility', ['sequence' => $sequence, 'record' => $record])

    <div class="px-5 py-3 border-b border-gray-100 bg-gradient-to-r from-brand-muted/50 to-white flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
            <h3 class="text-base font-bold text-gray-900">Review checklist</h3>
            <span id="screening-desk-counts" class="text-sm font-bold text-brand tabular-nums">{{ collect($gates)->sum('decided') }}/{{ collect($gates)->sum('total') }}</span>
            @if (($desk['failed'] ?? 0) > 0)
                <span id="screening-desk-failed" class="text-[11px] font-bold text-amber-800">{{ $desk['failed'] }} concern</span>
            @endif
        </div>
        @if ($canEdit)
            <div class="flex items-center gap-2 shrink-0">
                <span id="screening-checklist-save-status" class="text-[11px] font-semibold" hidden></span>
                <button type="submit" form="screening-checklist-form" data-screening-save data-loading-label="Saving…"
                        class="inline-flex items-center justify-center rounded-xl bg-brand text-white text-xs font-bold px-3.5 py-2 hover:bg-brand-light">
                    Save
                </button>
            </div>
        @endif
    </div>

    <div class="px-5 pt-4 flex flex-wrap gap-2 border-b border-gray-100 pb-3">
        @foreach ($gates as $gate)
            @php
                $gKey = $gate['key'];
                $gLabel = $gate['label'];
            @endphp
            <button type="button"
                    data-gate-key="{{ $gKey }}"
                    @click="{{ ! empty($gate['locked']) ? '' : 'setGate('.e(json_encode($gKey)).')' }}"
                    @if (! empty($gate['locked'])) disabled @endif
                    :class="gate === @js($gKey)
                        ? 'bg-brand text-white ring-brand shadow-sm'
                        : 'bg-white text-gray-800 ring-gray-200 hover:bg-brand-muted/40'"
                    class="shrink-0 rounded-xl px-3.5 py-2.5 text-left ring-1 transition min-w-[9rem] disabled:opacity-60 disabled:cursor-not-allowed">
                <span class="sr-only">{{ $gLabel }}</span>
                <span class="block text-xs font-bold" data-gate-chip>{{ $gate['chip'] ?? $gLabel }}</span>
                <span class="block text-[11px] mt-0.5 tabular-nums" data-gate-status
                      :class="gate === @js($gKey) ? 'text-white/80' : 'text-gray-500'">
                    @if (($gate['status_label'] ?? '') === 'Complete')
                        Complete
                    @elseif (($gate['failed'] ?? 0) > 0)
                        Attention
                    @else
                        {{ $gate['decided'] }}/{{ $gate['total'] }}
                    @endif
                </span>
            </button>
        @endforeach
    </div>

    <div class="p-5 space-y-4">
        @if ($canEdit)
            <form id="screening-checklist-form" method="POST" action="{{ route('admin.loan-applications.screening-checklist', $record) }}" class="space-y-4" data-skip-loading="1" data-no-draft>
                @csrf
                <input type="hidden" name="person" value="{{ $deskPerson }}">
                <input type="hidden" name="gate" :value="gate">
                <input type="hidden" name="open_group" :value="openGroup || ''">
                <input type="hidden" name="open_item" :value="openItem || ''">
                @if ($deskG)
                    <input type="hidden" name="g" value="{{ $deskG }}">
                @endif
                @if ($deskM)
                    <input type="hidden" name="m" value="{{ $deskM }}">
                @endif

                @foreach ($gates as $gate)
                    <div x-show="gate === @js($gate['key'])" x-cloak class="space-y-3">
                        @if (! empty($gate['locked']))
                            <div class="rounded-2xl bg-slate-50 ring-1 ring-slate-200 px-4 py-4">
                                <p class="text-sm font-bold text-slate-900">{{ $gate['chip'] ?? $gate['label'] }}</p>
                                <p class="text-sm text-slate-600 mt-1">{{ $gate['lock_detail'] ?? 'Complete Income & Statement Review to continue screening.' }}</p>
                            </div>
                        @else
                            @if ($gate['key'] === 'identity')
                                @if (count($desk['subjects'] ?? []) > 1)
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($desk['subjects'] as $subject)
                                            @php
                                                $identityHref = route('admin.loan-applications.show', array_filter([
                                                    'loan_application' => $record,
                                                    'workspace' => 'checklist',
                                                    'review_person' => $subject['person'],
                                                    'review_g' => $subject['g'],
                                                    'review_m' => $subject['m'],
                                                    'gate' => 'identity',
                                                ])).'#review-desk';
                                            @endphp
                                            <a href="{{ $identityHref }}"
                                               class="inline-flex rounded-xl px-3 py-1.5 text-[11px] font-bold ring-1 {{ ($subject['person'] === $deskPerson && (int) ($subject['m'] ?? 0) === (int) $deskM && (int) ($subject['g'] ?? 0) === (int) $deskG) ? 'bg-brand text-white ring-brand' : 'bg-white text-slate-800 ring-slate-200' }}">
                                                {{ $subject['label'] }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                                @include('admin.loan-applications.review._identity_people_card', ['identityCard' => $identityCard, 'record' => $record])
                            @endif
                            @include('admin.loan-applications.review._checklist_groups', [
                                'groups' => $gate['groups'],
                                'canEdit' => true,
                                'guidedIncome' => $gate['key'] === 'income',
                            ])
                            @if ($gate['key'] === 'income')
                                @include('admin.loan-applications.review._subject_affordability_gate')
                            @endif
                            @if ($gate['key'] === 'final')
                                @php
                                    $finalUnresolved = (int) ($gate['human_open'] ?? 0) + (int) ($gate['failed'] ?? 0);
                                    $outcomeTicks = [
                                        'income' => 'Verified income',
                                        'crb' => 'CRB',
                                        'identity' => 'Identity, people & contacts',
                                        'collateral' => 'Collateral & security',
                                    ];
                                @endphp
                                <div class="rounded-2xl ring-1 ring-brand/15 bg-white px-4 py-3.5 space-y-2">
                                    <p class="text-sm font-bold text-slate-900">Screening outcome</p>
                                    <p class="text-[12px] font-semibold text-emerald-800">✓ Initial affordability</p>
                                    @foreach ($outcomeTicks as $ok => $label)
                                        @php $og = $gates[$ok] ?? []; @endphp
                                        <p class="text-[12px] font-semibold {{ ! empty($og['complete']) && empty($og['failed']) ? 'text-emerald-800' : 'text-amber-800' }}">
                                            {{ ! empty($og['complete']) && empty($og['failed']) ? '✓' : '○' }} {{ $label }}
                                        </p>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    </div>
                @endforeach
            </form>
        @else
            @foreach ($gates as $gate)
                <div x-show="gate === @js($gate['key'])" x-cloak class="space-y-3">
                    @include('admin.loan-applications.review._checklist_groups_readonly', ['groups' => $gate['groups']])
                </div>
            @endforeach
        @endif

        <div x-show="gate === 'crb'" x-cloak>
            @include('admin.loan-applications.review._checklist_phase_panels', [
                'phase' => 'capacity',
                'section' => 'crb',
                'deskPerson' => $deskPerson,
                'deskG' => $deskG,
                'deskM' => $deskM,
            ])
        </div>
        <div x-show="gate === 'final'" x-cloak class="space-y-4">
            @include('admin.loan-applications.review._checklist_phase_panels', [
                'phase' => 'capacity',
                'section' => 'documents',
                'deskPerson' => $deskPerson,
                'deskG' => $deskG,
                'deskM' => $deskM,
            ])
            @php
                $finalReady = is_array($readiness) && ($readiness['ready'] ?? false);
                $finalBlocks = is_array($readiness) ? ($readiness['blocking_items'] ?? []) : [];
                $finalBlock = $finalBlocks[0] ?? null;
                $groupMembers = collect($groupReview['members'] ?? []);
            @endphp
            @if ($isGroupFile)
                <div class="rounded-2xl ring-1 ring-brand/15 bg-white px-4 py-3 space-y-1.5">
                    <p class="text-sm font-bold text-gray-900">Group summary</p>
                    <p class="text-[12px] text-slate-700">{{ $groupMembers->count() }}/{{ collect($groupReview['members'] ?? [])->count() }} eligible</p>
                    @foreach (['identity' => 'Identity', 'crb' => 'CRB', 'income' => 'Income'] as $gk => $gl)
                        @php $gg = $gates[$gk] ?? []; @endphp
                        <p class="text-[12px] text-slate-700">{{ $gl }}: {{ (int) ($gg['decided'] ?? 0) }}/{{ (int) ($gg['total'] ?? 0) }} complete</p>
                    @endforeach
                </div>
            @endif
            @if ($finalBlocks !== [])
                <div class="rounded-2xl ring-1 ring-amber-200 bg-amber-50 px-4 py-3 space-y-1">
                    <p class="text-sm font-bold text-amber-950">Needs resolution · {{ count($finalBlocks) }}</p>
                    @foreach ($finalBlocks as $block)
                        <p class="text-[12px] text-amber-900">{{ $block['label'] ?? '' }}</p>
                    @endforeach
                </div>
            @endif
            @if ($isGroupFile)
                @php
                    $finalMembers = collect($groupReview['members'] ?? []);
                    $sigProgress = $groupReview['membership_signatures'] ?? $groupReview['contract_signatures'] ?? [];
                    $sigReceived = (int) ($sigProgress['signed_count'] ?? $sigProgress['signed'] ?? $sigProgress['received'] ?? 0);
                    $sigExpected = (int) ($sigProgress['total'] ?? $finalMembers->count());
                @endphp
                <div class="rounded-2xl ring-1 ring-brand/15 bg-white px-4 py-3 space-y-2">
                    <p class="text-sm font-bold text-gray-900">Members · {{ $finalMembers->count() }}</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($finalMembers as $fm)
                            @php
                                $g1 = (string) ($fm['gate_1'] ?? '');
                                $g2 = (string) ($fm['gate_2'] ?? '');
                                $memberReady = in_array($g1, ['pass', 'ok'], true) && in_array($g2, ['pass', 'ok'], true);
                            @endphp
                            <span class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-[11px] font-semibold ring-1 {{ $memberReady ? 'bg-emerald-50 text-emerald-900 ring-emerald-200' : 'bg-amber-50 text-amber-950 ring-amber-200' }}">
                                {{ $fm['name'] ?? 'Member' }}
                                {{ $memberReady ? '✓ Ready' : 'Needs attention' }}
                            </span>
                        @endforeach
                    </div>
                    <p class="text-[11px] text-slate-600">
                        Signatures · {{ $sigExpected }} members
                        @if ($sigExpected > 0)
                            · {{ $sigReceived }}/{{ $sigExpected }} received
                        @endif
                    </p>
                </div>
                <div x-data="{ groupSummaryOpen: false }" class="rounded-2xl ring-1 ring-brand/15 bg-white overflow-hidden">
                    <button type="button" class="w-full text-left px-4 py-3 flex items-center justify-between gap-2"
                            @click="groupSummaryOpen = ! groupSummaryOpen">
                        <span class="text-sm font-bold text-gray-900">Group review summary</span>
                        <span class="text-[11px] font-semibold text-slate-500" x-text="groupSummaryOpen ? 'Hide' : 'View group review'"></span>
                    </button>
                    <div x-show="groupSummaryOpen" x-cloak class="px-4 pb-4 border-t border-gray-100 pt-3">
                        @include('admin.loan-applications.review._checklist_phase_panels', [
                            'phase' => 'security',
                            'section' => 'group',
                            'deskPerson' => $deskPerson,
                            'deskG' => $deskG,
                            'deskM' => $deskM,
                        ])
                    </div>
                </div>
            @endif
            <div class="rounded-2xl ring-1 ring-brand/15 bg-brand-muted/30 px-4 py-3.5 flex flex-wrap items-center justify-between gap-3">
                @if ($finalReady)
                    <p class="text-sm font-semibold text-gray-900">All required Screening checks complete</p>
                    <a href="{{ $continueDecisionUrl }}"
                       class="inline-flex rounded-xl bg-brand-gold text-brand text-sm font-bold px-4 py-2.5 hover:brightness-95">
                        Continue to Decision
                    </a>
                @elseif ($finalBlock)
                    <p class="text-sm font-semibold text-gray-900">
                        {{ count($finalBlocks) === 1 ? '1 issue must be resolved before Decision' : count($finalBlocks).' issues must be resolved before Decision' }}
                    </p>
                    <a href="{{ $finalBlock['href'] }}"
                       class="inline-flex rounded-xl bg-brand text-white text-sm font-bold px-4 py-2.5 hover:bg-brand-light">
                        {{ $finalBlock['cta'] ?? 'Open missing item' }}
                    </a>
                @else
                    <p class="text-sm font-semibold text-gray-900">Finish the open checks above, then continue.</p>
                @endif
            </div>
        </div>
        @if ($deskPerson === 'guarantor')
            @php
                $deskGuarantor = collect($review['guarantors'] ?? [])->first(
                    fn ($row) => (int) ($row['link_id'] ?? 0) === (int) $deskG
                );
            @endphp
            @if ($deskGuarantor)
                <div class="px-5 pb-4">
                    @include('admin.loan-applications.review._guarantor_overview', [
                        'guarantor' => $deskGuarantor,
                        'single' => true,
                    ])
                </div>
            @endif
        @endif
    </div>
</section>
