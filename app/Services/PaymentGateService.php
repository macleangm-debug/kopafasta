<?php

namespace App\Services;

use App\Models\Customer;
use App\Services\AffiliateAttributionService;
use App\Services\AffiliateService;
use App\Services\AffiliateSettingsService;

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
        $stack = app(GrowthPointsService::class)->allowRewardAndPromo();
        $canApplyLoyalty = $applyLoyalty && ! $useWallet;
        if ($canApplyLoyalty && ! $stack && $promoDiscount > 0) {
            $canApplyLoyalty = false;
        }
        if ($canApplyLoyalty) {
            $loyalty = app(LoyaltyRedemptionService::class)->discountForFee($customer, $feeType, $afterPartner);
            if (($loyalty['discount'] ?? 0) > 0) {
                $loyaltyDiscount = (float) $loyalty['discount'];
                $afterPartner = max(0, round($afterPartner - $loyaltyDiscount, 2));
                $loyaltyRedemptionId = $loyalty['redemption']?->id;
                $loyaltyLabel = $loyalty['label'] ?? null;
            }
        }

        $walletQuote = $referrals->quoteFee($customer, $afterPartner, $useWallet, $feeType, applyDiscount: false);

        return $this->formatQuote([
            'base'                  => round($baseAmount, 2),
            'referral_discount'     => $referralDiscount,
            'affiliate_discount'    => $affiliateDiscount,
            'promo_discount'        => $promoDiscount,
            'loyalty_discount'      => $loyaltyDiscount,
            'loyalty_redemption_id' => $loyaltyRedemptionId,
            'loyalty_label'         => $loyaltyLabel,
            'total_discount'        => round($referralDiscount + $affiliateDiscount + $promoDiscount + $loyaltyDiscount, 2),
            'after_discount'        => $afterPartner,
            'wallet_usable'         => (float) $walletQuote['wallet_usable'],
            'wallet_applied'        => (float) $walletQuote['wallet_applied'],
            'cash_due'              => max(0, round($afterPartner - (float) $walletQuote['wallet_applied'], 2)),
            'commission'            => $commission,
            'has_referrer'          => $hasReferrer,
            'has_affiliate'         => $hasAffiliate,
            'promo_code'            => $appliedPromo,
            'promo_valid'           => $promoValid,
            'code_kind'             => $codeKind,
            'referrer'              => $hasReferrer ? $referrals->referrer($customer) : null,
            'referred_by'           => $hasAffiliate ? $affiliates->affiliate($customer)?->name : null,
            'affiliate_auto_applied'=> $hasAffiliate && app(AffiliateSettingsService::class)->autoApplyPromo(),
            'affiliate_locked'      => $hasAffiliate && app(AffiliateAttributionService::class)->isLocked($customer),
            'streak_discount'       => 0.0,
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
            'discount'        => (float) ($quote['total_discount'] ?? $quote['discount'] ?? 0),
            'currency'        => $currency,
            'wallet_allowed'  => $referrals->canUseWalletFor($feeType),
        ]);
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

            if (! empty($quote['loyalty_redemption_id'])) {
                $redemption = \App\Models\LoyaltyRedemption::find($quote['loyalty_redemption_id']);
                if ($redemption && $redemption->isActive()) {
                    app(LoyaltyRedemptionService::class)->markUsed($redemption, $refType, $refId);
                }
            }

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

        if (! empty($quote['loyalty_redemption_id'])) {
            $redemption = \App\Models\LoyaltyRedemption::find($quote['loyalty_redemption_id']);
            if ($redemption && $redemption->isActive()) {
                app(LoyaltyRedemptionService::class)->markUsed($redemption, $refType, $refId);
            }
        }
    }
}
