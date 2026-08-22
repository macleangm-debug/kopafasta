<x-admin.layout title="Underwriting" heading="Underwriting settings" subheading="Guarantor gates, document SLAs, and default interest tier generation">
    @include('admin.settings._tabs', ['active' => 'underwriting'])
    <x-admin.settings-editor
        action="{{ route('admin.settings.underwriting.save') }}"
        submit-label="Save underwriting settings"
        :tabs="[
            'guarantors' => 'Guarantors',
            'documents' => 'Documents',
            'interest' => 'Interest',
            'offer' => 'Offer',
            'collateral' => 'Collateral',
            'disbursement' => 'Disbursement',
        ]"
    >
        <x-admin.settings-panel id="guarantors">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-1">Guarantor workflow</h3>
                <p class="text-xs text-gray-500 mb-4">Controls when applications enter underwriting and how long guarantor invitations stay valid.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-admin.input name="guarantor_invitation_expiry_days" label="Guarantor invitation expiry (days)" type="number"
                                   :value="$values['guarantor_invitation_expiry_days'] ?? 14" required />
                    <x-admin.input name="awaiting_guarantor_deadline_days" label="Awaiting-guarantor application window (days)" type="number"
                                   :value="$values['awaiting_guarantor_deadline_days'] ?? 7" required />
                    <p class="text-xs text-gray-500 md:col-span-2 -mt-2">
                        After submit, applications held for guarantor completion close automatically if the guarantor does not finish within this window (default 7 days). Borrowers see the deadline on their loan profile and get in-app reminders.
                    </p>
                    <x-admin.input name="stage_sla_days" label="Underwriting stage SLA reminder (days)" type="number"
                                   :value="$values['stage_sla_days'] ?? 5" required />
                    <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2 md:col-span-2">
                        <input type="hidden" name="hold_applications_until_guarantor_approved" value="0">
                        <input type="checkbox" name="hold_applications_until_guarantor_approved" value="1"
                               @checked(! isset($values['hold_applications_until_guarantor_approved']) || ! empty($values['hold_applications_until_guarantor_approved']))
                               class="size-4 rounded border-gray-300 text-brand focus:ring-brand">
                        <span class="text-gray-800">Hold new applications in “awaiting guarantor” until guarantor approves (guarantor lockout)</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2 md:col-span-2">
                        <input type="hidden" name="block_acknowledge_without_guarantor" value="0">
                        <input type="checkbox" name="block_acknowledge_without_guarantor" value="1"
                               @checked(! isset($values['block_acknowledge_without_guarantor']) || ! empty($values['block_acknowledge_without_guarantor']))
                               class="size-4 rounded border-gray-300 text-brand focus:ring-brand">
                        <span class="text-gray-800">Block underwriting acknowledgement while guarantor is incomplete</span>
                    </label>
                </div>
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="documents">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-1">Document requests</h3>
                <p class="text-xs text-gray-500 mb-4">Default due date when underwriting requests additional documents without specifying a date.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-admin.input name="document_request_default_due_days" label="Default due date (days from request)" type="number"
                                   :value="$values['document_request_default_due_days'] ?? 7" required />
                </div>
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="interest">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-1">Interest tier defaults</h3>
                <p class="text-xs text-gray-500 mb-4">
                    Used when auto-generating amount bands on loan products. Per-product overrides remain on each product edit page.
                </p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-admin.input name="default_rate_tier_count" label="Default number of amount bands" type="number"
                                   :value="$values['default_rate_tier_count'] ?? 4" required />
                    <x-admin.input name="default_rate_discount_fraction" label="Rate discount on largest band (0–0.85)" type="number" step="0.01"
                                   :value="$values['default_rate_discount_fraction'] ?? 0.30" required />
                </div>
                <p class="mt-3 text-xs text-amber-800/90 rounded-lg bg-amber-50 ring-1 ring-amber-100 px-3 py-2">
                    Example: base rate 19% with discount 0.30 → lowest band ≈ 13.3% monthly.
                </p>
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="offer">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-1">Offer workflow (pilot)</h3>
                <p class="text-xs text-gray-500 mb-4">Keep the workflow simple initially. Enable advanced options when ready.</p>
                <div class="space-y-3">
                    <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2">
                        <input type="hidden" name="enable_counter_offers" value="0">
                        <input type="checkbox" name="enable_counter_offers" value="1"
                               @checked(! empty($values['enable_counter_offers']))
                               class="size-4 rounded border-gray-300 text-brand focus:ring-brand">
                        <span class="text-gray-800">Enable counter-offers (underwriter can recommend reduced amount)</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2">
                        <input type="hidden" name="enable_asset_backed_alternative" value="0">
                        <input type="checkbox" name="enable_asset_backed_alternative" value="1"
                               @checked(! empty($values['enable_asset_backed_alternative']))
                               class="size-4 rounded border-gray-300 text-brand focus:ring-brand">
                        <span class="text-gray-800">Enable asset-backed alternative (continuation on same application)</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2">
                        <input type="hidden" name="enable_automatic_rejection" value="0">
                        <input type="checkbox" name="enable_automatic_rejection" value="1"
                               @checked(! isset($values['enable_automatic_rejection']) || ! empty($values['enable_automatic_rejection']))
                               class="size-4 rounded border-gray-300 text-brand focus:ring-brand">
                        <span class="text-gray-800">Enable automatic rejection guidance when affordability fails</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2">
                        <input type="hidden" name="enable_capacity_auto_reject" value="0">
                        <input type="checkbox" name="enable_capacity_auto_reject" value="1"
                               @checked(! isset($values['enable_capacity_auto_reject']) || ! empty($values['enable_capacity_auto_reject']))
                               class="size-4 rounded border-gray-300 text-brand focus:ring-brand">
                        <span class="text-gray-800">Auto-park &amp; reject when repayment capacity fails (screening does not work these; committee owns the 12-hour window)</span>
                    </label>
                    <x-admin.input name="capacity_auto_reject_delay_hours" label="Capacity auto-reject delay (hours)" type="number"
                                   :value="$values['capacity_auto_reject_delay_hours'] ?? 12" required />
                    <p class="text-xs text-gray-500 md:col-span-2 -mt-2">
                        After submit, capacity-fail applications are marked “system sorted.” Credit committee can send the rejection early or keep the file in screening during this delay. Credit management does not work this queue. Default 12 hours.
                    </p>
                </div>
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="collateral">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-1">Collateral to secure a loan</h3>
                <p class="text-xs text-gray-500 mb-4">
                    Post-submit flow on the loan profile when screening asks for collateral. Product name and interest stay the same; application fee follows the asset-backed schedule.
                </p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-admin.input name="collateral_secure_decision_days" label="Decision window (days)" type="number"
                                   :value="$values['collateral_secure_decision_days'] ?? 3" required />
                    <x-admin.input name="insurance_expiry_buffer_months" label="Insurance must outlast tenure by (months)" type="number"
                                   :value="$values['insurance_expiry_buffer_months'] ?? 2" required />
                    <x-admin.input name="insurance_renewal_decision_days" label="Insurance renewal window (days)" type="number"
                                   :value="$values['insurance_renewal_decision_days'] ?? 5" required />
                    <x-admin.input name="collateral_secure_grace_days" label="Grace days after window (before close)" type="number"
                                   :value="$values['collateral_secure_grace_days'] ?? 3" required />
                    <x-admin.input name="collateral_insurance_rate_percent" label="Comprehensive rate (% of insured value)" type="number" step="0.1"
                                   :value="$values['collateral_insurance_rate_percent'] ?? 3.5" required />
                    <x-admin.input name="collateral_insurance_markup_percent" label="Kopafasta markup on premium (%)" type="number" step="0.1"
                                   :value="$values['collateral_insurance_markup_percent'] ?? 0" required />
                </div>
                <p class="text-xs text-gray-500 mt-3">
                    Premium = insured value × rate, then optional markup.
                    Primary place to manage insurance / GPS / valuer defaults (with Add partner links) is
                    <a href="{{ route('admin.settings.recovery') }}" class="font-semibold text-brand underline">Recovery policy → Service partner default rates</a>.
                </p>
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="disbursement">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-1">Disbursement SLA</h3>
                <p class="text-xs text-gray-500 mb-4">
                    Clock starts when the borrower accepts the loan contract. Credit management owns the release queue.
                    Hours/days use <a href="{{ route('admin.settings.working-hours') }}" class="font-semibold text-brand underline">Working hours</a>
                    (default Mon–Fri 08:00–17:00) and skip public holidays.
                </p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-admin.input name="disbursement_sla_working_days" label="Standard disbursement SLA (working days)" type="number"
                                   :value="$values['disbursement_sla_working_days'] ?? 2" required />
                    <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2 md:col-span-2">
                        <input type="hidden" name="enable_disbursement_fast_track" value="0">
                        <input type="checkbox" name="enable_disbursement_fast_track" value="1"
                               @checked(! empty($values['enable_disbursement_fast_track']))
                               class="size-4 rounded border-gray-300 text-brand focus:ring-brand">
                        <span class="text-gray-800">Offer paid fast-track disbursement on post-approval fees (after offer acceptance)</span>
                    </label>
                    <x-admin.input name="disbursement_fast_track_business_hours" label="Fast-track window (working hours)" type="number"
                                   :value="$values['disbursement_fast_track_business_hours'] ?? 12" required />
                    <x-admin.input name="disbursement_fast_track_fee_amount" label="Fast-track fee amount (TZS)" type="number" step="1"
                                   :value="$values['disbursement_fast_track_fee_amount'] ?? 25000" required />
                </div>
                <p class="text-xs text-gray-500 mt-3">
                    When fast-track is off, borrowers only see the standard SLA. When on, an optional fee appears on
                    <strong>Post-approval fees</strong> after they accept the offer letter. Leave off until you are ready to sell rush disbursement.
                </p>
            </div>
        </x-admin.settings-panel>
    </x-admin.settings-editor>
</x-admin.layout>
