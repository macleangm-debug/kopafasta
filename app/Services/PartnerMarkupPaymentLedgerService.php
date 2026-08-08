<?php

namespace App\Services;

use App\Models\CustomerPayment;
use App\Models\JournalEntry;
use App\Models\LoanApplication;
use App\Models\LoanApplicationPostApprovalFee;
use App\Models\ManualPostApprovalFee;
use App\Models\Setting;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;

/**
 * Posts borrower fee payments that include platform markup:
 *   Dr Cash / customer clearing (total)
 *     Cr Revenue (markup only)
 *     Cr Partner payable (partner share)
 *     Cr Fee income (any remainder without markup split)
 */
class PartnerMarkupPaymentLedgerService
{
    /**
     * @return array{
     *   partner_share: float,
     *   markup_amount: float,
     *   remainder: float,
     *   revenue_account_id: ?int,
     *   lines_meta: list<array{account_id: int, credit: float, description: string}>
     * }|null
     */
    public function resolveSplit(CustomerPayment $payment): ?array
    {
        $total = round((float) $payment->amount, 2);
        if ($total <= 0) {
            return null;
        }

        return match ($payment->payment_type) {
            'valuation_fee' => $this->valuationSplit($payment, $total),
            'insurance_premium' => $this->insuranceSplit($payment, $total),
            'post_approval_fee' => $this->postApprovalSplit($payment, $total),
            default => null,
        };
    }

    public function post(CustomerPayment $payment, int $debitAccountId): ?JournalEntry
    {
        $split = $this->resolveSplit($payment);
        if ($split === null) {
            return null;
        }

        $total = round((float) $payment->amount, 2);
        $lines = [
            [
                'account_id' => $debitAccountId,
                'debit' => $total,
                'credit' => 0,
                'description' => 'Customer payment',
            ],
        ];

        foreach ($split['lines_meta'] as $credit) {
            if (($credit['credit'] ?? 0) <= 0 || empty($credit['account_id'])) {
                continue;
            }
            $lines[] = [
                'account_id' => (int) $credit['account_id'],
                'debit' => 0,
                'credit' => (float) $credit['credit'],
                'description' => (string) $credit['description'],
            ];
        }

        $credited = array_sum(array_map(fn ($l) => (float) ($l['credit'] ?? 0), $lines));
        if (round($credited, 2) !== round($total, 2)) {
            // Safety: fall back to single credit so verify never fails on rounding.
            return null;
        }

        try {
            return DB::transaction(function () use ($payment, $lines) {
                return app(LedgerService::class)->post(
                    $lines,
                    "Payment {$payment->reference} — {$payment->typeLabel()}",
                    $payment,
                    optional($payment->paid_at)->toDateString(),
                    'Markup fee split: platform revenue + partner payable.',
                );
            });
        } catch (\Throwable $e) {
            logger()->warning('Markup fee GL split not posted: '.$e->getMessage(), [
                'payment_id' => $payment->id,
            ]);

            return null;
        }
    }

    /** @return array<string, mixed>|null */
    private function valuationSplit(CustomerPayment $payment, float $total): ?array
    {
        $meta = (array) data_get($payment->provider_meta, 'fee_split', []);
        $partner = (float) ($meta['partner_share'] ?? 0);
        $markup = (float) ($meta['markup_amount'] ?? 0);

        if ($partner <= 0 && $markup <= 0) {
            $quote = app(ValuationPricingService::class)->quote();
            $partner = (float) $quote['partner_share'];
            $markup = (float) $quote['markup_amount'];
            $quotedTotal = (float) $quote['borrower_amount'];
            if ($quotedTotal > 0 && abs($quotedTotal - $total) > 0.5) {
                $scale = $total / $quotedTotal;
                $partner = round($partner * $scale, 2);
                $markup = round($total - $partner, 2);
            } elseif ($quotedTotal <= 0) {
                return null;
            } else {
                $markup = round($total - $partner, 2);
            }
        } else {
            [$partner, $markup] = $this->scalePair($partner, $markup, $total);
        }

        if ($markup <= 0 && $partner <= 0) {
            return null;
        }

        $revenueId = app(LedgerService::class)->valuationRevenueAccountId()
            ?? $this->settingAccount('fee_income_gl_account_id');
        $payableId = app(LedgerService::class)->recoveryPartnerPayableAccountId();

        $lines = [];
        if ($markup > 0 && $revenueId) {
            $lines[] = ['account_id' => $revenueId, 'credit' => $markup, 'description' => 'Valuation markup (platform)'];
        }
        if ($partner > 0 && $payableId) {
            $lines[] = ['account_id' => $payableId, 'credit' => $partner, 'description' => 'Valuer cost accrual'];
        }

        $remainder = round($total - $markup - $partner, 2);
        if ($remainder > 0 && $revenueId) {
            $lines[] = ['account_id' => $revenueId, 'credit' => $remainder, 'description' => 'Valuation fee'];
        }

        if ($lines === [] || ! $revenueId || ($partner > 0 && ! $payableId)) {
            return null;
        }

        return [
            'partner_share' => $partner,
            'markup_amount' => $markup,
            'remainder' => max(0, $remainder),
            'revenue_account_id' => $revenueId,
            'lines_meta' => $lines,
        ];
    }

