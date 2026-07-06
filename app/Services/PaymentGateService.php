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
        bool $useStreak = false,
    ): array {
        if (filled($affiliateCode) && ! app(ReferralService::class)->referrer($customer) && ! $customer->affiliate_partner_id) {
            app(AffiliateService::class)->attachAffiliate($customer, $affiliateCode);
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
        }

        if (filled($promoCode)) {
            $promo = $promotions->applyPromoCode($promoCode, $feeType, $afterPartner);
            if ($promo['valid']) {
                $promoValid = true;
                $appliedPromo = strtoupper(trim($promoCode));
                $promoDiscount += (float) $promo['promotion_discount'];
                $afterPartner = (float) $promo['after_discount'];
            }
        } elseif (! $hasReferrer) {
            $autoPromo = $promotions->applyAfter($feeType, $afterPartner);
            if ($autoPromo['promotion_discount'] > 0) {
                $promoDiscount = (float) $autoPromo['promotion_discount'];
                $afterPartner = (float) $autoPromo['after_discount'];
            }
        }

        $loyaltyDiscount = 0.0;
        $loyaltyRedemptionId = null;
        $loyalty = app(LoyaltyRedemptionService::class)->discountForFee($customer, $feeType, $afterPartner);
        if (($loyalty['discount'] ?? 0) > 0) {
            $loyaltyDiscount = (float) $loyalty['discount'];
            $afterPartner = max(0, round($afterPartner - $loyaltyDiscount, 2));
            $loyaltyRedemptionId = $loyalty['redemption']?->id;
        }

        $streakDiscount = 0.0;
        $streakPercent = 0.0;
        if ($useStreak) {
            $streak = app(RepaymentStreakRewardService::class)->discountForFee($customer, $feeType, $afterPartner);
            $streakDiscount = (float) ($streak['discount'] ?? 0);
            $streakPercent = (float) ($streak['percent'] ?? 0);
            $afterPartner = max(0, round($afterPartner - $streakDiscount, 2));
        }

        $walletQuote = $referrals->quoteFee($customer, $afterPartner, $useWallet && ! $useStreak, $feeType, applyDiscount: false);

        return $this->formatQuote([
            'base'                => round($baseAmount, 2),
            'referral_discount'     => $referralDiscount,
            'affiliate_discount'    => $affiliateDiscount,
            'promo_discount'        => $promoDiscount,
            'loyalty_discount'      => $loyaltyDiscount,
            'loyalty_redemption_id' => $loyaltyRedemptionId,
            'streak_discount'       => $streakDiscount,
            'streak_percent'        => $streakPercent,
            'total_discount'        => round($referralDiscount + $affiliateDiscount + $promoDiscount + $loyaltyDiscount + $streakDiscount, 2),
            'after_discount'        => $afterPartner,
            'wallet_usable'         => (float) $walletQuote['wallet_usable'],
            'wallet_applied'        => (float) $walletQuote['wallet_applied'],
            'cash_due'              => max(0, round($afterPartner - (float) $walletQuote['wallet_applied'], 2)),
            'commission'            => $commission,
            'has_referrer'          => $hasReferrer,
            'has_affiliate'         => $hasAffiliate,
            'promo_code'            => $appliedPromo,
            'promo_valid'           => $promoValid,
            'referrer'              => $hasReferrer ? $referrals->referrer($customer) : null,
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

        if ($quote['has_referrer'] ?? false) {
            $referrals->settleFee($customer, $base, $useWallet, $feeType, $refType, $refId);

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
