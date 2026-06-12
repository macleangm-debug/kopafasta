<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\LoanApplicationPostApprovalFee;
use App\Models\LoanProductPostApprovalFee;
use Illuminate\Support\Facades\DB;

class PostApprovalFeeService
{
    /** Generate fee rows for an approved application from product configuration. */
    public function generateForApplication(LoanApplication $application, bool $regenerate = false): int
    {
        return DB::transaction(function () use ($application, $regenerate) {
            $application->loadMissing('product');

            if ($regenerate) {
                LoanApplicationPostApprovalFee::where('loan_application_id', $application->id)->delete();
            } elseif (LoanApplicationPostApprovalFee::where('loan_application_id', $application->id)->exists()) {
                return LoanApplicationPostApprovalFee::where('loan_application_id', $application->id)->count();
            }

            $principal = app(ApplicationOfferService::class)->effectiveAmount($application);
            $templates = LoanProductPostApprovalFee::query()
                ->where('loan_product_id', $application->loan_product_id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            $count = 0;
            foreach ($templates as $fee) {
                $calculated = $this->calculateAmount($fee, $principal, $application);
                LoanApplicationPostApprovalFee::create([
                    'loan_application_id'             => $application->id,
                    'loan_product_post_approval_fee_id' => $fee->id,
                    'code'                            => $fee->code,
                    'name'                            => $fee->name,
                    'fee_type'                        => $fee->fee_type,
                    'configured_amount'               => $fee->amount,
                    'calculated_amount'               => $calculated,
                    'status'                          => 'pending',
                ]);
                $count++;
            }

            return $count;
        });
    }

    public function calculateAmount(LoanProductPostApprovalFee $fee, float $principal, ?LoanApplication $application = null): float
    {
        if ($this->isGpsFee($fee)) {
            $months = (int) ($application?->approved_tenure_months
                ?? $application?->requested_tenure_months
                ?? $application?->product?->default_tenure_months
                ?? 12);

            return app(GpsPricingService::class)->estimate($months)['total'];
        }

        if ($fee->fee_type === 'percent') {
            return round($principal * ((float) $fee->amount / 100), 2);
        }

        return round((float) $fee->amount, 2);
    }

    private function isGpsFee(LoanProductPostApprovalFee $fee): bool
    {
        if ($fee->fee_type === 'gps') {
            return true;
        }

        $codes = config('gps_pricing.fee_codes', ['GPS', 'GPS_BUNDLE', 'GPS_DEVICE']);

        return in_array(strtoupper((string) $fee->code), array_map('strtoupper', $codes), true);
    }

    public function totalDue(LoanApplication $application): float
    {
        return (float) LoanApplicationPostApprovalFee::where('loan_application_id', $application->id)
            ->where('status', '!=', 'paid')
            ->sum('calculated_amount');
    }

    public function allPaid(LoanApplication $application): bool
    {
        $fees = LoanApplicationPostApprovalFee::where('loan_application_id', $application->id)->get();
        if ($fees->isEmpty()) {
            return true;
        }

        return $fees->every(fn (LoanApplicationPostApprovalFee $f) => $f->isPaid());
    }

    public function markAllPaid(LoanApplication $application, ?\App\Models\Customer $payer = null, bool $useWallet = false): array
    {
        $application->loadMissing('postApprovalFees', 'customer');
        $payer ??= $application->customer;

        $pendingFees = LoanApplicationPostApprovalFee::where('loan_application_id', $application->id)
            ->where('status', 'pending')
            ->get();

        $baseTotal = (float) $pendingFees->sum('calculated_amount');
        $settlement = null;

        if ($payer && $baseTotal > 0) {
            $referrals = app(ReferralService::class);

            if ($referrals->referrer($payer)) {
                $settlement = $referrals->settleFee(
                    $payer,
                    $baseTotal,
                    $useWallet,
                    'post_approval_fee',
                    LoanApplication::class,
                    (int) $application->id,
                );
            } else {
                $affiliate = app(AffiliateService::class);
                $quote = $affiliate->quoteFee($payer, $baseTotal, 'post_approval_fee');
                $walletApplied = 0.0;

                if ($useWallet && $referrals->canUseWalletFor('post_approval_fee')) {
                    $walletQuote = $referrals->quoteFee($payer, $quote['after_discount'], true, 'post_approval_fee', applyDiscount: false);
                    $walletApplied = $walletQuote['wallet_applied'];

                    if ($walletApplied > 0) {
                        $referrals->debit(
                            $payer,
                            $walletApplied,
                            'Applied to post approval fee',
                            LoanApplication::class,
                            (int) $application->id,
                        );
                    }
                }

                $affiliate->accrueCommission(
                    $payer,
                    $baseTotal,
                    'post_approval_fee',
                    LoanApplication::class,
                    (int) $application->id,
                );

                $settlement = array_merge($quote, [
                    'wallet_applied' => $walletApplied,
                    'cash_due'       => max(0, round($quote['after_discount'] - $walletApplied, 2)),
                ]);
            }
        }

        $pendingFees->each(function (LoanApplicationPostApprovalFee $fee): void {
            $fee->update([
                'status'      => 'paid',
                'amount_paid' => $fee->calculated_amount,
                'paid_at'     => now(),
            ]);
        });

        $application = $application->fresh();
        app(AssetReservationService::class)->syncFromApplication($application);

        if ($this->allPaid($application)) {
            app(LoanAgreementService::class)->ensureLoanContractAfterFees($application);
        }

        return [
            'base_total' => $baseTotal,
            'settlement' => $settlement,
        ];
    }
}
