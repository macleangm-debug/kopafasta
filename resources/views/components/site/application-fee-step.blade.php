@props([
    'feeQuote' => null,
    'bankAccounts' => [],
    'currency' => 'TZS',
    'paymentReference' => null,
    'referralWallet' => null,
    'referralSettings' => [],
    'streakReward' => null,
    'paymentGatewayDummy' => true,
    'applyRequirements' => null,
    'pointsBalance' => null,
])

@php
    $referralPoints = wallet_balance_as_points((float) ($referralWallet->balance ?? 0));
    $loyaltyPoints = (int) ($pointsBalance ?? 0);
@endphp

<div x-show="stepKey === 'application_fee'" class="p-6 sm:p-8">
    <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-1">{{ __('borrower.apply.application_fee.eyebrow') }}</p>
    <h2 class="text-xl font-semibold mb-1">{{ __('borrower.apply.application_fee.title') }}</h2>
    <p class="text-sm text-gray-600 mb-5">{{ __('borrower.apply.application_fee.subtitle') }}</p>

    @if ($applyRequirements && ! ($applyRequirements['can_apply'] ?? true))
        <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-4 text-sm text-amber-900 mb-6">
            <p class="font-semibold">{{ __('borrower.apply.kyc_incomplete_title') }}</p>
            <p class="mt-1 text-amber-800">{{ __('borrower.apply.kyc_incomplete_hint') }}</p>
            <ul class="mt-2 space-y-1 text-amber-800">
                @foreach (($applyRequirements['items'] ?? []) as $item)
                    @if (! ($item['complete'] ?? false))
                        <li class="flex items-start gap-2">
                            <span>•</span>
                            <span>
                                {{ $item['label'] }}
                                @if (! empty($item['action_url']))
                                    — <a href="{{ $item['action_url'] }}" class="font-semibold underline">{{ __('borrower.apply.details.complete_missing') }}</a>
                                @endif
                            </span>
                        </li>
                    @endif
                @endforeach
            </ul>
        </div>
    @endif

    @if ($paymentGatewayDummy)
        <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-4 text-sm text-amber-900 mb-6">
            <p class="font-semibold">{{ __('borrower.apply.application_fee.dummy_banner_title') }}</p>
            <p class="mt-1 text-amber-800">{{ __('borrower.apply.application_fee.dummy_banner') }}</p>
        </div>
    @endif

    <div x-show="applicationFeeState?.status === 'pending'" x-cloak class="rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-4 text-sm text-sky-900 mb-6">
        <p class="font-semibold">{{ __('borrower.apply.application_fee.bank_submitted', ['ref' => $paymentReference ?? '—']) }}</p>
        <p class="mt-1 text-xs font-mono" x-show="applicationFeeState?.reference" x-text="applicationFeeState.reference"></p>
        <p class="mt-2 text-xs">{{ __('borrower.membership.bank_hint') }}</p>
    </div>

    <div x-show="applicationFeePaid" x-cloak class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-4 text-sm text-emerald-900 mb-6">
        <p class="font-semibold">{{ __('borrower.apply.application_fee.already_paid') }}</p>
        <p class="mt-1 text-xs" x-show="applicationFeeState?.reference">
            {{ __('borrower.apply.application_fee.reference') }}:
            <span class="font-mono font-semibold" x-text="applicationFeeState.reference"></span>
        </p>
    </div>

    <div x-show="!applicationFeePaid && effectiveFeeAmount() <= 0" x-cloak class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-4 text-sm text-gray-700 mb-6">
        {{ __('borrower.apply.application_fee.waived') }}
    </div>

    <div x-show="showsApplicationFeePayment()" x-cloak>
        <div x-show="isGroupProduct(current) && groupFeeBreakdown()" x-cloak class="rounded-xl ring-1 ring-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-950 mb-6 space-y-2">
            <p class="text-xs font-semibold uppercase tracking-widest text-amber-800">{{ __('borrower.apply.group.fee_breakdown.settings_note') }}</p>
            <div class="flex justify-between gap-4"><span>{{ __('borrower.apply.group.fee_breakdown.per_member') }}</span><span class="font-mono font-semibold" x-text="formatTzs(groupFeeBreakdown().per_member)"></span></div>
            <div class="flex justify-between gap-4"><span>{{ __('borrower.apply.group.fee_breakdown.members') }}</span><span class="font-semibold" x-text="groupFeeBreakdown().member_count"></span></div>
            <div class="flex justify-between gap-4 pt-2 border-t border-amber-200 font-semibold"><span>{{ __('borrower.apply.group.fee_breakdown.total') }}</span><span class="font-mono" x-text="formatTzs(groupFeeBreakdown().total)"></span></div>
        </div>
        <div class="glass-card overflow-hidden ring-1 ring-brand/15 mb-6">
            <div class="bg-gradient-to-br from-brand to-brand-light text-white px-6 py-5">
                <p class="text-[10px] uppercase tracking-widest text-white/80">{{ __('borrower.apply.application_fee.amount_label') }}</p>
                <p class="mt-2 text-3xl font-extrabold tabular-nums" x-show="feeQuoteData && feeQuoteData.base > 0" x-text="'{{ $currency }} ' + formatTzs(feeQuoteData?.cash_due ?? feeQuoteData?.after_discount ?? effectiveFeeAmount())"></p>
                <p class="mt-2 text-3xl font-extrabold tabular-nums" x-show="!feeQuoteData || feeQuoteData.base <= 0">{{ $currency }} <span x-text="formatTzs(effectiveFeeAmount())"></span></p>
                @if ($paymentReference)
                    <p class="mt-3 text-xs text-white/90">{{ __('borrower.membership.payment_reference') }}</p>
                    <p class="mt-1 font-mono text-sm bg-white/15 inline-block px-3 py-1 rounded-lg">{{ $paymentReference }}</p>
                @endif
                <p class="mt-3 text-xs text-white/90">{{ __('borrower.apply.application_fee.product_note') }}</p>
            </div>
            <div class="px-6 py-4 bg-white border-t border-gray-100 text-sm space-y-1.5" x-show="feeQuoteData && feeQuoteData.base > 0">
                <div class="flex justify-between gap-4 text-gray-600"><span>{{ __('borrower.apply.application_fee.amount_label') }}</span><span class="font-mono" x-text="formatTzs(feeQuoteData.base)"></span></div>
                <template x-if="feeQuoteData.promo_discount > 0"><div class="flex justify-between gap-4 text-gray-600"><span>{{ __('borrower.apply.application_fee.promo_discount') }}</span><span class="font-mono text-emerald-700" x-text="'− ' + formatTzs(feeQuoteData.promo_discount)"></span></div></template>
                <template x-if="feeQuoteData.referral_discount > 0"><div class="flex justify-between gap-4 text-gray-600"><span>{{ __('borrower.apply.application_fee.referral_discount') }}</span><span class="font-mono text-emerald-700" x-text="'− ' + formatTzs(feeQuoteData.referral_discount)"></span></div></template>
                <template x-if="feeQuoteData.affiliate_discount > 0"><div class="flex justify-between gap-4 text-gray-600"><span>{{ __('borrower.apply.application_fee.affiliate_discount') }}</span><span class="font-mono text-emerald-700" x-text="'− ' + formatTzs(feeQuoteData.affiliate_discount)"></span></div></template>
                <template x-if="feeQuoteData.loyalty_discount > 0"><div class="flex justify-between gap-4 text-gray-600"><span>{{ __('borrower.apply.application_fee.loyalty_discount') }}</span><span class="font-mono text-emerald-700" x-text="'− ' + formatTzs(feeQuoteData.loyalty_discount)"></span></div></template>
                <template x-if="feeQuoteData.streak_discount > 0"><div class="flex justify-between gap-4 text-gray-600"><span>{{ __('borrower.apply.application_fee.streak_discount') }}</span><span class="font-mono text-emerald-700" x-text="'− ' + formatTzs(feeQuoteData.streak_discount)"></span></div></template>
                <template x-if="feeQuoteData.wallet_applied > 0"><div class="flex justify-between gap-4 text-gray-600"><span>{{ __('borrower.apply.application_fee.referral_points') }}</span><span class="font-mono text-emerald-700" x-text="'− ' + Math.round(feeQuoteData.wallet_applied / {{ referral_points_per_tzs() }}) + ' {{ __('borrower.rewards.points_short') }}'"></span></div></template>
                <div class="flex justify-between gap-4 font-semibold pt-1 border-t border-gray-200 text-gray-900"><span>{{ __('borrower.apply.application_fee.amount_due') }}</span><span class="font-mono" x-text="formatTzs(feeQuoteData.cash_due ?? feeQuoteData.after_discount)"></span></div>
            </div>
        </div>

        @if ($loyaltyPoints > 0)
            <div class="mb-6 rounded-xl bg-brand-muted/30 ring-1 ring-brand/15 px-4 py-3 text-sm text-brand flex flex-wrap items-center justify-between gap-3">
                <span>{{ __('borrower.apply.application_fee.loyalty_points_hint', ['points' => number_format($loyaltyPoints)]) }}</span>
                <a href="{{ route('site.borrower.engagement', ['tab' => 'rewards']) }}" class="text-xs font-semibold underline shrink-0">{{ __('borrower.apply.application_fee.redeem_points_link') }}</a>
            </div>
        @endif

        <div class="mb-6 rounded-xl bg-white ring-1 ring-gray-200 px-4 py-4 text-sm">
            <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('borrower.apply.application_fee.promo_label') }}</label>
            <input type="text" x-model="feePromoCode" @change="refreshApplicationFeeQuote()" maxlength="40" class="w-full rounded-lg border-gray-300 text-sm font-mono uppercase" placeholder="PROMO2026">
        </div>

        @if ($feeQuote && ($feeQuote['wallet_allowed'] ?? false) && $referralPoints > 0)
            <div class="mb-6 rounded-xl bg-brand-muted/40 ring-1 ring-brand/15 px-4 py-4 text-sm text-brand">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" x-model="feeUseWallet" @change="feeUseStreak = false; refreshApplicationFeeQuote()" class="mt-1 rounded border-brand/30 text-brand focus:ring-brand">
                    <span>
                        {{ __('borrower.apply.application_fee.wallet_points_label', [
                            'points' => number_format($referralPoints),
                            'percent' => rtrim(rtrim(format_number($referralSettings['wallet_max_fee_percent'] ?? 0, 2), '0'), '.'),
                        ]) }}
                    </span>
                </label>
            </div>
        @endif

        @if (($streakReward['enabled'] ?? false) && ($streakReward['percent'] ?? 0) > 0)
            <div class="mb-6 rounded-xl bg-orange-50 ring-1 ring-orange-200 px-4 py-4 text-sm text-orange-950">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" x-model="feeUseStreak" @change="feeUseWallet = false; refreshApplicationFeeQuote()" class="mt-1 rounded border-orange-300 text-orange-600 focus:ring-orange-500">
                    <span>
                        {{ __('borrower.apply.application_fee.streak_label', [
                            'count' => $streakReward['count'] ?? 0,
                            'percent' => rtrim(rtrim(number_format($streakReward['percent'] ?? 0, 1), '0'), '.'),
                        ]) }}
                    </span>
                </label>
                <p class="mt-2 text-xs text-orange-800">{{ __('borrower.apply.application_fee.wallet_or_streak_hint') }}</p>
            </div>
        @endif

        <div class="space-y-5">
            <div>
                <p class="text-xs uppercase tracking-wider text-gray-500 mb-3">{{ __('borrower.membership.payment_method') }}</p>
                <div class="grid sm:grid-cols-2 gap-3">
                    <label class="cursor-pointer">
                        <input type="radio" value="mobile_money" x-model="feeChannel" class="sr-only peer">
                        <div class="rounded-xl border-2 border-gray-200 peer-checked:border-brand peer-checked:bg-brand-muted/30 p-4">
                            <p class="font-semibold text-sm">{{ __('borrower.membership.mobile_money') }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ __('borrower.membership.mobile_hint') }}</p>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" value="bank" x-model="feeChannel" class="sr-only peer">
                        <div class="rounded-xl border-2 border-gray-200 peer-checked:border-brand peer-checked:bg-brand-muted/30 p-4">
                            <p class="font-semibold text-sm">{{ __('borrower.membership.bank') }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ __('borrower.membership.bank_hint') }}</p>
                        </div>
                    </label>
                </div>
            </div>

            <div x-show="feeChannel === 'mobile_money'" x-transition>
                <label class="block text-xs uppercase tracking-wider text-gray-500 mb-1">{{ __('borrower.membership.mobile_money') }}</label>
                <input type="tel" x-model="feePhone" class="w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm" placeholder="+255 7XX XXX XXX">
                <p class="mt-2 text-xs text-gray-500">
                    @if ($paymentGatewayDummy)
                        {{ __('borrower.apply.application_fee.dummy_mobile_hint') }}
                    @else
                        {{ __('borrower.apply.application_fee.mobile_hint') }}
                    @endif
                </p>
            </div>

            <div x-show="feeChannel === 'bank'" x-cloak class="rounded-xl bg-sky-50 border border-sky-200 p-4 text-sm">
                @if ($paymentGatewayDummy)
                    <p class="font-semibold text-sky-900 mb-2">{{ __('borrower.apply.application_fee.dummy_banner_title') }}</p>
                    <p class="text-sky-800 text-xs">{{ __('borrower.apply.application_fee.dummy_bank_hint') }}</p>
                @else
                    <p class="font-semibold text-sky-900 mb-2">{{ __('borrower.apply.application_fee.bank_instructions') }}</p>
                    @if ($paymentReference)
                        <p class="text-sky-800 text-xs mb-3">{{ __('borrower.apply.application_fee.bank_reference', ['ref' => $paymentReference]) }}</p>
                    @endif
                    @foreach ($bankAccounts as $acct)
                        <div class="mb-2 last:mb-0 text-xs text-sky-800">
                            <p class="font-medium">{{ $acct['bank'] }}</p>
                            <p>{{ $acct['account_name'] }} · {{ $acct['account_number'] }}</p>
                        </div>
                    @endforeach
                @endif
            </div>

            <button type="button"
                    @click="payApplicationFee()"
                    :disabled="feePaying"
                    class="w-full bg-brand hover:bg-brand-light disabled:opacity-60 text-white font-semibold px-5 py-3 rounded-xl text-sm shadow-sm">
                <span x-text="feePaying ? @js(__('borrower.apply.application_fee.processing')) : (@js($paymentGatewayDummy) ? @js(__('borrower.apply.application_fee.dummy_pay')) : (feeChannel === 'mobile_money' ? @js(__('borrower.membership.pay_now')) : @js(__('borrower.membership.submit_bank'))))"></span>
            </button>
        </div>
    </div>
</div>
