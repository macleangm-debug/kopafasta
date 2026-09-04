<?php

namespace App\Services;

use App\Models\Customer;

class PaymentGateService
{
    /**
     * Unified fee quote: referral / affiliate discount → promo code → referral wallet.
     *
     * @return array{
     *   base: float,
     *   referral_discount: float,
     *   affiliate_discount: float,
     *   promo_discount: float,
     *   total_discount: float,
     *   after_discount: float,
     *   wallet_usable: float,
     *   wallet_applied: float,
     *   cash_due: float,
     *   commission: float,
     *   has_referrer: bool,
     *   has_affiliate: bool,
     *   promo_code: string|null,
     *   promo_valid: bool,
     * }
     */
    public function quote(
        Customer $customer,
        float $baseAmount,
        string $feeType,
        bool $useWallet = false,
        ?string $promoCode = null,
        ?string $affiliateCode = null,
        bool $applyLoyalty = false,
    ): array {
        [$resolvedPromo, $resolvedAffiliate] = app(ApplicationFeePaymentService::class)
            ->resolvePromoOrAffiliate($promoCode, $affiliateCode, $customer);

        if (filled($resolvedAffiliate) && ! app(ReferralService::class)->referrer($customer) && blank($customer->affiliate_vendor_id)) {
            app(AffiliateService::class)->attachAffiliate($customer, $resolvedAffiliate);
            $customer->refresh();
        }
        $referrals = app(ReferralService::class);
        $affiliates = app(AffiliateService::class);
        $promotions = app(PromotionService::class);

        $referralDiscount = 0.0;
        $affiliateDiscount = 0.0;
        $promoDiscount = 0.0;
        $commission = 0.0;
        $hasReferrer = (bool) $referrals->referrer($customer);
        $hasAffiliate = false;
        $promoValid = false;
        $appliedPromo = null;
        $codeKind = null; // promo | affiliate | invalid

        $afterPartner = round($baseAmount, 2);
        $canonicalBase = $afterPartner;
        $staging = app(\App\Services\Staging\StagingPaymentsService::class);
        $afterPartner = $staging->effective($feeType, $afterPartner);
        $baseAmount = $afterPartner;

        if ($hasReferrer) {
            $referralQuote = $referrals->quoteFee($customer, $baseAmount, false, $feeType, applyDiscount: true);
            $referralDiscount = (float) $referralQuote['discount'];
            $afterPartner = (float) $referralQuote['after_discount'];
            $commission = (float) $referralQuote['commission'];
        } elseif ($affiliates->affiliate($customer)) {
            $affiliateQuote = $affiliates->quoteFee($customer, $baseAmount, $feeType);
            $affiliateDiscount = (float) $affiliateQuote['discount'];
            $afterPartner = (float) $affiliateQuote['after_discount'];
            $commission = (float) $affiliateQuote['commission'];
            $hasAffiliate = (bool) $affiliateQuote['has_affiliate'];
            $linked = $affiliates->affiliate($customer);
            if ($hasAffiliate && $linked && (filled($resolvedAffiliate) || app(AffiliateSettingsService::class)->autoApplyPromo())) {
                $promoValid = true;
                $appliedPromo = strtoupper(trim((string) ($linked->affiliate_code ?: $resolvedAffiliate)));
                $codeKind = 'affiliate';
            }
        }

        if (filled($resolvedPromo) && ! $useWallet) {
            $promo = $promotions->applyPromoCode($resolvedPromo, $feeType, $afterPartner);
            if ($promo['valid']) {
                $promoValid = true;
                $appliedPromo = strtoupper(trim($resolvedPromo));
                $codeKind = 'promo';
                $promoDiscount += (float) $promo['promotion_discount'];
                $afterPartner = (float) $promo['after_discount'];
            } else {
                $appliedPromo = strtoupper(trim($resolvedPromo));
                $promoValid = false;
                $codeKind = 'invalid';
            }
        } elseif (filled($promoCode) && blank($resolvedPromo) && blank($resolvedAffiliate)) {
            // Raw code entered but matched neither promo nor affiliate.
            $appliedPromo = strtoupper(trim((string) $promoCode));
            $promoValid = false;
            $codeKind = 'invalid';
        } elseif (filled($resolvedAffiliate) && ! $promoValid) {
            // Affiliate code provided but could not attach / no discount yet.
            $appliedPromo = strtoupper(trim((string) $resolvedAffiliate));
            $promoValid = $hasAffiliate;
            $codeKind = $hasAffiliate ? 'affiliate' : 'invalid';
        }
        // Promo / campaign discounts apply only when a code is entered — no silent auto-discount.

        $loyaltyDiscount = 0.0;
        $loyaltyRedemptionId = null;
        $loyaltyLabel = null;
        $loyaltyOptionKey = null;
        $loyaltyPointsCost = 0;
        $stack = app(GrowthPointsService::class)->allowRewardAndPromo();
        $canApplyLoyalty = $applyLoyalty && ! $useWallet;
        if ($canApplyLoyalty && ! $stack && $promoDiscount > 0) {
            $canApplyLoyalty = false;
        }
        if ($canApplyLoyalty) {
            $loyalty = app(LoyaltyRedemptionService::class)->previewDiscountForFee($customer, $feeType, $afterPartner);
            if (($loyalty['discount'] ?? 0) > 0) {
                $loyaltyDiscount = (float) $loyalty['discount'];
                $afterPartner = max(0, round($afterPartner - $loyaltyDiscount, 2));
                $loyaltyRedemptionId = $loyalty['redemption']?->id;
                $loyaltyLabel = $loyalty['label'] ?? null;
                $loyaltyOptionKey = $loyalty['option_key'] ?? null;
                $loyaltyPointsCost = (int) ($loyalty['points_cost'] ?? 0);
            }
        }

        $walletQuote = $referrals->quoteFee($customer, $afterPartner, $useWallet, $feeType, applyDiscount: false);

        return $this->formatQuote([
            'base' => round($baseAmount, 2),
            'canonical_base' => round($canonicalBase, 2),
            'staging_test' => $staging->isEnabled(),
            'referral_discount' => $referralDiscount,
            'affiliate_discount' => $affiliateDiscount,
            'promo_discount' => $promoDiscount,
            'loyalty_discount' => $loyaltyDiscount,
            'loyalty_redemption_id' => $loyaltyRedemptionId,
            'loyalty_label' => $loyaltyLabel,
            'loyalty_option_key' => $loyaltyOptionKey,
            'loyalty_points_cost' => $loyaltyPointsCost,
            'stack_with_promo' => $stack,
            'total_discount' => round($referralDiscount + $affiliateDiscount + $promoDiscount + $loyaltyDiscount, 2),
            'after_discount' => $afterPartner,
            'wallet_usable' => (float) $walletQuote['wallet_usable'],
            'wallet_applied' => (float) $walletQuote['wallet_applied'],
            'cash_due' => max(0, round($afterPartner - (float) $walletQuote['wallet_applied'], 2)),
            'commission' => $commission,
            'has_referrer' => $hasReferrer,
            'has_affiliate' => $hasAffiliate,
            'promo_code' => $appliedPromo,
            'promo_valid' => $promoValid,
            'code_kind' => $codeKind,
            'referrer' => $hasReferrer ? $referrals->referrer($customer) : null,
            'referred_by' => $hasAffiliate ? $affiliates->affiliate($customer)?->name : null,
            'affiliate_auto_applied' => $hasAffiliate && app(AffiliateSettingsService::class)->autoApplyPromo(),
            'affiliate_locked' => $hasAffiliate && app(AffiliateAttributionService::class)->isLocked($customer),
            'streak_discount' => 0.0,
        ], $feeType);
    }

