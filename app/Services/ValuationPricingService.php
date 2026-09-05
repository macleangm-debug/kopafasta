<?php

namespace App\Services;

use App\Models\ChargesFee;
use App\Models\Partner;
use App\Models\Setting;

/**
 * Borrower valuation fee = partner base × (1 + markup%).
 * Partner earns base only; markup is platform valuation revenue.
 */
class ValuationPricingService
{
    /**
     * @return array{
     *   base_cost: int,
     *   markup_percent: float,
     *   markup_amount: int,
     *   borrower_amount: int,
     *   partner_share: int
     * }
     */
    public function quote(?Partner $partner = null): array
    {
        $defaults = app(PartnerDefaultsService::class);
        $base = (int) round($defaults->valuerBaseCost($partner));
        $markupPct = max(0, (float) $defaults->valuerMarkupPercent($partner));

        // Optional whole-TZS commercial target (set by pricing profile). When present, it is
        // the borrower payable; markup is the residual so partner + platform always reconcile.
        $target = Setting::get('partner_defaults.valuer.borrower_amount');
        if ($target !== null && $target !== '' && (float) $target > 0) {
            $borrower = (int) round((float) $target);
            $markup = $borrower - $base;
        } else {
            $markup = (int) round($base * ($markupPct / 100));
            $borrower = $base + $markup;
        }

        return [
            'base_cost' => $base,
            'markup_percent' => $markupPct,
            'markup_amount' => $markup,
            'borrower_amount' => $borrower,
            'partner_share' => $base,
        ];
    }

    /** Keep Fees hub VAL_FEE (and VAL_POST_FEE) aligned with Recovery valuer defaults. */
    public function syncChargesFees(): void
    {
        $amount = (float) $this->quote()['borrower_amount'];

        foreach (['VAL_FEE', 'VAL_POST_FEE'] as $code) {
            $fee = ChargesFee::query()->where('code', $code)->first();
            if ($fee) {
                $fee->update(['amount' => $amount]);
            }
        }
    }
}
