<x-admin.layout title="Country Settings" heading="Country Settings" subheading="Language, currency, ID format, phone prefix, grace rules, and loan policies per country">
    @include('admin.settings._tabs', ['active' => 'countries'])

    <div class="mb-6 flex flex-wrap gap-2">
        @foreach ($countries as $row)
            <a href="{{ route('admin.settings.countries', ['country' => $row['code']]) }}"
               class="px-3 py-1.5 rounded-md text-sm font-medium transition {{ ($selected['code'] ?? '') === $row['code'] ? 'bg-brand-gold text-brand' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">
                {{ $row['emoji'] }} {{ $row['name'] }}
            </a>
        @endforeach
    </div>

    <x-admin.settings-editor
        action="{{ route('admin.settings.countries.save', $selected['code']) }}"
        submit-label="Save {{ $selected['name'] }} settings"
        :tabs="[
            'profile' => 'Profile',
            'policies' => 'Policies',
        ]"
    >
        <x-admin.settings-panel id="profile">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">{{ $selected['name'] }} ({{ $selected['code'] }})</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2 md:col-span-3">
                        <input type="hidden" name="active" value="0">
                        <input type="checkbox" name="active" value="1" @checked($selected['active']) class="rounded border-gray-300 text-brand">
                        <span>Operational (borrowers can register)</span>
                    </label>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Default language</label>
                        <select name="language" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="en" @selected($selected['language'] === 'en')>English</option>
                            <option value="sw" @selected($selected['language'] === 'sw')>Swahili</option>
                        </select>
                    </div>
                    <x-admin.input name="currency" label="Currency (ISO 4217)" :value="$selected['currency']" required maxlength="3" />
                    <x-admin.input name="timezone" label="Timezone" :value="$selected['timezone']" required />
                    <x-admin.input name="phone_prefix" label="Phone prefix" :value="$selected['phone_prefix']" required />
                    <x-admin.input name="national_id_label" label="National ID label" :value="$selected['national_id_label']" required />
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">National ID format</label>
                        <select name="national_id_format" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            @foreach (['nida_20' => 'NIDA (20 digits)', 'digits_8' => '8 digits', 'digits_16' => '16 digits', 'alphanumeric' => 'Alphanumeric'] as $val => $label)
                                <option value="{{ $val }}" @selected($selected['national_id_format'] === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-admin.input name="grace_period_days" label="Grace period (days)" type="number" min="0" max="60" :value="$selected['grace_period_days']" required />
                    <x-admin.input name="repayment_ratio_pct" label="Max repayment ratio (%)" type="number" step="0.01" :value="$selected['repayment_ratio_pct']" required />
                    <x-admin.input name="crb_freshness_days" label="CRB freshness (days)" type="number" :value="$selected['crb_freshness_days']" required />
                    <x-admin.input name="kyc_freshness_days" label="KYC freshness (days)" type="number" :value="$selected['kyc_freshness_days']" required />
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contract language</label>
                        <select name="contract_locale" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="en" @selected($selected['contract_locale'] === 'en')>English</option>
                            <option value="sw" @selected($selected['contract_locale'] === 'sw')>Swahili</option>
                        </select>
                    </div>
                    <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2 md:col-span-2">
                        <input type="hidden" name="guarantor_required" value="0">
                        <input type="checkbox" name="guarantor_required" value="1" @checked($selected['guarantor_required']) class="rounded border-gray-300 text-brand">
                        <span>Guarantor required by default</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2 md:col-span-2">
                        <input type="hidden" name="borrower_membership_allowed" value="0">
                        <input type="checkbox" name="borrower_membership_allowed" value="1" @checked($selected['borrower_membership_allowed'] ?? false) class="rounded border-gray-300 text-brand">
                        <span>Borrower membership allowed</span>
                    </label>
                    <p class="text-xs text-gray-500 md:col-span-2 -mt-2">Master switch for compulsory borrower membership in this country. Fee amount alone does not enable it. Tanzania: keep OFF.</p>
                </div>
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="policies">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Loan policies &amp; contracts</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Loan policy notes</label>
                        <textarea name="loan_policy_notes" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ $selected['loan_policy_notes'] ?? '' }}</textarea>
                        <p class="text-xs text-gray-500 mt-1">Internal notes for underwriters operating in this country.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contract template override (optional)</label>
                        <input type="text" name="contract_template" value="{{ $selected['contract_template'] ?? '' }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="document_templates.code or path">
                    </div>
                </div>
            </div>
        </x-admin.settings-panel>
    </x-admin.settings-editor>
</x-admin.layout>
