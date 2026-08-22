<x-admin.layout title="Offer Settings" heading="Offer settings" subheading="Pilot acceptance flow and repayment commencement defaults">
    @include('admin.settings._tabs', ['active' => 'offer'])
    <x-admin.settings-editor
        action="{{ route('admin.settings.offer.save') }}"
        submit-label="Save offer settings"
        :tabs="[
            'acceptance' => 'Acceptance',
            'repayment' => 'Repayment',
        ]"
    >
        <x-admin.settings-panel id="acceptance">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-1">Offer acceptance</h3>
                <p class="text-xs text-gray-500 mb-4">
                    For the pilot, keep PIN confirmation disabled until you are ready.
                    When disabled, borrowers accept with one click — no PIN required.
                </p>
                <div class="space-y-3">
                    <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2">
                        <input type="hidden" name="require_offer_acceptance_code" value="0">
                        <input type="checkbox" name="require_offer_acceptance_code" value="1"
                               @checked(! empty($values['require_offer_acceptance_code']))
                               class="size-4 rounded border-gray-300 text-brand focus:ring-brand">
                        <span class="text-gray-800">Require PIN to accept the offer letter</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2">
                        <input type="hidden" name="require_contract_acceptance_code" value="0">
                        <input type="checkbox" name="require_contract_acceptance_code" value="1"
                               @checked(! empty($values['require_contract_acceptance_code']))
                               class="size-4 rounded border-gray-300 text-brand focus:ring-brand">
                        <span class="text-gray-800">Require PIN to accept the loan agreement</span>
                    </label>
                </div>
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="repayment">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-1">Repayment commencement</h3>
                <p class="text-xs text-gray-500 mb-4">
                    Used on pre-disbursement contracts and when generating the actual schedule after disbursement.
                    First instalment falls due this many days after the disbursement date.
                </p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-admin.input name="repayment_commencement_days" label="Days after disbursement" type="number" min="0" max="90"
                                   :value="$values['repayment_commencement_days'] ?? 7" required />
                </div>
            </div>
        </x-admin.settings-panel>
    </x-admin.settings-editor>
</x-admin.layout>