    /** @return array<string, mixed>|null */
    private function insuranceSplit(CustomerPayment $payment, float $total): ?array
    {
        $ins = (array) data_get($payment->provider_meta, 'collateral_insurance', []);
        $partner = (float) ($ins['base_premium'] ?? $ins['partner_share'] ?? 0);
        $markup = (float) ($ins['markup_amount'] ?? 0);

        if (($partner <= 0 && $markup <= 0) && filled($ins['insured_value'] ?? null)) {
            $quote = app(CollateralInsurancePartnerService::class)->quote((int) $ins['insured_value']);
            $partner = (float) $quote['base_premium'];
            $markup = (float) $quote['markup_amount'];
        }

        if ($partner <= 0 && $markup <= 0) {
            return null;
        }

        [$partner, $markup] = $this->scalePair($partner, $markup, $total);

        $revenueId = $this->settingAccount('fee_income_gl_account_id');
        $payableId = app(LedgerService::class)->recoveryPartnerPayableAccountId();
        if (! $revenueId || ($partner > 0 && ! $payableId)) {
            return null;
        }

        $lines = [];
        if ($markup > 0) {
            $lines[] = ['account_id' => $revenueId, 'credit' => $markup, 'description' => 'Insurance markup (platform)'];
        }
        if ($partner > 0 && $payableId) {
            $lines[] = ['account_id' => $payableId, 'credit' => $partner, 'description' => 'Insurance partner cost accrual'];
        }
        $remainder = round($total - $markup - $partner, 2);
        if ($remainder > 0) {
            $lines[] = ['account_id' => $revenueId, 'credit' => $remainder, 'description' => 'Insurance premium'];
        }

        return [
            'partner_share' => $partner,
            'markup_amount' => $markup,
            'remainder' => max(0, $remainder),
            'revenue_account_id' => $revenueId,
            'lines_meta' => $lines,
        ];
    }

    /** @return array<string, mixed>|null */
    private function postApprovalSplit(CustomerPayment $payment, float $total): ?array
    {
        $meta = (array) data_get($payment->provider_meta, 'fee_split', []);
        $gpsMarkup = (float) ($meta['gps_markup'] ?? 0);
        $gpsPartner = (float) ($meta['gps_partner_share'] ?? 0);
        $otherMarkup = (float) ($meta['other_markup'] ?? 0);
        $otherPartner = (float) ($meta['other_partner_share'] ?? 0);
        $plainFees = (float) ($meta['plain_fees'] ?? 0);

        if ($gpsMarkup + $gpsPartner + $otherMarkup + $otherPartner + $plainFees <= 0) {
            $computed = $this->computePostApprovalBreakdown($payment);
            if ($computed === null) {
                return null;
            }
            $gpsMarkup = (float) $computed['gpsMarkup'];
            $gpsPartner = (float) $computed['gpsPartner'];
            $otherMarkup = (float) $computed['otherMarkup'];
            $otherPartner = (float) $computed['otherPartner'];
            $plainFees = (float) $computed['plainFees'];
        }

        $partner = $gpsPartner + $otherPartner;
        $markup = $gpsMarkup + $otherMarkup;
        $quoted = $partner + $markup + $plainFees;
        if ($quoted > 0 && abs($quoted - $total) > 0.5) {
            $scale = $total / $quoted;
            $gpsMarkup = round($gpsMarkup * $scale, 2);
            $gpsPartner = round($gpsPartner * $scale, 2);
            $otherMarkup = round($otherMarkup * $scale, 2);
            $otherPartner = round($otherPartner * $scale, 2);
            $plainFees = round($total - $gpsMarkup - $gpsPartner - $otherMarkup - $otherPartner, 2);
            $partner = $gpsPartner + $otherPartner;
            $markup = $gpsMarkup + $otherMarkup;
        }

        if ($markup <= 0 && $partner <= 0) {
            return null; // no markup split — use default single-credit posting
        }

        $gpsRevenueId = app(LedgerService::class)->gpsRevenueAccountId()
            ?? $this->settingAccount('fee_income_gl_account_id');
        $feeIncomeId = $this->settingAccount('application_fee_income_gl_account_id')
            ?? $this->settingAccount('fee_income_gl_account_id');
        $payableId = app(LedgerService::class)->recoveryPartnerPayableAccountId();

        if (! $feeIncomeId || ($partner > 0 && ! $payableId)) {
            return null;
        }

        $lines = [];
        if ($gpsMarkup > 0 && $gpsRevenueId) {
            $lines[] = ['account_id' => $gpsRevenueId, 'credit' => $gpsMarkup, 'description' => 'GPS markup (platform)'];
        }
        if ($otherMarkup > 0) {
            $lines[] = ['account_id' => $feeIncomeId, 'credit' => $otherMarkup, 'description' => 'Post-approval markup (platform)'];
        }
        if ($plainFees > 0) {
            $lines[] = ['account_id' => $feeIncomeId, 'credit' => $plainFees, 'description' => 'Post-approval fees'];
        }
        if ($partner > 0 && $payableId) {
            $lines[] = ['account_id' => $payableId, 'credit' => $partner, 'description' => 'Partner cost accrual'];
        }

        $credited = array_sum(array_column($lines, 'credit'));
        $gap = round($total - $credited, 2);
        if ($gap > 0) {
            $lines[] = ['account_id' => $feeIncomeId, 'credit' => $gap, 'description' => 'Post-approval fee'];
        }

        return [
            'partner_share' => $partner,
            'markup_amount' => $markup,
            'remainder' => $plainFees,
            'revenue_account_id' => $gpsRevenueId,
            'lines_meta' => $lines,
        ];
    }

