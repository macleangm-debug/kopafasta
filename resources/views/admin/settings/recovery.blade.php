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
            <p class="text-xs text-gray-500 mt-3">Industry timeline: 2-day grace → Call Center (7) → Debt Collector incl. repossession (10) → Auctioneer (11) = 30 days max. Escalation: Call Center → Debt Collector → Auctioneer → Legal.</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-4">Partner SLAs &amp; rates</h3>
            <p class="text-xs text-gray-500 mb-4">Each partner supports percentage or fixed recovery fee plus company markup. Fixed example: 10,000 fee + 10% markup → borrower pays 11,000.</p>
            <div class="space-y-4">
                @foreach ($types as $type => $meta)
                    <div class="rounded-lg border border-gray-200 p-4">
                        <p class="text-sm font-semibold text-gray-900 mb-3">{{ $meta['label'] }}</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                            <x-admin.input :name="'sla_days_'.$type" label="SLA (days)" type="number" min="1" max="90"
                                           :value="$values['sla_days'][$type] ?? $meta['default_sla_days']" />
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Fee type</label>
                                <select name="fee_type_{{ $type }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                    <option value="percentage" @selected(($values['fee_type'][$type] ?? 'percentage') === 'percentage')>Percentage</option>
                                    <option value="fixed" @selected(($values['fee_type'][$type] ?? '') === 'fixed')>Fixed amount</option>
                                </select>
                            </div>
                            <x-admin.input :name="'commission_percent_'.$type" label="Commission %" type="number" step="0.1" min="0" max="100"
                                           :value="$values['commission_percent'][$type] ?? $meta['default_commission_percent']" />
                            <x-admin.input :name="'fixed_amount_'.$type" label="Fixed fee" type="number" step="1" min="0"
                                           :value="$values['fixed_amount'][$type] ?? ''" />
                            <x-admin.input :name="'markup_percent_'.$type" label="Company markup %" type="number" step="0.1" min="0" max="100"
                                           :value="$values['markup_percent'][$type] ?? $meta['default_markup_percent']" />
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-5 py-2 rounded-lg shadow-sm">
                Save recovery policy
            </button>
        </div>
    </form>
</x-admin.layout>
