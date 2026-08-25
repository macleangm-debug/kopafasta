@props([
    'feeQuote' => null,
    'bankAccounts' => [],
    'currency' => 'TZS',
    'paymentReference' => null,
    'referralWallet' => null,
    'referralSettings' => [],
    'paymentGatewayDummy' => true,
    'applyRequirements' => null,
    'pointsBalance' => null,
])

@php
    $referralPoints = wallet_balance_as_points((float) ($referralWallet->balance ?? 0));
    $loyaltyPoints = (int) ($pointsBalance ?? 0);
@endphp

{{-- Fee discounts stay here; method + USSD push live on payments.show (canonical PSP gate). --}}
<div x-show="stepKey === 'application_fee' || $data.feeGateOpen" class="p-6 sm:p-8">
    <x-site.wizard-step-header
        :eyebrow="__('borrower.apply.application_fee.eyebrow')"
        :title="__('borrower.apply.application_fee.title')"
        :subtitle="__('borrower.apply.application_fee.gate_subtitle')"
    />

    <div x-show="feeNotice" x-cloak class="mb-6 rounded-xl px-4 py-4 text-sm"
         :class="feeNotice?.tone === 'success' ? 'bg-emerald-50 ring-1 ring-emerald-200 text-emerald-900' : 'bg-rose-50 ring-1 ring-rose-200 text-rose-900'">
        <p class="font-semibold" x-text="feeNotice?.message"></p>
    </div>

    <div x-show="applicationFeePaid" x-cloak class="rounded-xl bg-brand-muted/50 ring-1 ring-brand/20 px-4 py-4 text-sm text-brand mb-6">
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
        <div x-show="isAssetBackedProduct(current) && effectiveValuationFeeAmount() > 0" x-cloak
             class="glass-card rounded-2xl ring-1 ring-brand/15 px-5 py-4 text-sm mb-6 space-y-2">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.application_fee.fee_breakdown.title') }}</p>
            <div class="flex justify-between gap-4">
                <span class="text-gray-600">{{ __('borrower.apply.application_fee.fee_breakdown.application_line') }}</span>
                <span class="font-mono font-semibold tabular-nums" x-text="formatAmount(Math.max(0, effectiveFeeAmount() - effectiveValuationFeeAmount()))"></span>
            </div>
            <div class="flex justify-between gap-4">
                <span class="text-gray-600">{{ __('borrower.apply.application_fee.fee_breakdown.valuation_line') }}</span>
                <span class="font-mono font-semibold tabular-nums" x-text="formatAmount(effectiveValuationFeeAmount())"></span>
            </div>
            <div class="flex justify-between gap-4 pt-2 border-t border-brand/10 font-semibold text-gray-900">
                <span>{{ __('borrower.apply.application_fee.fee_breakdown.total') }}</span>
                <span class="font-mono tabular-nums" x-text="formatAmount(effectiveFeeAmount())"></span>
            </div>
            <p class="text-xs text-gray-500 pt-1">{{ __('borrower.apply.application_fee.fee_breakdown.valuation_note') }}</p>
        </div>
        <div x-show="isGroupProduct(current) && groupFeeBreakdown()" x-cloak class="rounded-xl ring-1 ring-brand/15 bg-brand-muted/40 px-4 py-4 text-sm text-brand mb-6 space-y-2">
            <p class="text-xs font-semibold uppercase tracking-widest text-brand">{{ __('borrower.apply.group.fee_breakdown.settings_note') }}</p>
            <div class="flex justify-between gap-4"><span>{{ __('borrower.apply.group.fee_breakdown.per_member') }}</span><span class="font-mono font-semibold" x-text="formatAmount(groupFeeBreakdown().per_member)"></span></div>
            <div class="flex justify-between gap-4"><span>{{ __('borrower.apply.group.fee_breakdown.members') }}</span><span class="font-semibold" x-text="groupFeeBreakdown().member_count"></span></div>
            <div class="flex justify-between gap-4 pt-2 border-t border-brand/15 font-semibold"><span>{{ __('borrower.apply.group.fee_breakdown.total') }}</span><span class="font-mono" x-text="formatAmount(groupFeeBreakdown().total)"></span></div>
        </div>

        <div class="glass-card overflow-hidden ring-1 ring-brand/15 mb-6">
            <div class="kf-premium-panel px-6 py-5">
                <p class="text-[10px] uppercase tracking-widest text-white/80">{{ __('borrower.apply.application_fee.amount_label') }}</p>
                <p class="mt-2 text-3xl font-extrabold tabular-nums" x-show="feeQuoteData && feeQuoteData.base > 0" x-text="formatAmount(feeQuoteData?.cash_due ?? feeQuoteData?.after_discount ?? effectiveFeeAmount())"></p>
                <p class="mt-2 text-3xl font-extrabold tabular-nums" x-show="!feeQuoteData || feeQuoteData.base <= 0"><span x-text="formatAmount(effectiveFeeAmount())"></span></p>
            </div>
            <div class="px-6 py-4 bg-white border-t border-gray-100 text-sm space-y-1.5" x-show="feeQuoteData && feeQuoteData.base > 0">
                <div class="flex justify-between gap-4 text-gray-600">
                    <span>{{ __('borrower.apply.application_fee.amount_label') }}</span>
                    <span class="font-mono" x-text="formatAmount(feeQuoteData.base)"></span>
                </div>
                <template x-if="feeQuoteData.promo_discount > 0">
                    <div class="flex justify-between gap-4 text-gray-600">
                        <span>{{ __('borrower.apply.application_fee.promo_discount') }}</span>
                        <span class="font-mono text-emerald-700" x-text="'− ' + formatAmount(feeQuoteData.promo_discount)"></span>
                    </div>
                </template>
                <template x-if="feeQuoteData.referral_discount > 0">
                    <div class="flex justify-between gap-4 text-gray-600">
                        <span>{{ __('borrower.apply.application_fee.referral_discount') }}</span>
                        <span class="font-mono text-emerald-700" x-text="'− ' + formatAmount(feeQuoteData.referral_discount)"></span>
                    </div>
                </template>
                <template x-if="feeQuoteData.affiliate_discount > 0">
                    <div class="flex justify-between gap-4 text-gray-600">
                        <span>{{ __('borrower.apply.application_fee.affiliate_discount') }}</span>
                        <span class="font-mono text-emerald-700" x-text="'− ' + formatAmount(feeQuoteData.affiliate_discount)"></span>
                    </div>
                </template>
                <template x-if="feeQuoteData.loyalty_discount > 0">
                    <div class="flex justify-between gap-4 text-gray-600">
                        <span>{{ __('borrower.apply.application_fee.loyalty_discount') }}</span>
                        <span class="font-mono text-emerald-700" x-text="'− ' + formatAmount(feeQuoteData.loyalty_discount)"></span>
                    </div>
                </template>
                <template x-if="feeQuoteData.wallet_applied > 0">
                    <div class="flex justify-between gap-4 text-gray-600">
                        <span>{{ __('borrower.apply.application_fee.referral_points') }}</span>
                        <span class="font-mono text-emerald-700" x-text="'− ' + Math.round(feeQuoteData.wallet_applied / {{ referral_points_per_tzs() }}) + ' {{ __('borrower.rewards.points_short') }}'"></span>
                    </div>
                </template>
                <div class="flex justify-between gap-4 font-semibold pt-1 border-t border-gray-200 text-gray-900">
                    <span>{{ __('borrower.apply.application_fee.amount_due') }}</span>
                    <span class="font-mono" x-text="formatAmount(feeQuoteData.cash_due ?? feeQuoteData.after_discount)"></span>
                </div>
            </div>
        </div>

        <div class="mb-6 rounded-xl bg-brand-muted/30 ring-1 ring-brand/15 px-4 py-4 text-sm text-brand"
             x-show="feeLoyaltyOption?.can_redeem" x-cloak>
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox"
                       x-model="feeRedeemLoyalty"
                       class="mt-1 rounded border-brand/30 text-brand focus:ring-brand">
                <span>
                    <span class="font-semibold" x-text="feeLoyaltyOption?.label || @js(__('borrower.apply.application_fee.redeem_loyalty_label'))"></span>
                    <span class="block mt-1 text-xs text-brand/80"
                          x-show="estimatedLoyaltySave() > 0"
                          x-text="@js(__('borrower.apply.application_fee.youll_save')).replace(':amount', formatAmount(estimatedLoyaltySave()))"></span>
                    <span class="block mt-1 text-xs text-brand/70"
                          x-text="@js(__('borrower.apply.application_fee.redeem_costs_points')).replace(':points', String(feeLoyaltyOption?.points || 0))"></span>
                </span>
            </label>
        </div>

        <x-site.promo-code-toggle
            alpine-model="feePromoCode"
            alpine-apply="refreshApplicationFeeQuote()"
            hidden-when="!feeUseWallet"
        >
            <template x-if="feeQuoteData?.promo_valid && feeQuoteData?.promo_code">
                <p class="mt-1 text-xs text-emerald-700" x-text="`{{ __('borrower.membership.promo_applied', ['code' => '__CODE__']) }}`.replace('__CODE__', feeQuoteData.promo_code)"></p>
            </template>
            <p class="mt-1 text-xs text-emerald-700" x-show="feeQuoteData?.has_affiliate && feeQuoteData?.affiliate_discount > 0" x-cloak>
                {{ __('borrower.apply.application_fee.affiliate_applied') }}
            </p>
        </x-site.promo-code-toggle>

        @if ($feeQuote && ($feeQuote['wallet_allowed'] ?? false) && $referralPoints > 0)
            <div class="mb-6 rounded-xl bg-brand-muted/40 ring-1 ring-brand/15 px-4 py-4 text-sm text-brand">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" x-model="feeUseWallet" @change="feePromoCode = feeUseWallet ? '' : feePromoCode; refreshApplicationFeeQuote()" class="mt-1 rounded border-brand/30 text-brand focus:ring-brand">
                    <span>
                        {{ __('borrower.apply.application_fee.wallet_points_label', [
                            'points' => number_format($referralPoints),
                            'percent' => rtrim(rtrim(format_number($referralSettings['wallet_max_fee_percent'] ?? 0, 2), '0'), '.'),
                        ]) }}
                    </span>
                </label>
            </div>
        @endif

        <p class="mb-4 text-sm text-gray-600">{{ __('borrower.apply.application_fee.open_gate_hint') }}</p>

        <button type="button"
                @click="payApplicationFee()"
                :disabled="feePaying"
                class="w-full bg-brand hover:bg-brand-light disabled:opacity-60 text-white font-semibold px-5 py-3 rounded-xl text-sm shadow-sm">
            <span x-text="feePaying ? @js(__('borrower.apply.application_fee.processing')) : @js(__('borrower.apply.application_fee.continue_to_payment'))"></span>
        </button>
    </div>
</div>