    /**
     * @param  array<string, mixed>  $quote
     * @return array<string, mixed>
     */
    public function formatQuote(array $quote, string $feeType, string $currency = 'TZS'): array
    {
        $referrals = app(ReferralService::class);

        return array_merge($quote, [
            'discount' => (float) ($quote['total_discount'] ?? $quote['discount'] ?? 0),
            'currency' => $currency,
            'wallet_allowed' => $referrals->canUseWalletFor($feeType),
            'affiliate_percent' => $this->percentOfBase($quote),
            'lines' => $this->adjustmentLines($quote, $feeType, $currency),
        ]);
    }

    /** @param  array<string, mixed>  $quote */
    private function percentOfBase(array $quote): int
    {
        $base = (float) ($quote['base'] ?? 0);
        $discount = (float) ($quote['affiliate_discount'] ?? 0);
        if ($base <= 0 || $discount <= 0) {
            return 0;
        }

        return (int) round(($discount / $base) * 100);
    }

    /**
     * Authoritative line items for payment.show. The view must not recompute discounts.
     *
     * @param  array<string, mixed>  $quote
     * @return list<array{key: string, label: string, amount: float, kind: string}>
     */
    public function adjustmentLines(array $quote, string $feeType, string $currency = 'TZS'): array
    {
        $lines = [];
        $base = (float) ($quote['base'] ?? 0);
        $typeKey = "borrower.payment_types.{$feeType}";
        $typeLabel = __($typeKey);
        $lines[] = [
            'key' => 'base',
            'label' => $typeLabel !== $typeKey ? $typeLabel : __('borrower.payments_page.show.obligation_line'),
            'amount' => $base,
            'kind' => 'base',
            'display' => format_money($base),
        ];

        $affiliate = (float) ($quote['affiliate_discount'] ?? 0);
        if ($affiliate > 0) {
            $code = (string) ($quote['promo_code'] ?? '');
            $percent = (int) ($quote['affiliate_percent'] ?? $this->percentOfBase($quote));
            $label = $code !== ''
                ? __('borrower.payments_page.show.affiliate_discount_code', ['code' => $code, 'percent' => $percent])
                : __('borrower.payments_page.show.affiliate_discount');
            $lines[] = ['key' => 'affiliate', 'label' => $label, 'amount' => -$affiliate, 'kind' => 'discount', 'display' => '− '.format_money($affiliate)];
        }

        $promo = (float) ($quote['promo_discount'] ?? 0);
        if ($promo > 0) {
            $code = (string) ($quote['promo_code'] ?? '');
            $lines[] = [
                'key' => 'promo',
                'label' => $code !== ''
                    ? __('borrower.payments_page.show.promo_discount_code', ['code' => $code])
                    : __('borrower.payments_page.show.promo_discount'),
                'amount' => -$promo,
                'kind' => 'discount',
                'display' => '− '.format_money($promo),
            ];
        }

        $referral = (float) ($quote['referral_discount'] ?? 0);
        if ($referral > 0) {
            $lines[] = [
                'key' => 'referral',
                'label' => __('borrower.payments_page.show.referral_discount'),
                'amount' => -$referral,
                'kind' => 'discount',
                'display' => '− '.format_money($referral),
            ];
        }

        $loyalty = (float) ($quote['loyalty_discount'] ?? 0);
        if ($loyalty > 0) {
            $label = filled($quote['loyalty_label'] ?? null)
                ? __('borrower.payments_page.show.reward_discount_named', ['name' => $quote['loyalty_label']])
                : __('borrower.payments_page.show.reward_discount');
            $lines[] = ['key' => 'reward', 'label' => $label, 'amount' => -$loyalty, 'kind' => 'discount', 'display' => '− '.format_money($loyalty)];
        }

        $payable = (float) ($quote['cash_due'] ?? $base);
        $lines[] = [
            'key' => 'payable',
            'label' => __('borrower.payments_page.show.amount_to_pay'),
            'amount' => $payable,
            'kind' => 'total',
            'display' => format_money($payable),
        ];

        return $lines;
    }

