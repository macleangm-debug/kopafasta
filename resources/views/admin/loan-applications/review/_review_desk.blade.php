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
        $firstM = collect($groupReview['members'] ?? [])->first();
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

    $phaseOrder = ['person', 'capacity', 'security'];
    $phases = [];
    foreach ($desk['groups'] ?? [] as $group) {
        $phaseKey = (string) ($group['phase'] ?? 'other');
        if (! isset($phases[$phaseKey])) {
            $phases[$phaseKey] = [
                'key' => $phaseKey,
                'label' => (string) ($group['phase_label'] ?? strtoupper($phaseKey)),
                'groups' => [],
                'decided' => 0,
                'total' => 0,
                'failed' => 0,
                'complete' => true,
            ];
        }
        $phases[$phaseKey]['groups'][] = $group;
        $phases[$phaseKey]['decided'] += (int) ($group['decided'] ?? 0);
        $phases[$phaseKey]['total'] += (int) ($group['total'] ?? count($group['items'] ?? []));
        $phases[$phaseKey]['failed'] += (int) ($group['failed'] ?? 0);
        if (! ($group['complete'] ?? false)) {
            $phases[$phaseKey]['complete'] = false;
        }
    }
    uksort($phases, function ($a, $b) use ($phaseOrder) {
        $ia = array_search($a, $phaseOrder, true);
        $ib = array_search($b, $phaseOrder, true);
        $ia = $ia === false ? 99 : $ia;
        $ib = $ib === false ? 99 : $ib;

        return $ia <=> $ib;
    });

    $incomeGateGroup = collect($desk['groups'] ?? [])
        ->first(function ($g) {
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

    // Gate 2 (statements vs declared revenue) opens Capacity before Personal when still undecided.
    $firstOpenGroup = $incomeGateGroup
        ?? collect($desk['groups'] ?? [])->first(fn ($g) => ! ($g['complete'] ?? false));
    if (! $firstOpenGroup) {
        $firstOpenGroup = collect($desk['groups'] ?? [])->first();
    }
    $defaultOpenGroup = (string) ($firstOpenGroup['key'] ?? '');
    $defaultPhase = (string) ($firstOpenGroup['phase'] ?? (array_key_first($phases) ?: 'person'));
    if (! isset($phases[$defaultPhase])) {
        $defaultPhase = array_key_first($phases) ?: 'person';
    }
    $requestPhase = (string) request('desk_phase', '');
    if ($requestPhase !== '' && isset($phases[$requestPhase])) {
        $defaultPhase = $requestPhase;
    }
    $isGroupFile = collect($groupReview['members'] ?? [])->isNotEmpty();
    $defaultCapacityTab = (string) request('capacity_tab', 'checks');
    if (! in_array($defaultCapacityTab, ['checks', 'documents', 'affordability', 'crb'], true)) {
        $defaultCapacityTab = 'checks';
    }
    $defaultSecurityTab = (string) request('security_tab', 'checks');
    if (! in_array($defaultSecurityTab, ['checks', 'group', 'wrapup'], true)) {
        $defaultSecurityTab = 'checks';
    }
    if ($defaultSecurityTab === 'group' && ! $isGroupFile) {
        $defaultSecurityTab = 'checks';
    }
    $requestOpenGroup = (string) request('open_group', '');
    if ($requestOpenGroup !== '') {
        $defaultOpenGroup = $requestOpenGroup;
    }
    $requestOpenItem = (string) request('open_item', '');

    $phaseHints = [
        'person' => '',
        'capacity' => '',
        'security' => '',
    ];
@endphp

<section id="review-desk" class="rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm overflow-hidden scroll-mt-24"
         x-data="{
             phase: @js($defaultPhase),
             capacityTab: @js($defaultCapacityTab),
             securityTab: @js($defaultSecurityTab),
             openGroup: @js($defaultOpenGroup !== '' ? $defaultOpenGroup : null),
             openItem: @js($requestOpenItem !== '' ? $requestOpenItem : null),
             setPhase(key) {
                 this.phase = key;
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
                     if (! data || data.verdict) {
                         return;
                     }
                     if (data.needsStatementTotals && ! (Number(data.deposits) > 0)) {
                         return;
                     }
                     data.verdict = 'pass';
                 });
             }
         }">
    <div class="px-5 py-3 border-b border-gray-100 bg-gradient-to-r from-brand-muted/50 to-white flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
            <h3 class="text-base font-bold text-gray-900">Review</h3>
            <span class="text-sm font-bold text-brand tabular-nums">{{ $desk['decided'] }}/{{ $desk['total'] }}</span>
            @if (($desk['failed'] ?? 0) > 0)
                <span class="text-[11px] font-bold text-rose-700">{{ $desk['failed'] }} fail</span>
            @endif
        </div>
        @if ($canEdit)
            <button type="submit" form="screening-checklist-form"
                    class="shrink-0 inline-flex rounded-xl bg-brand text-white text-xs font-bold px-3.5 py-2 hover:bg-brand-light">
                Save →
            </button>
        @endif
    </div>

    {{-- Phase tabs (person switcher lives above Review checklist / Profiles / Decision) --}}
    <div class="px-5 pt-4 flex flex-wrap gap-2 border-b border-gray-100 pb-3">
        @foreach ($phases as $phase)
            @php
                $pKey = $phase['key'];
                $pLabel = match ($pKey) {
                    'person' => '1 · Personal in place',
                    'capacity' => '2 · Capacity and evidence',
                    'security' => '3 · Security and close',
                    default => $phase['label'],
                };
            @endphp
            <button type="button"
                    @click="setPhase(@js($pKey))"
                    :class="phase === @js($pKey)
                        ? 'bg-brand text-white ring-brand shadow-sm'
                        : 'bg-white text-gray-800 ring-gray-200 hover:bg-brand-muted/40'"
                    class="shrink-0 rounded-xl px-3.5 py-2.5 text-left ring-1 transition min-w-[10rem]">
                <span class="block text-xs font-bold">{{ $pLabel }}</span>
                <span class="block text-[11px] mt-0.5 tabular-nums"
                      :class="phase === @js($pKey) ? 'text-white/80' : 'text-gray-500'">
                    {{ $phase['decided'] }}/{{ $phase['total'] }}
                    @if ($phase['failed'] > 0)
                        · {{ $phase['failed'] }} fail
                    @elseif ($phase['complete'])
                        · Done
                    @endif
                </span>
            </button>
        @endforeach
    </div>

    <div class="p-5 space-y-4">
        @foreach ($phases as $phase)
            <div x-show="phase === @js($phase['key'])" x-cloak class="space-y-4">
                @if ($phase['key'] === 'capacity')
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ([
                            'checks' => 'Checks',
                            'documents' => 'Docs',
                            'affordability' => 'Affordability',
                            'crb' => 'CRB',
                        ] as $tabKey => $tabLabel)
                            <button type="button"
                                    @click="capacityTab = @js($tabKey)"
                                    :class="capacityTab === @js($tabKey)
                                        ? 'bg-brand-muted text-brand ring-brand/30'
                                        : 'bg-white text-gray-600 ring-gray-200 hover:bg-gray-50'"
                                    class="rounded-lg px-2.5 py-1.5 text-[11px] font-semibold ring-1 transition">
                                {{ $tabLabel }}
                            </button>
                        @endforeach
                    </div>
                @elseif ($phase['key'] === 'security')
                    <div class="flex flex-wrap gap-1.5">
                        @foreach (array_filter([
                            'checks' => 'Checks',
                            'group' => $isGroupFile && $deskPerson === 'borrower' ? 'Group' : null,
                            'wrapup' => 'Close',
                        ]) as $tabKey => $tabLabel)
                            <button type="button"
                                    @click="securityTab = @js($tabKey)"
                                    :class="securityTab === @js($tabKey)
                                        ? 'bg-brand-muted text-brand ring-brand/30'
                                        : 'bg-white text-gray-600 ring-gray-200 hover:bg-gray-50'"
                                    class="rounded-lg px-2.5 py-1.5 text-[11px] font-semibold ring-1 transition">
                                {{ $tabLabel }}
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach

        {{-- Pass/Fail only inside the save form (keeps document verify/reject forms valid) --}}
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

                @foreach ($phases as $phase)
                    @php
                        $checksShow = match ($phase['key']) {
                            'person' => "phase === 'person'",
                            'capacity' => "phase === 'capacity' && capacityTab === 'checks'",
                            'security' => "phase === 'security' && securityTab === 'checks'",
                            default => "phase === '{$phase['key']}'",
                        };
                    @endphp
                    <div x-show="{!! $checksShow !!}" x-cloak class="space-y-3">
                        @include('admin.loan-applications.review._checklist_groups', [
                            'groups' => $phase['groups'],
                            'canEdit' => true,
                        ])
                    </div>
                @endforeach

                <div class="flex justify-end">
                    <button type="submit"
                            class="inline-flex items-center rounded-xl bg-brand px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-light">
                        Save
                    </button>
                </div>
            </form>
        @else
            @foreach ($phases as $phase)
                @php
                    $checksShowRo = match ($phase['key']) {
                        'person' => "phase === 'person'",
                        'capacity' => "phase === 'capacity' && capacityTab === 'checks'",
                        'security' => "phase === 'security' && securityTab === 'checks'",
                        default => "phase === '{$phase['key']}'",
                    };
                @endphp
                <div x-show="{!! $checksShowRo !!}" x-cloak class="space-y-3">
                    @include('admin.loan-applications.review._checklist_groups_readonly', ['groups' => $phase['groups']])
                </div>
            @endforeach
        @endif

        {{-- Evidence / wrap-up outside the checklist form --}}
        @foreach ($phases as $phase)
            @if ($phase['key'] === 'capacity')
                <div x-show="phase === 'capacity' && capacityTab === 'documents'" x-cloak>
                    @include('admin.loan-applications.review._checklist_phase_panels', [
                        'phase' => 'capacity',
                        'section' => 'documents',
                        'deskPerson' => $deskPerson,
                        'deskG' => $deskG,
                        'deskM' => $deskM,
                    ])
                </div>
                <div x-show="phase === 'capacity' && capacityTab === 'affordability'" x-cloak>
                    @include('admin.loan-applications.review._checklist_phase_panels', [
                        'phase' => 'capacity',
                        'section' => 'affordability',
                        'deskPerson' => $deskPerson,
                        'deskG' => $deskG,
                        'deskM' => $deskM,
                    ])
                </div>
                <div x-show="phase === 'capacity' && capacityTab === 'crb'" x-cloak>
                    @include('admin.loan-applications.review._checklist_phase_panels', [
                        'phase' => 'capacity',
                        'section' => 'crb',
                        'deskPerson' => $deskPerson,
                        'deskG' => $deskG,
                        'deskM' => $deskM,
                    ])
                </div>
            @endif
            @if ($phase['key'] === 'security')
                @if ($isGroupFile && $deskPerson === 'borrower')
                    <div x-show="phase === 'security' && securityTab === 'group'" x-cloak>
                        @include('admin.loan-applications.review._checklist_phase_panels', [
                            'phase' => 'security',
                            'section' => 'group',
                            'deskPerson' => $deskPerson,
                            'deskG' => $deskG,
                            'deskM' => $deskM,
                        ])
                    </div>
                @endif
                <div x-show="phase === 'security' && securityTab === 'wrapup'" x-cloak>
                    @include('admin.loan-applications.review._checklist_phase_panels', [
                        'phase' => 'security',
                        'section' => 'wrapup',
                        'deskPerson' => $deskPerson,
                        'deskG' => $deskG,
                        'deskM' => $deskM,
                    ])
                </div>
            @endif
        @endforeach
    </div>
</section>
