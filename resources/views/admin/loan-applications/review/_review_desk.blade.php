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
    $gateKeys = array_keys($gates);

    $incomeGateOpen = collect($desk['groups'] ?? [])
        ->contains(function ($g) {
            if (($g['key'] ?? '') !== 'activity_income') {
                return false;
            }
            foreach ($g['items'] ?? [] as $item) {
                if (($item['key'] ?? '') === 'activity_income.income_evidence' && ($item['verdict'] ?? null) === null) {
                    return true;
                }
                if (($item['gate'] ?? null) === 'statements_vs_declared' && ($item['verdict'] ?? null) === null) {
                    return true;
                }
            }

            return false;
        });

    $firstOpenGate = $incomeGateOpen || ! ($sequence['later_unlocked'] ?? false)
        ? 'income'
        : collect($gates)->first(fn ($g) => empty($g['locked']) && ! ($g['complete'] ?? false));
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
             setGate(key) {
                 this.gate = key;
                 this.openItem = null;
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
            <span class="text-sm font-bold text-brand tabular-nums">{{ collect($gates)->sum('decided') }}/{{ collect($gates)->sum('total') }}</span>
            @if (($desk['failed'] ?? 0) > 0)
                <span class="text-[11px] font-bold text-amber-800">{{ $desk['failed'] }} concern</span>
            @endif
        </div>
        @if ($canEdit)
            <button type="submit" form="screening-checklist-form"
                    class="shrink-0 inline-flex rounded-xl bg-brand text-white text-xs font-bold px-3.5 py-2 hover:bg-brand-light">
                Save
            </button>
        @endif
    </div>

    <div class="px-5 pt-4 flex flex-wrap gap-2 border-b border-gray-100 pb-3">
        @foreach ($gates as $gate)
            @php
                $gKey = $gate['key'];
                $gLabel = $gate['label'];
            @endphp
            <button type="button"
                    @click="{{ ! empty($gate['locked']) ? '' : 'setGate('.e(json_encode($gKey)).')' }}"
                    @if (! empty($gate['locked'])) disabled @endif
                    :class="gate === @js($gKey)
                        ? 'bg-brand text-white ring-brand shadow-sm'
                        : 'bg-white text-gray-800 ring-gray-200 hover:bg-brand-muted/40'"
                    class="shrink-0 rounded-xl px-3.5 py-2.5 text-left ring-1 transition min-w-[9rem] disabled:opacity-60 disabled:cursor-not-allowed">
                <span class="sr-only">{{ $gLabel }}</span>
                <span class="block text-xs font-bold">{{ $gate['chip'] ?? $gLabel }}</span>
                <span class="block text-[11px] mt-0.5 tabular-nums"
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
            <form id="screening-checklist-form" method="POST" action="{{ route('admin.loan-applications.screening-checklist', $record) }}" class="space-y-4">
                @csrf
                <input type="hidden" name="person" value="{{ $deskPerson }}">
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
                            @include('admin.loan-applications.review._checklist_groups', [
                                'groups' => $gate['groups'],
                                'canEdit' => true,
                                'guidedIncome' => $gate['key'] === 'income',
                            ])
                            @if ($gate['key'] === 'income')
                                @include('admin.loan-applications.review._subject_affordability_gate')
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
            @if ($isGroupFile && $deskPerson === 'borrower')
                @include('admin.loan-applications.review._checklist_phase_panels', [
                    'phase' => 'security',
                    'section' => 'group',
                    'deskPerson' => $deskPerson,
                    'deskG' => $deskG,
                    'deskM' => $deskM,
                ])
            @endif
            @include('admin.loan-applications.review._checklist_phase_panels', [
                'phase' => 'security',
                'section' => 'wrapup',
                'deskPerson' => $deskPerson,
                'deskG' => $deskG,
                'deskM' => $deskM,
            ])
            @php
                $finalReady = is_array($readiness) && ($readiness['ready'] ?? false);
                $finalBlock = is_array($readiness) ? (($readiness['blocking_items'][0] ?? null)) : null;
            @endphp
            <div class="rounded-2xl ring-1 ring-brand/15 bg-brand-muted/30 px-4 py-3.5 flex flex-wrap items-center justify-between gap-3">
                @if ($finalReady)
                    <p class="text-sm font-semibold text-gray-900">All required screening checks complete</p>
                    <a href="{{ $continueDecisionUrl }}"
                       class="inline-flex rounded-xl bg-brand-gold text-brand text-sm font-bold px-4 py-2.5 hover:brightness-95">
                        Continue to decision
                    </a>
                @elseif ($finalBlock)
                    <p class="text-sm font-semibold text-gray-900">
                        {{ count($readiness['blocking_items'] ?? []) === 1 ? '1 item before decision' : count($readiness['blocking_items']).' items before decision' }}
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
    </div>
</section>
