<x-admin.layout title="Affiliate Settings" heading="Affiliate Settings" subheading="Promo code rules, defaults, and where affiliate discounts apply">
    @include('admin.settings._tabs', ['active' => 'affiliates'])

    <x-admin.settings-editor
        action="{{ route('admin.settings.affiliates.save') }}"
        submit-label="Save affiliate settings"
        :tabs="[
            'defaults' => 'Defaults',
            'commission' => 'Commission',
            'promo' => 'Promo codes',
            'membership' => 'Membership',
            'premium' => 'Premium',
            'attribution' => 'Attribution',
            'messages' => 'Messages',
            'evaluation' => 'Evaluation',
            'terms' => 'Terms',
            'fraud' => 'Fraud',
        ]"
    >
        <x-admin.settings-panel id="defaults">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Defaults</h3>
                    <p class="text-xs text-gray-500 mt-1">Fallback promo prefix, commission, discounts, and the smallest payout you will send.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-admin.input name="code_prefix" label="Code prefix (fallback)" :value="$values['code_prefix'] ?? 'KPA'" required />
                    <x-admin.input name="default_commission_percent" label="Default commission (%)" type="number" step="0.1" min="0" max="100"
                                   :value="$values['default_commission_percent'] ?? 10" required />
                    <x-admin.input name="default_registration_discount_percent" label="Default registration discount (%)" type="number" step="0.1" min="0" max="100"
                                   :value="$values['default_registration_discount_percent'] ?? 10" required />
                    <x-admin.input name="default_application_discount_percent" label="Default application discount (%)" type="number" step="0.1" min="0" max="100"
                                   :value="$values['default_application_discount_percent'] ?? 10" required />
                    <x-admin.input name="default_plus_discount_percent" label="Default Kopafasta Plus customer discount (%)" type="number" step="0.1" min="0" max="100"
                                   :value="$values['default_plus_discount_percent'] ?? 10" />
                    <x-admin.input name="minimum_payout_amount" label="Minimum payout amount (TZS)" type="number" step="1000" min="0"
                                   :value="$values['minimum_payout_amount'] ?? 50000" required money />
                </div>
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="commission">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4" x-data="{ mode: @js($values['commission_mode'] ?? 'percentage') }">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">How affiliates earn commission</h3>
                    <p class="text-xs text-gray-500 mt-1">Pick one mode. Only the fields for that mode are shown below.</p>
                </div>

                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm">
                    @foreach ([
                        'percentage' => ['Percentage', 'Simple % of the fee'],
                        'fixed' => ['Fixed amount', 'Same TZS amount each time'],
                        'tiered' => ['Tiered by volume', 'More referrals → higher pay'],
                        'hybrid' => ['Hybrid', 'Fixed TZS + a %'],
                    ] as $mode => [$label, $hint])
                        <label class="rounded-xl ring-1 px-3 py-3 cursor-pointer transition"
                               :class="mode === '{{ $mode }}' ? 'ring-brand bg-brand-muted/40' : 'ring-gray-200 hover:bg-gray-50'">
                            <input type="radio" name="commission_mode" value="{{ $mode }}" x-model="mode" class="sr-only">
                            <span class="font-semibold text-gray-900 block">{{ $label }}</span>
                            <span class="text-xs text-gray-500">{{ $hint }}</span>
                        </label>
                    @endforeach
                </div>

                <div x-show="mode === 'percentage'" x-cloak class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-700">
                    Uses <strong>Default commission (%)</strong> on the Defaults tab. No extra fields needed.
                </div>

                <div x-show="mode === 'hybrid'" x-cloak class="space-y-3">
                    <h4 class="text-xs font-semibold text-gray-700 uppercase">Hybrid amounts</h4>
                    <div class="grid md:grid-cols-2 gap-4">
                        <x-admin.input name="hybrid_fixed_amount" label="Fixed part (TZS)" type="number" step="1" min="0"
                                       :value="$values['hybrid_fixed_amount'] ?? 0" money />
                        <x-admin.input name="hybrid_percent" label="Percent part (%)" type="number" step="0.1" min="0" max="100"
                                       :value="$values['hybrid_percent'] ?? 0" />
                    </div>
                </div>

                <div x-show="mode === 'fixed'" x-cloak class="space-y-3">
                    <h4 class="text-xs font-semibold text-gray-700 uppercase">Fixed commission amounts (TZS)</h4>
                    <p class="text-xs text-gray-500">Paid per successful fee event of that type. “Default” is the fallback when a fee type has no amount.</p>
                    <div class="grid md:grid-cols-2 gap-4">
                        <x-admin.input name="fixed_commission_default" label="Default" type="number" step="1" min="0"
                                       :value="$values['fixed_commission_amounts']['default'] ?? 0" money />
                        <x-admin.input name="fixed_commission_registration_fee" label="Membership fee" type="number" step="1" min="0"
                                       :value="$values['fixed_commission_amounts']['registration_fee'] ?? 0" money />
                        <x-admin.input name="fixed_commission_application_fee" label="Application fee" type="number" step="1" min="0"
                                       :value="$values['fixed_commission_amounts']['application_fee'] ?? 0" money />
                        <x-admin.input name="fixed_commission_post_approval_fee" label="Post approval fee" type="number" step="1" min="0"
                                       :value="$values['fixed_commission_amounts']['post_approval_fee'] ?? 0" money />
                    </div>
                </div>

                <div x-show="mode === 'tiered'" x-cloak class="space-y-3">
                    <h4 class="text-xs font-semibold text-gray-700 uppercase">Volume tiers</h4>
                    <p class="text-xs text-gray-500">Based on how many registrations/applications the affiliate has driven. Tier 1 is beginners; leave Max blank for unlimited.</p>
                    @php $tiers = $values['commission_tiers'] ?? []; @endphp
                    <div class="space-y-3">
                        @foreach (range(0, 2) as $i)
                            @php $tier = $tiers[$i] ?? ['min_count' => '', 'max_count' => '', 'type' => 'fixed', 'amount' => '']; @endphp
                            <div class="rounded-lg ring-1 ring-gray-200 p-3 grid md:grid-cols-4 gap-3 items-end">
                                <x-admin.input name="commission_tiers[{{ $i }}][min_count]" label="From (count)" type="number" min="0"
                                               :value="$tier['min_count'] ?? ''" />
                                <x-admin.input name="commission_tiers[{{ $i }}][max_count]" label="To (blank = ∞)" type="number" min="0"
                                               :value="$tier['max_count'] ?? ''" />
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Pay as</label>
                                    <select name="commission_tiers[{{ $i }}][type]" class="w-full rounded-lg border-gray-300 text-sm">
                                        <option value="fixed" @selected(($tier['type'] ?? 'fixed') === 'fixed')>Fixed (TZS)</option>
                                        <option value="percentage" @selected(($tier['type'] ?? '') === 'percentage')>Percentage</option>
                                    </select>
                                </div>
                                <x-admin.input name="commission_tiers[{{ $i }}][amount]" label="Amount" type="number" step="0.01" min="0"
                                               :value="$tier['amount'] ?? ''" />
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Keep unused mode fields in the form so saving another mode does not wipe stored values --}}
                <template x-if="mode !== 'hybrid'">
                    <div class="hidden">
                        <input type="hidden" name="hybrid_fixed_amount" value="{{ $values['hybrid_fixed_amount'] ?? 0 }}">
                        <input type="hidden" name="hybrid_percent" value="{{ $values['hybrid_percent'] ?? 0 }}">
                    </div>
                </template>
                <template x-if="mode !== 'fixed'">
                    <div class="hidden">
                        <input type="hidden" name="fixed_commission_default" value="{{ $values['fixed_commission_amounts']['default'] ?? 0 }}">
                        <input type="hidden" name="fixed_commission_registration_fee" value="{{ $values['fixed_commission_amounts']['registration_fee'] ?? 0 }}">
                        <input type="hidden" name="fixed_commission_application_fee" value="{{ $values['fixed_commission_amounts']['application_fee'] ?? 0 }}">
                        <input type="hidden" name="fixed_commission_post_approval_fee" value="{{ $values['fixed_commission_amounts']['post_approval_fee'] ?? 0 }}">
                    </div>
                </template>
                <template x-if="mode !== 'tiered'">
                    <div class="hidden">
                        @php $tiersHidden = $values['commission_tiers'] ?? []; @endphp
                        @foreach (range(0, 2) as $i)
                            @php $tier = $tiersHidden[$i] ?? ['min_count' => '', 'max_count' => '', 'type' => 'fixed', 'amount' => '']; @endphp
                            <input type="hidden" name="commission_tiers[{{ $i }}][min_count]" value="{{ $tier['min_count'] ?? '' }}">
                            <input type="hidden" name="commission_tiers[{{ $i }}][max_count]" value="{{ $tier['max_count'] ?? '' }}">
                            <input type="hidden" name="commission_tiers[{{ $i }}][type]" value="{{ $tier['type'] ?? 'fixed' }}">
                            <input type="hidden" name="commission_tiers[{{ $i }}][amount]" value="{{ $tier['amount'] ?? '' }}">
                        @endforeach
                    </div>
                </template>
            </div>

            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-3">
                <h3 class="text-sm font-semibold text-gray-900">Commission calculation base</h3>
                <div class="flex flex-wrap gap-4 text-sm">
                    <label class="inline-flex items-center gap-2">
                        <input type="radio" name="commission_calculation_base" value="discounted_amount"
                               @checked(($values['commission_calculation_base'] ?? 'discounted_amount') === 'discounted_amount')
                               class="text-brand">
                        Discounted amount (recommended)
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input type="radio" name="commission_calculation_base" value="original_amount"
                               @checked(($values['commission_calculation_base'] ?? '') === 'original_amount')
                               class="text-brand">
                        Original amount
                    </label>
                </div>
                <p class="text-xs text-gray-500">Example: 10,000 fee with 10% discount → paid 9,000. At 10% commission on discounted base = 900.</p>
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="promo">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Affiliate promo-code editing</h3>
                    <p class="text-xs text-gray-500">Affiliates may change their public code subject to these rules. Attribution stays on the Affiliate ID, not the string.</p>
                </div>
                @php $promo = $values['promo_code'] ?? []; @endphp
                <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input type="hidden" name="promo_affiliate_can_edit" value="0">
                    <input type="checkbox" name="promo_affiliate_can_edit" value="1"
                           @checked((bool) ($promo['affiliate_can_edit'] ?? true))
                           class="rounded border-gray-300 text-brand">
                    Affiliates may edit their promo code
                </label>
                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <x-admin.input name="promo_min_length" label="Minimum length" type="number" min="2"
                                   :value="$promo['min_length'] ?? 3" />
                    <x-admin.input name="promo_max_length" label="Maximum length" type="number" min="3"
                                   :value="$promo['max_length'] ?? 24" />
                    <x-admin.input name="promo_change_cooldown_days" label="Change cooldown (days)" type="number" min="0"
                                   :value="$promo['change_cooldown_days'] ?? 30" />
                    <x-admin.input name="promo_old_code_grace_days" label="Old-code alias grace (days)" type="number" min="0"
                                   :value="$promo['old_code_grace_days'] ?? 14" />
                </div>
                <x-admin.input name="promo_allowed_pattern" label="Allowed characters (character class)"
                               :value="$promo['allowed_pattern'] ?? 'A-Z0-9_-'" />
                <x-admin.textarea name="promo_reserved" label="Reserved / prohibited codes (comma-separated)" rows="2"
                                  :value="implode(', ', $promo['reserved'] ?? [])" />
            </div>
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Apply affiliate promo codes to</h3>
                    <p class="text-xs text-gray-500">Choose which fee types accept affiliate discounts and accrue commission.</p>
                </div>
                @php
                    $feeLabels = [
                        'application_fee'   => 'Application fee (launch)',
                        'kopafasta_plus'    => 'Kopafasta Plus (launch)',
                        'registration_fee'  => 'Borrower membership fee (off)',
                        'valuation_fee'     => 'Valuation fee (off)',
                        'gps_fee'           => 'GPS fee (off)',
                        'post_approval_fee' => 'Other post-approval fees (off)',
                        'interest'          => 'Interest',
                        'repayments'        => 'Repayments',
                    ];
                @endphp
                <div class="grid sm:grid-cols-2 gap-3">
                    @foreach ($feeLabels as $key => $label)
                        <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                            <input type="hidden" name="applies_to[{{ $key }}]" value="0">
                            <input type="checkbox" name="applies_to[{{ $key }}]" value="1"
                                   @checked((bool) ($values['applies_to'][$key] ?? false))
                                   class="rounded border-gray-300 text-brand">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="membership">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Affiliate application fee</h3>
                    <p class="text-xs text-gray-500 mt-1">Paid through payment.show before the application enters Admin review. Separate from annual membership. Snapshotted at payment creation.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-admin.input name="application_fee_amount" label="Application fee (TZS)" type="number" step="1000" min="0"
                                   :value="$values['application_fee_amount'] ?? 10000" money />
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Affiliate membership</h3>
                    <p class="text-xs text-gray-500 mt-1">Annual fee paid through the standard payment gate before affiliates can share. Individuals pay {{ format_money(25000) }}; companies pay {{ format_money(50000) }} (defaults). Tick the checkbox to require the fee.</p>
                </div>
                @php $membership = $values['membership'] ?? []; @endphp
                <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input type="hidden" name="membership_enabled" value="0">
                    <input type="checkbox" name="membership_enabled" value="1"
                           @checked((bool) ($membership['enabled'] ?? true))
                           class="rounded border-gray-300 text-brand">
                    Require affiliate membership
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input type="hidden" name="membership_required_before_sharing" value="0">
                    <input type="checkbox" name="membership_required_before_sharing" value="1"
                           @checked((bool) ($membership['required_before_sharing'] ?? true))
                           class="rounded border-gray-300 text-brand">
                    Block sharing until membership is paid
                </label>
                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <x-admin.input name="membership_fee_amount_individual" label="Individual annual fee (TZS)" type="number" step="1000" min="0"
                                   :value="$membership['fee_amount_individual'] ?? 25000" money />
                    <x-admin.input name="membership_fee_amount_company" label="Company annual fee (TZS)" type="number" step="1000" min="0"
                                   :value="$membership['fee_amount_company'] ?? ($membership['fee_amount'] ?? 50000)" money />
                    <x-admin.input name="membership_duration_days" label="Duration (days)" type="number" min="1"
                                   :value="$membership['duration_days'] ?? 365" />
                    <x-admin.input name="membership_grace_period_hours" label="Pay-within window (hours)" type="number" min="1"
                                   :value="$membership['grace_period_hours'] ?? 48" />
                    <x-admin.input name="membership_renewal_window_days" label="Renewal window (days before expiry)" type="number" min="1"
                                   :value="$membership['renewal_window_days'] ?? 30" />
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input type="hidden" name="membership_require_terms" value="0">
                    <input type="checkbox" name="membership_require_terms" value="1"
                           @checked((bool) ($membership['require_terms_before_activation'] ?? true))
                           class="rounded border-gray-300 text-brand">
                    Require Affiliate Terms before first membership payment
                </label>
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Promo code when membership expires</label>
                        <select name="membership_promo_code_on_expiry" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                            <option value="disable" @selected(($membership['promo_code_on_expiry'] ?? 'disable') === 'disable')>Disable new qualifying referrals</option>
                            <option value="keep" @selected(($membership['promo_code_on_expiry'] ?? '') === 'keep')>Keep operational (not recommended)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Commission after expiry</label>
                        <select name="membership_commission_after_expiry" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                            <option value="historical_only" @selected(($membership['commission_after_expiry'] ?? 'historical_only') === 'historical_only')>Preserve history; no new commission</option>
                            <option value="continue" @selected(($membership['commission_after_expiry'] ?? '') === 'continue')>Continue accruing</option>
                        </select>
                    </div>
                </div>
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="premium">
            @php $premium = $values['premium'] ?? []; @endphp
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Premium Affiliate</h3>
                    <p class="text-xs text-gray-500 mt-1">Premium uses a fixed agreement instead of annual membership unless you explicitly require a fee. Duration is Settings-owned and must not be hard-coded in the portal.</p>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input type="hidden" name="premium_membership_required" value="0">
                    <input type="checkbox" name="premium_membership_required" value="1"
                           @checked((bool) ($premium['membership_required'] ?? false))
                           class="rounded border-gray-300 text-brand">
                    Require annual membership fee for Premium Affiliates
                </label>
                <div class="grid md:grid-cols-3 gap-4">
                    <x-admin.input name="premium_contract_duration_months" label="Premium contract duration (months)" type="number" min="1"
                                   :value="$premium['contract_duration_months'] ?? 24" />
                    <x-admin.input name="premium_renewal_window_days" label="Renewal window (days before expiry)" type="number" min="1"
                                   :value="$premium['renewal_window_days'] ?? 30" />
                    <x-admin.input name="premium_badge_label" label="Premium badge label"
                                   :value="$premium['badge_label'] ?? 'Premium'" />
                </div>
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="attribution">
            @php $attribution = $values['attribution'] ?? []; @endphp
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Referral attribution</h3>
                    <p class="text-xs text-gray-500 mt-1">The referral link captures the Affiliate at the first eligible touchpoint. Commission still fires only at the configured qualifying fee event. The window governs anonymous/pre-application claims; locked application attribution does not expire with the cookie.</p>
                </div>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <x-admin.input name="attribution_window_days" label="Attribution window (days)" type="number" min="1"
                                   :value="$attribution['window_days'] ?? 30" />
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Attribution model</label>
                        <select name="attribution_model" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                            <option value="first_valid" @selected(($attribution['model'] ?? 'first_valid') === 'first_valid')>First valid Affiliate</option>
                            <option value="last_click" @selected(($attribution['model'] ?? '') === 'last_click')>Last click (not recommended)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Lock attribution at</label>
                        <select name="attribution_lock_at" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                            <option value="application_created" @selected(($attribution['lock_at'] ?? 'application_created') === 'application_created')>Application created</option>
                            <option value="registration" @selected(($attribution['lock_at'] ?? '') === 'registration')>Registration</option>
                        </select>
                    </div>
                </div>
                <div class="grid sm:grid-cols-2 gap-3 text-sm">
                    <label class="inline-flex items-center gap-2">
                        <input type="hidden" name="attribution_auto_apply_promo" value="0">
                        <input type="checkbox" name="attribution_auto_apply_promo" value="1"
                               @checked((bool) ($attribution['auto_apply_promo'] ?? true))
                               class="rounded border-gray-300 text-brand">
                        Auto-apply promo while attribution is valid
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input type="hidden" name="attribution_cookie_enabled" value="0">
                        <input type="checkbox" name="attribution_cookie_enabled" value="1"
                               @checked((bool) ($attribution['cookie_enabled'] ?? true))
                               class="rounded border-gray-300 text-brand">
                        Persist anonymous claim in a cookie
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input type="hidden" name="attribution_allow_replacement" value="0">
                        <input type="checkbox" name="attribution_allow_replacement" value="1"
                               @checked((bool) ($attribution['allow_replacement_before_lock'] ?? false))
                               class="rounded border-gray-300 text-brand">
                        Allow replacement before lock
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input type="hidden" name="attribution_allow_override_after_lock" value="0">
                        <input type="checkbox" name="attribution_allow_override_after_lock" value="1"
                               @checked((bool) ($attribution['allow_override_after_lock'] ?? false))
                               class="rounded border-gray-300 text-brand">
                        Allow override after lock
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input type="hidden" name="attribution_existing_customer" value="0">
                        <input type="checkbox" name="attribution_existing_customer" value="1"
                               @checked((bool) ($attribution['existing_customer_referral'] ?? false))
                               class="rounded border-gray-300 text-brand">
                        Allow referring already-active borrowers
                    </label>
                </div>
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="messages">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Partner messages</h3>
                    <p class="text-xs text-gray-500 mt-1">English and Swahili templates. Placeholders: <span class="font-mono">{brand}</span>, <span class="font-mono">{affiliate_name}</span>, <span class="font-mono">{affiliate_code}</span>, <span class="font-mono">{affiliate_link}</span>, <span class="font-mono">{registration_link}</span>, <span class="font-mono">{verify_link}</span></p>
                </div>
                @foreach ([
                    ['message_share_template', 'message_share_template_sw', 'Share message (portal copy)'],
                    ['message_referral_sms', 'message_referral_sms_sw', 'Referral SMS template'],
                    ['message_verification_notice', 'message_verification_notice_sw', 'Public verification notice'],
                    ['message_welcome_partner', 'message_welcome_partner_sw', 'Welcome message (new affiliates)'],
                ] as [$en, $sw, $label])
                    <div class="grid md:grid-cols-2 gap-4">
                        <x-admin.textarea :name="$en" :label="$label.' (English)'" rows="2"
                                          :value="$values[$en] ?? ''" />
                        <x-admin.textarea :name="$sw" :label="$label.' (Swahili)'" rows="2"
                                          :value="$values[$sw] ?? ''" />
                    </div>
                @endforeach
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="evaluation">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
                <h3 class="text-sm font-semibold text-gray-900 mb-1">Affiliate performance &amp; automation</h3>
                <p class="text-xs text-gray-500">Used by <span class="font-mono">php artisan affiliate:evaluate</span>. The formal assessment period defaults to 90 days (quarterly) and is Settings-owned. Qualified referrals use existing registration events. First miss warns; repeated misses move to At risk then Suspended. Fraud still suspends compliance immediately. Field partners use <a href="{{ route('admin.settings.partner-performance') }}" class="font-semibold text-brand hover:underline">Partner performance</a>.</p>
                @php $eval = $values['evaluation'] ?? []; $kpis = $eval['kpis'] ?? config('affiliates.evaluation.kpis', []); @endphp
                <label class="inline-flex items-center gap-2 text-sm text-gray-800 mb-2">
                    <input type="hidden" name="eval_auto_apply_actions" value="0">
                    <input type="checkbox" name="eval_auto_apply_actions" value="1"
                           @checked((bool) ($eval['auto_apply_actions'] ?? true))
                           class="rounded border-gray-300 text-brand">
                    Automatically apply warnings / suspension / recovery
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-gray-800 mb-2">
                    <input type="hidden" name="eval_auto_recover" value="0">
                    <input type="checkbox" name="eval_auto_recover" value="1"
                           @checked((bool) ($eval['auto_recover'] ?? true))
                           class="rounded border-gray-300 text-brand">
                    Automatically restore eligibility when recovery KPIs are met
                </label>
                <div class="grid md:grid-cols-3 gap-4">
                    <x-admin.input name="eval_period_days" label="Assessment period (days)" type="number" min="1"
                                   :value="$eval['period_days'] ?? 90" />
                    <x-admin.input name="eval_min_events_for_scoring" label="Min events before scoring" type="number" min="1"
                                   :value="$eval['min_events_for_scoring'] ?? 3" />
                    <x-admin.input name="eval_high_click_threshold" label="High click threshold" type="number" min="1"
                                   :value="$eval['high_click_threshold'] ?? 50" />
                    <x-admin.input name="eval_monthly_registration_target" label="Qualified referrals (target)" type="number" min="0"
                                   :value="$eval['monthly_registration_target'] ?? ($kpis['qualified_referrals']['target'] ?? 10)" />
                    <x-admin.input name="eval_volume_min_active_days" label="Ramp-up days before enforcement" type="number" min="0"
                                   :value="$eval['volume_min_active_days'] ?? 90" />
                    <x-admin.input name="eval_volume_misses_before_nudge" label="Missed periods before warning" type="number" min="1"
                                   :value="$eval['volume_misses_before_nudge'] ?? 1" />
                    <x-admin.input name="eval_volume_misses_before_watchlist" label="Missed periods before at-risk" type="number" min="1"
                                   :value="$eval['volume_misses_before_watchlist'] ?? 2" />
                    <x-admin.input name="eval_volume_misses_before_suspend" label="Missed periods before suspend" type="number" min="1"
                                   :value="$eval['volume_misses_before_suspend'] ?? 3" />
                    <x-admin.input name="eval_low_conversion_threshold" label="Low conversion % threshold" type="number" step="0.1" min="0"
                                   :value="$eval['low_conversion_threshold'] ?? 5" />
                    <x-admin.input name="eval_duplicate_ip_threshold" label="Duplicate IP registration threshold" type="number" min="1"
                                   :value="$eval['duplicate_ip_registration_threshold'] ?? 3" />
                    <x-admin.input name="eval_watchlist_risk_score" label="Watchlist risk score" type="number" step="0.1" min="0" max="100"
                                   :value="$eval['watchlist_risk_score'] ?? 60" />
                    <x-admin.input name="eval_watchlist_fraud_score" label="Watchlist fraud score" type="number" step="0.1" min="0" max="100"
                                   :value="$eval['watchlist_fraud_score'] ?? 50" />
                    <x-admin.input name="eval_suspend_risk_score" label="Suspend risk score" type="number" step="0.1" min="0" max="100"
                                   :value="$eval['suspend_risk_score'] ?? 80" />
                    <x-admin.input name="eval_suspend_fraud_score" label="Suspend fraud score" type="number" step="0.1" min="0" max="100"
                                   :value="$eval['suspend_fraud_score'] ?? 75" />
                </div>
                <h4 class="text-xs font-semibold uppercase tracking-widest text-gray-500 pt-2">KPI catalogue (only enabled metrics are enforced)</h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-xs uppercase text-gray-500">
                            <tr>
                                <th class="text-left py-2">Enabled</th>
                                <th class="text-left py-2">Metric</th>
                                <th class="text-left py-2">Target</th>
                                <th class="text-left py-2">Weight</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ([
                                'qualified_referrals' => 'Qualified referrals',
                                'applications' => 'Applications generated',
                                'disbursed_loans' => 'Loans disbursed',
                                'conversion' => 'Conversion % (reg → application)',
                            ] as $key => $label)
                                @php $row = $kpis[$key] ?? ['enabled' => $key === 'qualified_referrals', 'target' => $key === 'conversion' ? 30 : 10, 'weight' => 1]; @endphp
                                <tr>
                                    <td class="py-2">
                                        <input type="hidden" name="kpi_{{ $key }}_enabled" value="0">
                                        <input type="checkbox" name="kpi_{{ $key }}_enabled" value="1" class="rounded border-gray-300 text-brand" @checked((bool) ($row['enabled'] ?? false))>
                                    </td>
                                    <td class="py-2">{{ $label }}</td>
                                    <td class="py-2"><input type="number" name="kpi_{{ $key }}_target" value="{{ $row['target'] ?? 0 }}" min="0" step="0.1" class="w-28 rounded-lg border-gray-300 ring-1 ring-gray-200 px-2 py-1.5 text-sm"></td>
                                    <td class="py-2"><input type="number" name="kpi_{{ $key }}_weight" value="{{ $row['weight'] ?? 1 }}" min="0" step="0.1" class="w-20 rounded-lg border-gray-300 ring-1 ring-gray-200 px-2 py-1.5 text-sm"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="terms">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
                <h3 class="text-sm font-semibold text-gray-900">Affiliate Terms</h3>
                <p class="text-xs text-gray-500">Templates may only use approved Settings variables such as <span class="font-mono">@{{membership_fee_individual}}</span>, <span class="font-mono">@{{assessment_period}}</span>, <span class="font-mono">@{{minimum_qualified_referrals}}</span>. Leave blank to use the built-in EN/SW catalogue. Saving Terms increments the agreement version; historical acceptances stay frozen.</p>
                <x-admin.textarea name="terms_body_en" label="English Terms (optional override)" rows="8" :value="$values['terms_body_en'] ?? ''" />
                <x-admin.textarea name="terms_body_sw" label="Kiswahili Terms (optional override)" rows="8" :value="$values['terms_body_sw'] ?? ''" />
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="fraud">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
                <h3 class="text-sm font-semibold text-gray-900">Fraud detection thresholds</h3>
                @php $fraud = $values['fraud'] ?? []; @endphp
                <div class="grid md:grid-cols-3 gap-4">
                    <x-admin.input name="fraud_medium_score" label="Medium risk score" type="number" min="0" max="100"
                                   :value="$fraud['medium_score'] ?? 20" />
                    <x-admin.input name="fraud_high_score" label="High risk score" type="number" min="0" max="100"
                                   :value="$fraud['high_score'] ?? 50" />
                    <x-admin.input name="fraud_blocked_score" label="Blocked risk score" type="number" min="0" max="100"
                                   :value="$fraud['blocked_score'] ?? 80" />
                    <x-admin.input name="fraud_shared_phone_threshold" label="Shared phone threshold" type="number" min="1"
                                   :value="$fraud['shared_phone_customer_threshold'] ?? 2" />
                    <x-admin.input name="fraud_shared_device_threshold" label="Shared device registrations" type="number" min="1"
                                   :value="$fraud['shared_device_registration_threshold'] ?? 2" />
                    <x-admin.input name="fraud_multi_account_threshold" label="Multi-account device threshold" type="number" min="1"
                                   :value="$fraud['multi_account_device_threshold'] ?? 2" />
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">Anti-fraud</h3>
                <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input type="hidden" name="require_kyc_for_verification" value="0">
                    <input type="checkbox" name="require_kyc_for_verification" value="1"
                           @checked((bool) ($values['require_kyc_for_verification'] ?? true))
                           class="rounded border-gray-300 text-brand">
                    Require approved KYC before public affiliate verification badge
                </label>
            </div>
        </x-admin.settings-panel>
    </x-admin.settings-editor>
</x-admin.layout>
