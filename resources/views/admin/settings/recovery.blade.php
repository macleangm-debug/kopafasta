<x-admin.layout title="Recovery Policy" heading="Recovery Policy" subheading="Grace period, partner SLAs, commissions, and auto-escalation">
    @include('admin.settings._tabs', ['active' => 'recovery'])

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.settings.recovery.save') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-4">Timeline</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-admin.input name="grace_period_days" label="Grace period (days, from loan product)" type="number" min="1" max="60"
                               :value="$values['grace_period_days'] ?? 2" required />
                <x-admin.input name="call_center_lead_days" label="Call center lead (days before grace ends)" type="number" min="0" max="30"
                               :value="$values['call_center_lead_days'] ?? 0" required />
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Calculate recovery fees from</label>
                    <div class="flex flex-wrap gap-4 text-sm">
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" name="fee_base" value="principal" @checked(($values['fee_base'] ?? 'principal') === 'principal') class="text-amber-600">
                            Principal only
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" name="fee_base" value="outstanding" @checked(($values['fee_base'] ?? '') === 'outstanding') class="text-amber-600">
                            Outstanding balance
                        </label>
                    </div>
                </div>
                <div class="flex items-center gap-2 pt-6">
                    <input type="hidden" name="auto_assign_call_center" value="0">
                    <input type="checkbox" name="auto_assign_call_center" value="1" id="auto_assign_call_center"
                           @checked((bool) ($values['auto_assign_call_center'] ?? true))
                           class="rounded border-gray-300 text-amber-600">
                    <label for="auto_assign_call_center" class="text-sm text-gray-700">Auto-assign call center when grace threshold is reached</label>
                </div>
                <div class="flex items-center gap-2 pt-6">
                    <input type="hidden" name="auto_escalate" value="0">
                    <input type="checkbox" name="auto_escalate" value="1" id="auto_escalate"
                           @checked((bool) ($values['auto_escalate'] ?? true))
                           class="rounded border-gray-300 text-amber-600">
                    <label for="auto_escalate" class="text-sm text-gray-700">Auto escalate when partner SLA expires</label>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-3">Grace uses each loan product’s default grace days (this setting is the platform fallback). Partner SLA days below are applied when a recovery assignment is created — due dates drive auto-escalation when enabled.</p>
            <p class="text-xs text-gray-500 mt-1">Typical path: grace → Call Center → Debt Collector (incl. repossession) → Auctioneer → Legal. GPS partners use their own SLA for tracking tasks.</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Repossession charges by asset type</h3>
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
                                   class="rounded border-gray-300 text-amber-600">
                            Manual quote
                        </label>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Partner SLA matrix</h3>
            <p class="text-xs text-gray-500 mb-4">Configure priority, loan scope, collateral scope, SLA, commission, and per-stage auto-escalation. Loan types: <code class="text-[11px]">all</code> or comma-separated product codes (e.g. <code class="text-[11px]">GL,IL,AB</code>).</p>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gradient-to-r from-brand via-brand to-brand-light text-left text-[11px] uppercase tracking-wide text-white/90">
                        <tr>
                            <th class="px-3 py-2.5 font-semibold">Partner</th>
                            <th class="px-3 py-2.5 font-semibold">Priority</th>
                            <th class="px-3 py-2.5 font-semibold">Loan types</th>
                            <th class="px-3 py-2.5 font-semibold">Collateral</th>
                            <th class="px-3 py-2.5 font-semibold">SLA days</th>
                            <th class="px-3 py-2.5 font-semibold">Fee type</th>
                            <th class="px-3 py-2.5 font-semibold">Commission %</th>
                            <th class="px-3 py-2.5 font-semibold">Fixed fee</th>
                            <th class="px-3 py-2.5 font-semibold">Markup %</th>
                            <th class="px-3 py-2.5 font-semibold">Auto escalate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($types as $type => $meta)
                            <tr>
                                <td class="px-3 py-3 font-semibold text-gray-900 whitespace-nowrap">{{ $meta['label'] }}</td>
                                <td class="px-3 py-3">
                                    <input type="number" name="priority_{{ $type }}" min="1" max="99" step="1"
                                           value="{{ $values['priority'][$type] ?? $meta['default_priority'] ?? 99 }}"
                                           class="w-16 rounded-lg border-gray-300 text-sm">
                                </td>
                                <td class="px-3 py-3">
                                    <input type="text" name="loan_types_{{ $type }}"
                                           value="{{ $values['loan_types'][$type] ?? 'all' }}"
                                           placeholder="all"
                                           class="w-28 rounded-lg border-gray-300 text-sm font-mono">
                                </td>
                                <td class="px-3 py-3">
                                    <select name="collateral_scope_{{ $type }}" class="rounded-lg border-gray-300 text-sm">
                                        @foreach (['all' => 'All', 'secured' => 'Secured', 'unsecured' => 'Unsecured'] as $scope => $label)
                                            <option value="{{ $scope }}" @selected(($values['collateral_scope'][$type] ?? 'all') === $scope)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-3 py-3">
                                    <input type="number" name="sla_days_{{ $type }}" min="1" max="90"
                                           value="{{ $values['sla_days'][$type] ?? $meta['default_sla_days'] }}"
                                           class="w-16 rounded-lg border-gray-300 text-sm">
                                </td>
                                <td class="px-3 py-3">
                                    <select name="fee_type_{{ $type }}" class="rounded-lg border-gray-300 text-sm">
                                        <option value="percentage" @selected(($values['fee_type'][$type] ?? 'percentage') === 'percentage')>%</option>
                                        <option value="fixed" @selected(($values['fee_type'][$type] ?? '') === 'fixed')>Fixed</option>
                                    </select>
                                </td>
                                <td class="px-3 py-3">
                                    <input type="number" name="commission_percent_{{ $type }}" step="0.1" min="0" max="100"
                                           value="{{ $values['commission_percent'][$type] ?? $meta['default_commission_percent'] }}"
                                           class="w-20 rounded-lg border-gray-300 text-sm">
                                </td>
                                <td class="px-3 py-3">
                                    <input type="number" name="fixed_amount_{{ $type }}" step="1" min="0"
                                           value="{{ $values['fixed_amount'][$type] ?? '' }}"
                                           class="w-24 rounded-lg border-gray-300 text-sm">
                                </td>
                                <td class="px-3 py-3">
                                    <input type="number" name="markup_percent_{{ $type }}" step="0.1" min="0" max="100"
                                           value="{{ $values['markup_percent'][$type] ?? $meta['default_markup_percent'] }}"
                                           class="w-20 rounded-lg border-gray-300 text-sm">
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <input type="hidden" name="auto_escalate_type_{{ $type }}" value="0">
                                    <input type="checkbox" name="auto_escalate_type_{{ $type }}" value="1"
                                           @checked((bool) ($values['auto_escalate_type'][$type] ?? true))
                                           class="rounded border-gray-300 text-amber-600">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-5 py-2 rounded-lg shadow-sm">
                Save recovery policy
            </button>
        </div>
    </form>
</x-admin.layout>
