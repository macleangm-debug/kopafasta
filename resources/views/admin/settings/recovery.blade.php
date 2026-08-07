<x-admin.layout title="Recovery Policy" heading="Recovery Policy" subheading="Timeline, recovery partner SLAs, repossession charges, and service partner rates">
    @include('admin.settings._tabs', ['active' => 'recovery'])

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    @php
        $initialTab = old('_tab', request('tab', 'timeline'));
        if (! in_array($initialTab, ['timeline', 'recovery', 'repossession', 'service'], true)) {
            $initialTab = 'timeline';
        }
        $recoveryTypeCount = count($types);
        $serviceTypeCount = count($partnerDefaults);
    @endphp

    <form method="POST" action="{{ route('admin.settings.recovery.save') }}" class="space-y-5"
          x-data="{ tab: @js($initialTab) }"
          @submit="document.getElementById('recovery_active_tab').value = tab">
        @csrf @method('PUT')
        <input type="hidden" name="_tab" id="recovery_active_tab" value="{{ $initialTab }}">

        <div class="flex flex-wrap gap-2 border-b border-gray-200 pb-3">
            <button type="button" @click="tab = 'timeline'"
                    :class="tab === 'timeline' ? 'bg-brand text-white ring-brand' : 'bg-white text-gray-700 ring-gray-200 hover:bg-gray-50'"
                    class="px-3.5 py-2 rounded-xl text-sm font-semibold ring-1 transition">
                1. Timeline
            </button>
            <button type="button" @click="tab = 'recovery'"
                    :class="tab === 'recovery' ? 'bg-brand text-white ring-brand' : 'bg-white text-gray-700 ring-gray-200 hover:bg-gray-50'"
                    class="px-3.5 py-2 rounded-xl text-sm font-semibold ring-1 transition">
                2. Recovery partners
                <span class="ml-1 text-[11px] opacity-80">({{ $recoveryTypeCount }})</span>
            </button>
            <button type="button" @click="tab = 'repossession'"
                    :class="tab === 'repossession' ? 'bg-brand text-white ring-brand' : 'bg-white text-gray-700 ring-gray-200 hover:bg-gray-50'"
                    class="px-3.5 py-2 rounded-xl text-sm font-semibold ring-1 transition">
                3. Repossession
            </button>
            <button type="button" @click="tab = 'service'"
                    :class="tab === 'service' ? 'bg-brand text-white ring-brand' : 'bg-white text-gray-700 ring-gray-200 hover:bg-gray-50'"
                    class="px-3.5 py-2 rounded-xl text-sm font-semibold ring-1 transition">
                4. Service rates
                <span class="ml-1 text-[11px] opacity-80">({{ $serviceTypeCount }})</span>
            </button>
        </div>

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
            <p class="text-xs text-gray-500 mt-4">Typical path: product grace → Call Center → Debt Collector → Auctioneer → Legal. GPS is for tracking tasks.</p>
        </div>

        {{-- Tab 2: Recovery partners (all escalation types) --}}
        <div x-show="tab === 'recovery'" x-cloak class="space-y-4">
            <div class="rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-3 text-sm text-sky-950">
                <p class="font-semibold">All recovery escalation partners ({{ $recoveryTypeCount }})</p>
                <p class="text-xs mt-1 text-sky-900/80">
                    Call Center, Debt Collector, Auctioneer, Legal, and GPS live here — SLA, commission, and markup for collection cases.
                    Insurance and Valuation are <strong>not</strong> in this list; they use the <button type="button" class="font-semibold underline" @click="tab = 'service'">Service rates</button> tab.
                </p>
            </div>

            <div class="space-y-4">
                @foreach ($types as $type => $meta)
                    @php
                        $vendorCategory = $meta['vendor_category'] ?? $type;
                    @endphp
                    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5">
                        <div class="flex flex-wrap items-start justify-between gap-2 mb-4">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $meta['label'] }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">Priority {{ $values['priority'][$type] ?? $meta['default_priority'] ?? '—' }} in the escalation chain</p>
                            </div>
                            <a href="{{ route('admin.partners.create', ['category' => $vendorCategory]) }}"
                               class="inline-flex text-xs font-semibold text-brand hover:underline">
                                Add partner →
                            </a>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Priority</label>
                                <input type="number" name="priority_{{ $type }}" min="1" max="99" step="1"
                                       value="{{ $values['priority'][$type] ?? $meta['default_priority'] ?? 99 }}"
                                       class="w-full rounded-lg border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Loan types</label>
                                <input type="text" name="loan_types_{{ $type }}"
                                       value="{{ $values['loan_types'][$type] ?? 'all' }}"
                                       placeholder="all"
                                       class="w-full rounded-lg border-gray-300 text-sm font-mono">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Collateral</label>
                                <select name="collateral_scope_{{ $type }}" class="w-full rounded-lg border-gray-300 text-sm">
                                    @foreach (['all' => 'All', 'secured' => 'Secured', 'unsecured' => 'Unsecured'] as $scope => $label)
                                        <option value="{{ $scope }}" @selected(($values['collateral_scope'][$type] ?? 'all') === $scope)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">SLA days</label>
                                <input type="number" name="sla_days_{{ $type }}" min="1" max="90"
                                       value="{{ $values['sla_days'][$type] ?? $meta['default_sla_days'] }}"
                                       class="w-full rounded-lg border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Fee type</label>
                                <select name="fee_type_{{ $type }}" class="w-full rounded-lg border-gray-300 text-sm">
                                    <option value="percentage" @selected(($values['fee_type'][$type] ?? 'percentage') === 'percentage')>%</option>
                                    <option value="fixed" @selected(($values['fee_type'][$type] ?? '') === 'fixed')>Fixed</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Commission %</label>
                                <input type="number" name="commission_percent_{{ $type }}" step="0.1" min="0" max="100"
                                       value="{{ $values['commission_percent'][$type] ?? $meta['default_commission_percent'] }}"
                                       class="w-full rounded-lg border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Fixed fee</label>
                                <input type="number" name="fixed_amount_{{ $type }}" step="1" min="0"
                                       value="{{ $values['fixed_amount'][$type] ?? '' }}"
                                       class="w-full rounded-lg border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Markup %</label>
                                <input type="number" name="markup_percent_{{ $type }}" step="0.1" min="0" max="100"
                                       value="{{ $values['markup_percent'][$type] ?? $meta['default_markup_percent'] }}"
                                       class="w-full rounded-lg border-gray-300 text-sm">
                            </div>
                            <div class="flex items-end pb-2">
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input type="hidden" name="auto_escalate_type_{{ $type }}" value="0">
                                    <input type="checkbox" name="auto_escalate_type_{{ $type }}" value="1"
                                           @checked((bool) ($values['auto_escalate_type'][$type] ?? true))
                                           class="rounded border-gray-300 text-brand">
                                    Auto escalate
                                </label>
                            </div>
                        </div>
                    </div>
                @endforeach
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
                    Insurance, GPS device pricing, and Valuation defaults. These are separate from recovery escalation SLAs.
                    GPS also appears under Recovery partners for tracking SLA/commission.
                </p>
            </div>

            @foreach ($partnerDefaults as $category => $row)
                @php
                    $mode = $row['pricing_mode'] ?? 'fixed';
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
                            <x-admin.input name="{{ $category }}_base_cost" label="Default base price (TZS)" type="number" step="1" min="0"
                                           :value="old($category.'_base_cost', $row['base_cost'] ?? 0)" required />
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">Markup %</label>
                            <input type="number" name="{{ $category }}_markup_percent" step="0.1" min="0" max="100"
                                   value="{{ $storedMarkup }}"
                                   x-bind:disabled="!hasMarkup"
                                   class="w-full rounded-lg border-gray-300 text-sm disabled:bg-gray-50 disabled:text-gray-400">
                            <input type="hidden" name="{{ $category }}_markup_percent" value="{{ $storedMarkup }}" x-bind:disabled="hasMarkup">
                            <p class="mt-1 text-[11px] text-gray-500" x-show="!hasMarkup" x-cloak>No markup applied.</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 sticky bottom-0 bg-gray-50/95 backdrop-blur py-3 border-t border-gray-200 -mx-1 px-1">
            <p class="text-xs text-gray-500">Saving stores every tab at once (hidden tabs are still submitted).</p>
            <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-5 py-2.5 rounded-lg shadow-sm">
                Save recovery policy
            </button>
        </div>
    </form>
</x-admin.layout>