    /**
     * @return array{
     *   gpsMarkup: float,
     *   gpsPartner: float,
     *   otherMarkup: float,
     *   otherPartner: float,
     *   plainFees: float
     * }|null
     */
    public function computePostApprovalBreakdown(CustomerPayment $payment): ?array
    {
        $application = null;
        if ($payment->source_type === LoanApplication::class && $payment->source_id) {
            $application = LoanApplication::query()->find($payment->source_id);
        }
        if (! $application) {
            return null;
        }

        $fees = LoanApplicationPostApprovalFee::query()
            ->where('loan_application_id', $application->id)
            ->whereIn('status', ['pending', 'paid'])
            ->get();

        $gpsMarkup = 0.0;
        $gpsPartner = 0.0;
        $otherMarkup = 0.0;
        $otherPartner = 0.0;
        $plainFees = 0.0;

        $gps = app(GpsPricingService::class);
        $months = (int) ($application->approved_tenure_months
            ?? $application->requested_tenure_months
            ?? $application->product?->default_tenure_months
            ?? 12);
        $gpsEstimate = $gps->estimate($months);

        foreach ($fees as $fee) {
            $amount = (float) $fee->calculated_amount;
            if ($amount <= 0) {
                continue;
            }

            $isGps = in_array(strtoupper((string) $fee->code), array_map('strtoupper', config('gps_pricing.fee_codes', ['GPS', 'GPS_BUNDLE', 'GPS_DEVICE'])), true)
                || $fee->fee_type === 'gps';

            if ($isGps) {
                $subtotal = (float) $gpsEstimate['device_cost'] + (float) $gpsEstimate['monitoring_total'];
                $mark = (float) $gpsEstimate['markup'];
                if ($subtotal + $mark > 0) {
                    $scale = $amount / ($subtotal + $mark);
                    $gpsPartner += round($subtotal * $scale, 2);
                    $gpsMarkup += round($mark * $scale, 2);
                } else {
                    $plainFees += $amount;
                }

                continue;
            }

            if ($fee->manual_post_approval_fee_id) {
                $manual = ManualPostApprovalFee::query()->find($fee->manual_post_approval_fee_id);
                if ($manual) {
                    $partnerCost = (float) $manual->partner_cost;
                    $borrower = (float) $manual->borrower_amount;
                    if ($borrower > 0 && $partnerCost >= 0) {
                        $scale = $amount / $borrower;
                        $otherPartner += round($partnerCost * $scale, 2);
                        $otherMarkup += round(max(0, $borrower - $partnerCost) * $scale, 2);

                        continue;
                    }
                }
            }

            $plainFees += $amount;
        }

        if ($gpsMarkup + $gpsPartner + $otherMarkup + $otherPartner <= 0) {
            return null;
        }

        return compact('gpsMarkup', 'gpsPartner', 'otherMarkup', 'otherPartner', 'plainFees');
    }

    /** @return array{0: float, 1: float} */
    private function scalePair(float $partner, float $markup, float $total): array
    {
        $sum = $partner + $markup;
        if ($sum <= 0) {
            return [0.0, 0.0];
        }
        if (abs($sum - $total) <= 0.5) {
            return [round($partner, 2), round($total - $partner, 2)];
        }
        $scale = $total / $sum;
        $partnerScaled = round($partner * $scale, 2);

        return [$partnerScaled, round($total - $partnerScaled, 2)];
    }

    private function settingAccount(string $key): ?int
    {
        $id = (int) (Setting::get("finance.{$key}") ?? 0);
        if ($id > 0 && ChartOfAccount::whereKey($id)->exists()) {
            return $id;
        }

        return null;
    }
}
