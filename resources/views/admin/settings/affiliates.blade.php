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
            'messages' => 'Messages',
            'evaluation' => 'Evaluation',
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
                    <h3 class="text-sm font-semibold text-gray-900">Apply affiliate promo codes to</h3>
                    <p class="text-xs text-gray-500">Choose which fee types accept affiliate discounts and accrue commission.</p>
                </div>
                @php
                    $feeLabels = [
                        'registration_fee'  => 'Membership fee',
                        'application_fee'   => 'Application fee',
                        'post_approval_fee' => 'Post approval fee',
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
                <h3 class="text-sm font-semibold text-gray-900 mb-1">Monthly evaluation &amp; automation</h3>
                <p class="text-xs text-gray-500">Used by <span class="font-mono">php artisan affiliate:evaluate</span> (scheduled 1st of each month). <strong>New users per month</strong> is borrower registrations via their code — change 10 to 15 or 20 here. First miss sends a nudge; repeated misses go to watchlist then suspend (never terminate automatically). Fraud still suspends immediately when those scores are hit. Field partners (valuer, GPS, recovery) use <a href="{{ route('admin.settings.partner-performance') }}" class="font-semibold text-brand hover:underline">Partner performance</a>.</p>
                @php $eval = $values['evaluation'] ?? []; @endphp
                <label class="inline-flex items-center gap-2 text-sm text-gray-800 mb-2">
                    <input type="hidden" name="eval_auto_apply_actions" value="0">
                    <input type="checkbox" name="eval_auto_apply_actions" value="1"
                           @checked((bool) ($eval['auto_apply_actions'] ?? true))
                           class="rounded border-gray-300 text-brand">
                    Automatically apply watchlist / suspension recommendations
                </label>
                <div class="grid md:grid-cols-3 gap-4">
                    <x-admin.input name="eval_period_days" label="Evaluation period (days)" type="number" min="1"
                                   :value="$eval['period_days'] ?? 30" />
                    <x-admin.input name="eval_min_events_for_scoring" label="Min events before scoring" type="number" min="1"
                                   :value="$eval['min_events_for_scoring'] ?? 3" />
                    <x-admin.input name="eval_high_click_threshold" label="High click threshold" type="number" min="1"
                                   :value="$eval['high_click_threshold'] ?? 50" />
                    <x-admin.input name="eval_monthly_registration_target" label="New users per month (target)" type="number" min="0"
                                   :value="$eval['monthly_registration_target'] ?? 10" />
                    <x-admin.input name="eval_volume_min_active_days" label="Days before volume scoring (onboarding)" type="number" min="0"
                                   :value="$eval['volume_min_active_days'] ?? 30" />
                    <x-admin.input name="eval_volume_misses_before_nudge" label="Missed months before a nudge" type="number" min="1"
                                   :value="$eval['volume_misses_before_nudge'] ?? 1" />
                    <x-admin.input name="eval_volume_misses_before_watchlist" label="Missed months before watchlist" type="number" min="1"
                                   :value="$eval['volume_misses_before_watchlist'] ?? 2" />
                    <x-admin.input name="eval_volume_misses_before_suspend" label="Missed months before suspend" type="number" min="1"
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
