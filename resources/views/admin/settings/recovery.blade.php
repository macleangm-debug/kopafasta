<x-admin.layout title="Recovery Policy" heading="Recovery Policy" subheading="Timeline, partner SLAs, repossession, service rates, and auto-assign & KPI rules">
    @include('admin.settings._tabs', ['active' => 'recovery', 'showHelp' => false])

@php
        $initialTab = old('_tab', request('tab', 'timeline'));
        if (! in_array($initialTab, ['timeline', 'recovery', 'repossession', 'service', 'auto_assign'], true)) {
            $initialTab = 'timeline';
        }
        $recoveryTypeCount = count($types);
        $serviceTypeCount = count($partnerDefaults);
        $autoAssignBoards = $autoAssignBoards ?? [];
        $autoAssignBoardCount = count($autoAssignBoards);
        $recoveryHelpPages = [
            'timeline' => 'recovery.timeline',
            'recovery' => 'recovery.partners',
            'repossession' => 'recovery.repossession',
            'service' => 'recovery.service',
            'auto_assign' => 'recovery.auto_assign',
        ];
    @endphp

    <form method="POST" action="{{ route('admin.settings.recovery.save') }}" class="space-y-5" novalidate
          x-data="{
              tab: @js($initialTab),
              editing: @js($errors->any()),
              setTab(next) {
                  this.tab = next;
                  this.$dispatch('settings-help-set', { ns: 'recovery', key: next });
                  const url = new URL(window.location.href);
                  url.searchParams.set('tab', next);
                  history.replaceState({}, '', url);
              },
              cancelEdit() {
                  window.location.assign(window.location.pathname + window.location.search);
              }
          }"
          @submit="document.getElementById('recovery_active_tab').value = tab">
        @csrf @method('PUT')
        <input type="hidden" name="_tab" id="recovery_active_tab" value="{{ $initialTab }}">

        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-3">
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="setTab('timeline')"
                        :class="tab === 'timeline' ? 'bg-brand text-white ring-brand' : 'bg-white text-gray-700 ring-gray-200 hover:bg-gray-50'"
                        class="px-3.5 py-2 rounded-xl text-sm font-semibold ring-1 transition">
                    1. Timeline
                </button>
                <button type="button" @click="setTab('recovery')"
                        :class="tab === 'recovery' ? 'bg-brand text-white ring-brand' : 'bg-white text-gray-700 ring-gray-200 hover:bg-gray-50'"
                        class="px-3.5 py-2 rounded-xl text-sm font-semibold ring-1 transition">
                    2. Recovery partners
                    <span class="ml-1 text-[11px] opacity-80">({{ $recoveryTypeCount }})</span>
                </button>
                <button type="button" @click="setTab('repossession')"
                        :class="tab === 'repossession' ? 'bg-brand text-white ring-brand' : 'bg-white text-gray-700 ring-gray-200 hover:bg-gray-50'"
                        class="px-3.5 py-2 rounded-xl text-sm font-semibold ring-1 transition">
                    3. Repossession
                </button>
                <button type="button" @click="setTab('service')"
                        :class="tab === 'service' ? 'bg-brand text-white ring-brand' : 'bg-white text-gray-700 ring-gray-200 hover:bg-gray-50'"
                        class="px-3.5 py-2 rounded-xl text-sm font-semibold ring-1 transition">
                    4. Service rates
                    <span class="ml-1 text-[11px] opacity-80">({{ $serviceTypeCount }})</span>
                </button>
                <button type="button" @click="setTab('auto_assign')"
                        :class="tab === 'auto_assign' ? 'bg-brand text-white ring-brand' : 'bg-white text-gray-700 ring-gray-200 hover:bg-gray-50'"
                        class="px-3.5 py-2 rounded-xl text-sm font-semibold ring-1 transition">
                    5. Auto-assign &amp; KPIs
                    <span class="ml-1 text-[11px] opacity-80">({{ $autoAssignBoardCount }})</span>
                </button>
            </div>
            <div class="ml-auto flex flex-wrap items-center gap-2 shrink-0">
                <x-admin.settings-help-drawer
                    ns="recovery"
                    :pages="$recoveryHelpPages"
                    :initial-key="$initialTab" />
                <button type="button" x-show="!editing" @click="editing = true"
                        class="inline-flex items-center rounded-xl bg-brand px-4 py-2 text-xs font-semibold text-white hover:bg-brand-light">
                    Edit
                </button>
                <button type="button" x-show="editing" x-cloak @click="cancelEdit()"
                        class="inline-flex items-center rounded-xl bg-white px-4 py-2 text-xs font-semibold text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" x-show="editing" x-cloak
                        class="inline-flex items-center rounded-xl bg-brand-gold px-4 py-2 text-xs font-bold text-brand hover:brightness-95">
                    Save recovery policy
                </button>
            </div>
        </div>
        <p class="text-xs text-gray-500" x-show="!editing" x-cloak>Read-only until you click Edit. All tabs save together.</p>
        <fieldset :disabled="!editing" class="min-w-0 space-y-5 disabled:opacity-90">

        {{-- Tab 1: Timeline --}}
        <div x-show="tab === 'timeline'" x-cloak class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-1">Timeline & automation</h3>
            <p class="text-xs text-gray-500 mb-4">Grace fallback and when recovery starts. Product grace still wins when set on the loan product.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-admin.input name="grace_period_days" label="Fallback grace (days) — only if loan has no product grace" type="number" min="1" max="60"
                               :value="$values['grace_period_days'] ?? 2" required
                               help="Canonical grace lives on each loan product (and Loan rules)." />
                <x-admin.input name="call_center_lead_days" label="Call center lead (days before grace ends)" type="number" min="0" max="30"
                               :value="$values['call_center_lead_days'] ?? 0" required />
                <x-admin.input name="auction_hold_days" label="Auction hold after repossession (days)" type="number" min="1" max="30"
                               :value="$values['auction_hold_days'] ?? 4" required
                               help="After debt collector marks repossession complete, the borrower has this many days to settle before an auctioneer is auto-assigned." />
                <div class="md:col-span-2 flex items-start gap-2 pt-2">
                    <input type="hidden" name="gps_map_enabled" value="0">
                    <input type="checkbox" name="gps_map_enabled" value="1" id="gps_map_enabled"
                           @checked((bool) ($values['gps_map_enabled'] ?? false))
                           class="mt-0.5 rounded border-gray-300 text-brand">
                    <label for="gps_map_enabled" class="text-sm text-gray-700">
                        <span class="font-medium">Enable GPS “View Asset Location” map links</span>
                        <span class="block text-xs text-gray-500 mt-0.5">
                            Each device’s tracking URL is entered by the GPS installer on the loan. When enabled, credit management, call center, and debt collectors see a <strong>View Asset Location</strong> button. Debt collectors also always see the GPS partner’s contact details to request deactivation on the provider platform.
                        </span>
                    </label>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Calculate recovery fees from</label>
                    <div class="flex flex-wrap gap-4 text-sm">
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" name="fee_base" value="principal" @checked(($values['fee_base'] ?? 'principal') === 'principal') class="text-brand">
                            Principal only
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" name="fee_base" value="outstanding" @checked(($values['fee_base'] ?? '') === 'outstanding') class="text-brand">
                            Outstanding balance
                        </label>
                    </div>
                </div>
                <div class="flex items-center gap-2 pt-2">
                    <input type="hidden" name="auto_assign_call_center" value="0">
                    <input type="checkbox" name="auto_assign_call_center" value="1" id="auto_assign_call_center"
                           @checked((bool) ($values['auto_assign_call_center'] ?? true))
                           class="rounded border-gray-300 text-brand">
                    <label for="auto_assign_call_center" class="text-sm text-gray-700">Auto-assign call center when grace threshold is reached</label>
                </div>
                <div class="flex items-center gap-2 pt-2">
                    <input type="hidden" name="auto_escalate" value="0">
                    <input type="checkbox" name="auto_escalate" value="1" id="auto_escalate"
                           @checked((bool) ($values['auto_escalate'] ?? true))
                           class="rounded border-gray-300 text-brand">
                    <label for="auto_escalate" class="text-sm text-gray-700">Auto escalate when partner SLA expires</label>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-4">Typical path: product grace → Call Center → Debt Collector → repossession hold ({{ $values['auction_hold_days'] ?? 4 }} days) → Auctioneer → Legal. GPS install feeds map links for credit &amp; recovery.</p>
        </div>

        {{-- Tab 2: Recovery partners (all escalation types) --}}
        <div x-show="tab === 'recovery'" x-cloak class="space-y-4">
            <div class="rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-3 text-sm text-sky-950">
                <p class="font-semibold">All recovery escalation partners ({{ $recoveryTypeCount }})</p>
                <p class="text-xs mt-1 text-sky-900/80">
                    Drag cards to set priority (1 = first). Escalation follows Call Center → Debt Collector → Auctioneer → Legal;
                    GPS is for tracking. Insurance and Valuation use the
                    <button type="button" class="font-semibold underline" @click="setTab('service')">Service rates</button> tab.
                </p>
            </div>

            @php
                $orderedTypes = [];
                foreach ($types as $type => $meta) {
                    $orderedTypes[] = [
                        'type' => $type,
                        'label' => $meta['label'],
                        'vendor_category' => $meta['vendor_category'] ?? $type,
                        'priority' => (int) ($values['priority'][$type] ?? $meta['default_priority'] ?? 99),
                        'has_markup' => (bool) ($values['has_markup'][$type] ?? false),
                        'markup' => (float) ($values['markup_percent'][$type] ?? 0),
                        'loan_types' => $values['loan_types'][$type] ?? 'all',
                        'collateral' => $values['collateral_scope'][$type] ?? 'all',
                        'sla' => $values['sla_days'][$type] ?? $meta['default_sla_days'],
                        'fee_type' => $values['fee_type'][$type] ?? 'percentage',
                        'commission' => $values['commission_percent'][$type] ?? $meta['default_commission_percent'],
                        'fixed' => $values['fixed_amount'][$type] ?? '',
                        'charges_borrower' => (bool) ($meta['charges_borrower'] ?? true),
                        'auto_escalate' => (bool) ($values['auto_escalate_type'][$type] ?? true),
                    ];
                }
            @endphp

            <div class="space-y-3"
                 x-data="{
                    items: @js($orderedTypes),
                    dragIndex: null,
                    syncPriorities() {
                        this.items.forEach((item, i) => { item.priority = i + 1; });
                    },
                    onDragStart(index) { this.dragIndex = index; },
                    onDrop(index) {
                        if (this.dragIndex === null || this.dragIndex === index) return;
                        const moved = this.items.splice(this.dragIndex, 1)[0];
                        this.items.splice(index, 0, moved);
                        this.dragIndex = null;
                        this.syncPriorities();
                    }
                 }">
                <template x-for="(item, index) in items" :key="item.type">
                    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5"
                         draggable="true"
                         @dragstart="onDragStart(index)"
                         @dragover.prevent
                         @drop.prevent="onDrop(index)"
                         :class="dragIndex === index ? 'opacity-60 ring-brand' : ''">
                        <div class="flex flex-wrap items-start justify-between gap-2 mb-4">
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 cursor-grab active:cursor-grabbing text-gray-400 select-none text-lg leading-none" title="Drag to reorder">⋮⋮</div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-900" x-text="item.label"></p>
                                    <p class="text-xs text-amber-800 mt-1" x-show="item.charges_borrower === false" x-cloak>
                                        No borrower recovery fee. GPS install/monitoring is a post-approval fee under Service rates. Deactivation is free.
                                    </p>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        Priority <span class="font-semibold text-brand" x-text="item.priority"></span>
                                        · drag to reorder
                                    </p>
                                </div>
                            </div>
                            <a :href="'{{ url('/admin/partners/create') }}?category=' + item.vendor_category"
                               class="inline-flex text-xs font-semibold text-brand hover:underline">
                                Add partner →
                            </a>
                        </div>

                        <input type="hidden" :name="'priority_' + item.type" :value="item.priority">

                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3"
                             x-data="{ hasMarkup: item.has_markup }"
                             x-init="$watch('hasMarkup', v => item.has_markup = v)">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Loan types</label>
                                <input type="text" :name="'loan_types_' + item.type" x-model="item.loan_types"
                                       placeholder="all" class="w-full rounded-lg border-gray-300 text-sm font-mono">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Collateral</label>
                                <select :name="'collateral_scope_' + item.type" x-model="item.collateral" class="w-full rounded-lg border-gray-300 text-sm">
                                    <option value="all">All</option>
                                    <option value="secured">Secured</option>
                                    <option value="unsecured">Unsecured</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">SLA days</label>
                                <input type="number" :name="'sla_days_' + item.type" x-model="item.sla" min="1" max="90"
                                       class="w-full rounded-lg border-gray-300 text-sm">
                            </div>
                            <div x-show="item.charges_borrower !== false">
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Fee type</label>
                                <select :name="'fee_type_' + item.type" x-model="item.fee_type" class="w-full rounded-lg border-gray-300 text-sm">
                                    <option value="percentage">%</option>
                                    <option value="fixed">Fixed</option>
                                    <option value="hybrid">Hybrid (fixed + %)</option>
                                </select>
                            </div>
                            <div x-show="item.charges_borrower !== false">
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Commission %</label>
                                <input type="number" :name="'commission_percent_' + item.type" x-model="item.commission" step="0.1" min="0" max="100"
                                       class="w-full rounded-lg border-gray-300 text-sm">
                            </div>
                            <div x-show="item.charges_borrower !== false">
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Fixed fee</label>
                                <input type="number" :name="'fixed_amount_' + item.type" x-model="item.fixed" step="1" min="0"
                                       class="w-full rounded-lg border-gray-300 text-sm">
                            </div>
                            <div class="flex items-center gap-2 pb-1" x-show="item.charges_borrower !== false">
                                <input type="hidden" :name="'has_markup_' + item.type" value="0">
                                <input type="checkbox" :name="'has_markup_' + item.type" value="1" x-model="hasMarkup"
                                       class="rounded border-gray-300 text-brand">
                                <label class="text-sm text-gray-700">Has markup</label>
                            </div>
                            <div x-show="item.charges_borrower !== false">
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Markup %</label>
                                <input type="number" :name="'markup_percent_' + item.type" x-model="item.markup" step="0.1" min="0" max="100"
                                       x-bind:disabled="!hasMarkup"
                                       class="w-full rounded-lg border-gray-300 text-sm disabled:bg-gray-50 disabled:text-gray-400">
                                <input type="hidden" :name="'markup_percent_' + item.type" :value="item.markup" x-bind:disabled="hasMarkup">
                                <p class="mt-1 text-[11px] text-gray-500" x-show="!hasMarkup" x-cloak>No markup applied.</p>
                            </div>
                            <div class="flex items-end pb-2">
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input type="hidden" :name="'auto_escalate_type_' + item.type" value="0">
                                    <input type="checkbox" :name="'auto_escalate_type_' + item.type" value="1" x-model="item.auto_escalate"
                                           class="rounded border-gray-300 text-brand">
                                    Auto escalate
                                </label>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Tab 3: Repossession --}}
        <div x-show="tab === 'repossession'" x-cloak class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-1">Repossession charges by asset type</h3>
            <p class="text-xs text-gray-500 mb-4">Fixed partner cost + markup % for debt collection / repossession. Borrower charge = partner cost + markup.</p>
            @php $assetTypes = app(\App\Services\RepossessionChargeService::class)->assetTypes(); @endphp
            <div class="space-y-3">
                @foreach ($assetTypes as $type => $row)
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end rounded-lg border border-gray-200 p-3">
                        <div class="text-sm font-semibold capitalize">{{ $row['label'] ?? $type }}</div>
                        <x-admin.input :name="'repossession_partner_cost_'.$type" label="Partner cost" type="number" step="1" min="0"
                                       :value="$values['repossession_charges'][$type]['partner_cost'] ?? $row['partner_cost'] ?? ''" />
                        <x-admin.input :name="'repossession_markup_'.$type" label="Markup %" type="number" step="0.1" min="0" max="100"
                                       :value="$values['repossession_charges'][$type]['markup_percent'] ?? $row['markup_percent'] ?? 10" />
                        <label class="inline-flex items-center gap-2 text-sm pb-2">
                            <input type="hidden" name="repossession_manual_{{ $type }}" value="0">
                            <input type="checkbox" name="repossession_manual_{{ $type }}" value="1"
                                   @checked((bool) ($values['repossession_charges'][$type]['manual_quote'] ?? $row['manual_quote'] ?? false))
                                   class="rounded border-gray-300 text-brand">
                            Manual quote
                        </label>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Tab 4: Service partner rates (insurance, GPS pricing, valuer) --}}
        <div x-show="tab === 'service'" x-cloak class="space-y-4">
            <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-950">
                <p class="font-semibold">Origination / service partners ({{ $serviceTypeCount }})</p>
                <p class="text-xs mt-1 text-amber-900/80">
                    Insurance, GPS device pricing, and Valuation defaults. Valuation is per pledged asset (same idea as GPS install per device). Application fee stays 1×. GPS install + monthly monitoring is a post-approval fee. Changing these amounts updates new contracts immediately. GPS deactivation during collection has no extra borrower charge and is not a recovery fee.
                </p>
            </div>

            @foreach ($partnerDefaults as $category => $row)
                @php
                    $mode = $row['pricing_mode'] ?? 'fixed';
                    $chargeUnit = (string) ($row['charge_unit'] ?? '');
                    $basePriceLabel = $chargeUnit !== ''
                        ? 'Default base price (TZS) '.$chargeUnit
                        : 'Default base price (TZS)';
                    $hasMarkup = (bool) old("{$category}_has_markup", $row['has_markup'] ?? false);
                    $storedMarkup = old("{$category}_markup_percent", $row['stored_markup_percent'] ?? $row['markup_percent'] ?? 0);
                @endphp
                <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5"
                     x-data="{ hasMarkup: {{ $hasMarkup ? 'true' : 'false' }} }">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ $row['label'] }}</p>
                            @if (! empty($row['help']))
                                <p class="text-xs text-gray-500 mt-0.5">{{ $row['help'] }}</p>
                            @endif
                        </div>
                        <a href="{{ route('admin.partners.create', ['category' => $row['add_category'] ?? $category]) }}"
                           class="inline-flex items-center text-xs font-semibold text-brand hover:underline">
                            Add partner →
                        </a>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
                        @if ($mode === 'percent_of_value')
                            <x-admin.input name="{{ $category }}_rate_percent" label="Default rate (% of value)" type="number" step="0.1" min="0" max="100"
                                           :value="old($category.'_rate_percent', $row['rate_percent'] ?? 3.5)" required />
                        @else
                            <x-admin.input name="{{ $category }}_base_cost" :label="$basePriceLabel" type="number" step="1" min="0"
                                           :value="old($category.'_base_cost', $row['base_cost'] ?? 0)" required
                                           :help="$chargeUnit === 'per asset' ? 'Quoted × number of pledged assets. Application fee is not multiplied.' : ($chargeUnit === 'per device' ? 'Installation charged once per GPS device.' : null)" />
                        @endif

                        @if ($mode === 'fixed_plus_recurring')
                            <x-admin.input name="{{ $category }}_monitoring_monthly" label="Monitoring / month (TZS)" type="number" step="1" min="0"
                                           :value="old($category.'_monitoring_monthly', $row['monitoring_monthly'] ?? 0)" required />
                        @endif

                        <div class="flex items-center gap-2 pb-2">
                            <input type="hidden" name="{{ $category }}_has_markup" value="0">
                            <input type="checkbox" name="{{ $category }}_has_markup" value="1" id="{{ $category }}_has_markup"
                                   x-model="hasMarkup"
                                   class="rounded border-gray-300 text-brand">
                            <label for="{{ $category }}_has_markup" class="text-sm text-gray-700">Has markup</label>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ $mode === 'percent_of_value' ? 'Markup % (added to rate)' : 'Markup % (on partner cost)' }}
                            </label>
                            <input type="number" name="{{ $category }}_markup_percent" step="0.1" min="0" max="100"
                                   value="{{ $storedMarkup }}"
                                   x-bind:disabled="!hasMarkup"
                                   class="w-full rounded-lg border-gray-300 text-sm disabled:bg-gray-50 disabled:text-gray-400">
                            <input type="hidden" name="{{ $category }}_markup_percent" value="{{ $storedMarkup }}" x-bind:disabled="hasMarkup">
                            <p class="mt-1 text-[11px] text-gray-500" x-show="!hasMarkup" x-cloak>No markup applied.</p>
                            @if ($mode === 'percent_of_value')
                                <p class="mt-1 text-[11px] text-gray-500" x-show="hasMarkup" x-cloak>Borrower pays (rate + markup)% of insured value. Partner earns rate% only.</p>
                            @else
                                <p class="mt-1 text-[11px] text-gray-500" x-show="hasMarkup" x-cloak>Borrower pays base × (1 + markup%){{ $chargeUnit !== '' ? ' '.$chargeUnit : '' }}. Partner earns base only; markup is platform revenue.</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Tab 5: Auto-assign & KPIs --}}
        <div x-show="tab === 'auto_assign'" x-cloak class="space-y-4">
            <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-950">
                <p class="font-semibold">Auto-assign &amp; KPI rules</p>
                <p class="text-xs mt-1 text-emerald-900/80">
                    Rules only — who gets work is decided from active partners of each type (managed under Partners).
                    Origination (valuer, GPS, insurance) can also be edited under
                    <a href="{{ route('admin.partners.origination-auto-assign') }}" class="font-semibold underline">Partners → Origination auto-assignment</a>.
                    Click Edit on a card to change values, then save this page.
                </p>
            </div>

            @foreach ($autoAssignBoards as $board)
                @php $settings = $board['settings'] ?? []; @endphp
                <div class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 bg-gradient-to-r from-brand-muted/40 to-white flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] uppercase tracking-widest font-bold text-brand">
                                {{ ($board['group'] ?? '') === 'service' ? 'Service / origination' : 'Recovery' }}
                            </p>
                            <h3 class="text-base font-bold text-gray-900 mt-0.5">{{ $board['label'] }}</h3>
                            <p class="text-xs text-gray-500 mt-1">{{ $board['kpi_source'] }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span @class([
                                'inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold',
                                'bg-emerald-100 text-emerald-800' => $settings['enabled'] ?? false,
                                'bg-gray-100 text-gray-600' => ! ($settings['enabled'] ?? false),
                            ])>
                                {{ ($settings['enabled'] ?? false) ? 'Auto-assign on' : 'Auto-assign off' }}
                            </span>
                            <a href="{{ $board['create_url'] }}"
                               class="inline-flex items-center rounded-full bg-white ring-1 ring-gray-200 px-2.5 py-1 text-[11px] font-semibold text-brand hover:bg-brand-muted/40">
                                {{ $board['partner_count'] }} partner{{ $board['partner_count'] === 1 ? '' : 's' }} · manage →
                            </a>
                        </div>
                    </div>

                    <div class="p-5">
                        <x-admin.auto-assign-settings
                            :suffix="$board['suffix']"
                            :settings="$settings"
                            :show-sla-days="$board['show_sla_days']" />
                    </div>
                </div>
            @endforeach
        </div>

        </fieldset>
    </form>
</x-admin.layout>