    /**
     * Debit wallet / accrue affiliate commission after a fee payment is recorded.
     *
     * @param  array<string, mixed>  $quote
     */
    public function settle(
        Customer $customer,
        array $quote,
        string $feeType,
        ?string $refType = null,
        ?int $refId = null,
        bool $useWallet = false,
    ): void {
        $base = (float) ($quote['base'] ?? 0);
        $referrals = app(ReferralService::class);

        if ($feeType === 'application_fee') {
            app(GrowthPointsService::class)->awardFirstApplicationFee($customer);
        }

        if ($quote['has_referrer'] ?? false) {
            $referrals->settleFee($customer, $base, $useWallet, $feeType, $refType, $refId);
            $this->finalizeLoyaltyFromQuote($customer, $quote, $feeType, $refType, $refId);

            return;
        }

        app(AffiliateService::class)->accrueCommission(
            $customer,
            $base,
            $feeType,
            $refType,
            $refId,
        );

        if ($useWallet && ($quote['wallet_applied'] ?? 0) > 0) {
            $referrals->debit(
                $customer,
                (float) $quote['wallet_applied'],
                'Applied to '.str_replace('_', ' ', $feeType),
                $refType,
                $refId,
            );
        }

        $this->finalizeLoyaltyFromQuote($customer, $quote, $feeType, $refType, $refId);
    }

    /** @param  array<string, mixed>  $quote */
    private function finalizeLoyaltyFromQuote(
        Customer $customer,
        array $quote,
        string $feeType,
        ?string $refType,
        ?int $refId,
    ): void {
        if (($quote['loyalty_discount'] ?? 0) <= 0 && empty($quote['loyalty_option_key']) && empty($quote['loyalty_redemption_id'])) {
            return;
        }

        app(LoyaltyRedemptionService::class)->finalizeCheckoutReward(
            $customer,
            $feeType,
            $quote['loyalty_option_key'] ?? null,
            $refType,
            $refId,
        );
    }
}
