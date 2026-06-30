<x-admin.layout title="Affiliate Settings" heading="Affiliate Settings" subheading="Promo code rules, defaults, and where affiliate discounts apply">
    @include('admin.settings._tabs', ['active' => 'affiliates'])

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.settings.affiliates.save') }}" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-6">
        @csrf @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-admin.input name="code_prefix" label="Code prefix (fallback)" :value="$values['code_prefix'] ?? 'KPA'" required />
            <x-admin.input name="default_commission_percent" label="Default commission (%)" type="number" step="0.1" min="0" max="100"
                           :value="$values['default_commission_percent'] ?? 10" required />
            <x-admin.input name="default_registration_discount_percent" label="Default registration discount (%)" type="number" step="0.1" min="0" max="100"
                           :value="$values['default_registration_discount_percent'] ?? 10" required />
            <x-admin.input name="default_application_discount_percent" label="Default application discount (%)" type="number" step="0.1" min="0" max="100"
                           :value="$values['default_application_discount_percent'] ?? 10" required />
        </div>

        <div>
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Commission mode</h3>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm mb-4">
                @foreach (['percentage' => 'Percentage', 'fixed' => 'Fixed amount', 'tiered' => 'Tiered by referrals', 'hybrid' => 'Hybrid (fixed + %)'] as $mode => $label)
                    <label class="inline-flex items-center gap-2">
                        <input type="radio" name="commission_mode" value="{{ $mode }}"
                               @checked(($values['commission_mode'] ?? 'percentage') === $mode)
                               class="text-amber-600">
                        {{ $label }}
                    </label>
                @endforeach
            </div>

            <div class="grid md:grid-cols-2 gap-4 mb-4">
                <x-admin.input name="hybrid_fixed_amount" label="Hybrid fixed amount (TZS)" type="number" step="1" min="0"
                               :value="$values['hybrid_fixed_amount'] ?? 0" />
                <x-admin.input name="hybrid_percent" label="Hybrid percent (%)" type="number" step="0.1" min="0" max="100"
                               :value="$values['hybrid_percent'] ?? 0" />
            </div>

            <h4 class="text-xs font-semibold text-gray-700 uppercase mb-2">Fixed commission amounts (TZS)</h4>
            <div class="grid md:grid-cols-2 gap-4 mb-4">
                <x-admin.input name="fixed_commission_default" label="Default" type="number" step="1" min="0"
                               :value="$values['fixed_commission_amounts']['default'] ?? 0" />
                <x-admin.input name="fixed_commission_registration_fee" label="Registration fee" type="number" step="1" min="0"
                               :value="$values['fixed_commission_amounts']['registration_fee'] ?? 0" />
                <x-admin.input name="fixed_commission_application_fee" label="Application fee" type="number" step="1" min="0"
                               :value="$values['fixed_commission_amounts']['application_fee'] ?? 0" />
                <x-admin.input name="fixed_commission_post_approval_fee" label="Post approval fee" type="number" step="1" min="0"
                               :value="$values['fixed_commission_amounts']['post_approval_fee'] ?? 0" />
            </div>

            <h4 class="text-xs font-semibold text-gray-700 uppercase mb-2">Tiered commission (by registration/application count)</h4>
            @php $tiers = $values['commission_tiers'] ?? []; @endphp
            <div class="space-y-3">
                @foreach (range(0, 2) as $i)
                    @php $tier = $tiers[$i] ?? ['min_count' => '', 'max_count' => '', 'type' => 'fixed', 'amount' => '']; @endphp
                    <div class="grid md:grid-cols-4 gap-3 items-end">
                        <x-admin.input name="commission_tiers[{{ $i }}][min_count]" label="Min count" type="number" min="0"
                                       :value="$tier['min_count'] ?? ''" />
                        <x-admin.input name="commission_tiers[{{ $i }}][max_count]" label="Max count (blank = unlimited)" type="number" min="0"
                                       :value="$tier['max_count'] ?? ''" />
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Type</label>
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

        <div>
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Commission calculation base</h3>
            <div class="flex flex-wrap gap-4 text-sm">
                <label class="inline-flex items-center gap-2">
                    <input type="radio" name="commission_calculation_base" value="discounted_amount"
                           @checked(($values['commission_calculation_base'] ?? 'discounted_amount') === 'discounted_amount')
                           class="text-amber-600">
                    Discounted amount (recommended)
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="radio" name="commission_calculation_base" value="original_amount"
                           @checked(($values['commission_calculation_base'] ?? '') === 'original_amount')
                           class="text-amber-600">
                    Original amount
                </label>
            </div>
            <p class="text-xs text-gray-500 mt-2">Example: 10,000 fee with 10% discount → paid 9,000. At 10% commission on discounted base = 900.</p>
        </div>

        <div>
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Apply affiliate promo codes to</h3>
            <p class="text-xs text-gray-500 mb-4">Choose which fee types accept affiliate discounts and accrue commission.</p>
            @php
                $feeLabels = [
                    'registration_fee'  => 'Registration fee',
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
                               class="rounded border-gray-300 text-amber-600">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>

        <div>
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Partner messages</h3>
            <p class="text-xs text-gray-500 mb-4">Placeholders: <span class="font-mono">{brand}</span>, <span class="font-mono">{affiliate_name}</span>, <span class="font-mono">{affiliate_code}</span>, <span class="font-mono">{affiliate_link}</span>, <span class="font-mono">{registration_link}</span>, <span class="font-mono">{verify_link}</span></p>
            <div class="space-y-4">
                <x-admin.textarea name="message_share_template" label="Share message (portal copy)" rows="2"
                                  :value="$values['message_share_template'] ?? ''" />
                <x-admin.textarea name="message_referral_sms" label="Referral SMS template" rows="2"
                                  :value="$values['message_referral_sms'] ?? ''" />
                <x-admin.textarea name="message_verification_notice" label="Public verification notice" rows="2"
                                  :value="$values['message_verification_notice'] ?? ''" />
                <x-admin.textarea name="message_welcome_partner" label="Welcome message (new affiliates)" rows="2"
                                  :value="$values['message_welcome_partner'] ?? ''" />
            </div>
        </div>

        <div>
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Monthly evaluation & automation</h3>
            <p class="text-xs text-gray-500 mb-4">Used by <span class="font-mono">php artisan affiliate:evaluate</span> (scheduled 1st of each month). Auto-actions move affiliates to watchlist or suspended when thresholds are exceeded.</p>
            @php $eval = $values['evaluation'] ?? []; @endphp
            <label class="inline-flex items-center gap-2 text-sm text-gray-800 mb-4">
                <input type="hidden" name="eval_auto_apply_actions" value="0">
                <input type="checkbox" name="eval_auto_apply_actions" value="1"
                       @checked((bool) ($eval['auto_apply_actions'] ?? true))
                       class="rounded border-gray-300 text-amber-600">
                Automatically apply watchlist / suspension recommendations
            </label>
            <div class="grid md:grid-cols-3 gap-4">
                <x-admin.input name="eval_period_days" label="Evaluation period (days)" type="number" min="1"
                               :value="$eval['period_days'] ?? 30" />
                <x-admin.input name="eval_min_events_for_scoring" label="Min events before scoring" type="number" min="1"
                               :value="$eval['min_events_for_scoring'] ?? 3" />
                <x-admin.input name="eval_high_click_threshold" label="High click threshold" type="number" min="1"
                               :value="$eval['high_click_threshold'] ?? 50" />
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

        <div>
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Fraud detection thresholds</h3>
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

        <div>
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Anti-fraud</h3>
            <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                <input type="hidden" name="require_kyc_for_verification" value="0">
                <input type="checkbox" name="require_kyc_for_verification" value="1"
                       @checked((bool) ($values['require_kyc_for_verification'] ?? true))
                       class="rounded border-gray-300 text-amber-600">
                Require approved KYC before public affiliate verification badge
            </label>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-5 py-2 rounded-lg shadow-sm">
                Save affiliate settings
            </button>
        </div>
    </form>
</x-admin.layout>
