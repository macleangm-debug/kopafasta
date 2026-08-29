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

    $gates = app(\App\Services\ScreeningChecklistGateService::class)->regroup($desk['groups'] ?? []);
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

    $firstOpenGate = $incomeGateOpen ? 'income' : collect($gates)->first(fn ($g) => ! ($g['complete'] ?? false));
    $defaultGate = is_array($firstOpenGate) ? ($firstOpenGate['key'] ?? 'identity') : ($firstOpenGate ?: ($gateKeys[0] ?? 'identity'));

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
    <div class="px-5 py-3 border-b border-gray-100 bg-gradient-to-r from-brand-muted/50 to-white flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
            <h3 class="text-base font-bold text-gray-900">Review checklist</h3>
            <span class="text-sm font-bold text-brand tabular-nums">{{ $desk['decided'] ?? 0 }}/{{ $desk['total'] ?? 0 }}</span>
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
                    @click="setGate(@js($gKey))"
                    :class="gate === @js($gKey)
                        ? 'bg-brand text-white ring-brand shadow-sm'
                        : 'bg-white text-gray-800 ring-gray-200 hover:bg-brand-muted/40'"
                    class="shrink-0 rounded-xl px-3.5 py-2.5 text-left ring-1 transition min-w-[9rem]">
                <span class="block text-xs font-bold">{{ $gLabel }}</span>
                <span class="block text-[11px] mt-0.5 tabular-nums"
                      :class="gate === @js($gKey) ? 'text-white/80' : 'text-gray-500'">
                    {{ $gate['decided'] }}/{{ $gate['total'] }}
                    @if ($gate['failed'] > 0)
                        · {{ $gate['failed'] }} concern
                    @elseif ($gate['complete'])
                        · Done
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
                        @include('admin.loan-applications.review._checklist_groups', [
                            'groups' => $gate['groups'],
                            'canEdit' => true,
                            'guidedIncome' => $gate['key'] === 'income',
                        ])
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

        <div x-show="gate === 'income'" x-cloak>
            @include('admin.loan-applications.review._checklist_phase_panels', [
                'phase' => 'capacity',
                'section' => 'affordability',
                'deskPerson' => $deskPerson,
                'deskG' => $deskG,
                'deskM' => $deskM,
            ])
        </div>
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
