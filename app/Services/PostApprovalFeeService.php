<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\LoanApplicationPostApprovalFee;
use App\Models\LoanProductPostApprovalFee;
use App\Models\ManualPostApprovalFee;
use Illuminate\Support\Facades\DB;

class PostApprovalFeeService
{
    /** Generate fee rows for an approved application from product configuration. */
    public function generateForApplication(LoanApplication $application, bool $regenerate = false): int
    {
        return DB::transaction(function () use ($application, $regenerate) {
            $application->loadMissing('product');

            $hasTemplateFees = LoanApplicationPostApprovalFee::query()
                ->where('loan_application_id', $application->id)
                ->whereNull('manual_post_approval_fee_id')
                ->exists();

            if ($regenerate) {
                LoanApplicationPostApprovalFee::query()
                    ->where('loan_application_id', $application->id)
                    ->whereNull('manual_post_approval_fee_id')
                    ->where('status', '!=', 'paid')
                    ->delete();

                $hasTemplateFees = LoanApplicationPostApprovalFee::query()
                    ->where('loan_application_id', $application->id)
                    ->whereNull('manual_post_approval_fee_id')
                    ->exists();
            }

            if (! $hasTemplateFees) {
                $this->createTemplateFees($application);
            }

            $this->syncManualFees($application);

            return LoanApplicationPostApprovalFee::where('loan_application_id', $application->id)->count();
        });
    }

    /** Regenerate pending template fees when a loan product's post-approval config changes. */
    public function syncFromProductUpdate(\App\Models\LoanProduct $product): int
    {
        $applicationIds = LoanApplicationPostApprovalFee::query()
            ->whereNull('manual_post_approval_fee_id')
            ->where('status', 'pending')
            ->whereHas('application', fn ($q) => $q->where('loan_product_id', $product->id))
            ->pluck('loan_application_id')
            ->unique();

        $regenerated = 0;

        foreach (LoanApplication::query()->whereIn('id', $applicationIds)->get() as $application) {
            if ($this->canRegenerateTemplateFees($application)) {
                $this->generateForApplication($application, regenerate: true);
                $regenerated++;
            }
        }

        return $regenerated;
    }

    public function canRegenerateTemplateFees(LoanApplication $application): bool
    {
        return ! LoanApplicationPostApprovalFee::query()
            ->where('loan_application_id', $application->id)
            ->whereNull('manual_post_approval_fee_id')
            ->where('status', 'paid')
            ->exists();
    }

    private function createTemplateFees(LoanApplication $application): void
    {
        $principal = app(ApplicationOfferService::class)->effectiveAmount($application);
        $templates = LoanProductPostApprovalFee::query()
            ->where('loan_product_id', $application->loan_product_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        foreach ($templates as $fee) {
            $calculated = $this->calculateAmount($fee, $principal, $application);
            LoanApplicationPostApprovalFee::create([
                'loan_application_id'               => $application->id,
                'loan_product_post_approval_fee_id' => $fee->id,
                'code'                              => $fee->code,
                'name'                              => $fee->name,
                'fee_type'                          => $fee->fee_type,
                'configured_amount'                 => $fee->amount,
                'calculated_amount'                 => $calculated,
                'status'                            => 'pending',
            ]);
        }
    }

    public function addManualFee(
        LoanApplication $application,
        string $description,
        float $partnerCost,
        float $markupPercent,
        ?int $createdBy = null,
    ): ManualPostApprovalFee {
        return DB::transaction(function () use ($application, $description, $partnerCost, $markupPercent, $createdBy) {
            $borrowerAmount = round($partnerCost * (1 + ($markupPercent / 100)), 2);

            $manual = $application->manualPostApprovalFees()->create([
                'description'     => $description,
                'partner_cost'    => $partnerCost,
                'markup_percent'  => $markupPercent,
                'borrower_amount' => $borrowerAmount,
                'status'          => 'pending',
                'created_by'      => $createdBy,
            ]);

            LoanApplicationPostApprovalFee::create([
                'loan_application_id'         => $application->id,
                'manual_post_approval_fee_id' => $manual->id,
                'code'                        => 'MANUAL_'.$manual->id,
                'name'                        => $description,
                'fee_type'                    => 'fixed',
                'configured_amount'           => $partnerCost,
                'calculated_amount'           => $borrowerAmount,
                'status'                      => 'pending',
            ]);

            return $manual;
        });
    }

    public function syncManualFees(LoanApplication $application): int
    {
        $synced = 0;

        foreach ($application->manualPostApprovalFees()->get() as $manual) {
            $existing = LoanApplicationPostApprovalFee::query()
                ->where('manual_post_approval_fee_id', $manual->id)
                ->first();

            if ($existing) {
                if ($manual->isPaid() && ! $existing->isPaid()) {
                    $existing->update([
                        'status'      => 'paid',
                        'amount_paid' => $existing->calculated_amount,
                        'paid_at'     => $manual->paid_at ?? now(),
                    ]);
                }

                continue;
            }

            LoanApplicationPostApprovalFee::create([
                'loan_application_id'         => $application->id,
                'manual_post_approval_fee_id' => $manual->id,
                'code'                        => 'MANUAL_'.$manual->id,
                'name'                        => $manual->description,
                'fee_type'                    => 'fixed',
                'configured_amount'           => $manual->partner_cost,
                'calculated_amount'           => $manual->borrower_amount,
                'status'                      => $manual->status === 'paid' ? 'paid' : 'pending',
                'amount_paid'                 => $manual->status === 'paid' ? $manual->borrower_amount : 0,
                'paid_at'                     => $manual->paid_at,
            ]);
            $synced++;
        }

        return $synced;
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

            if ($fee->manual_post_approval_fee_id) {
                ManualPostApprovalFee::where('id', $fee->manual_post_approval_fee_id)->update([
                    'status'  => 'paid',
                    'paid_at' => now(),
                ]);
            }
        });

        $application = $application->fresh();
        app(AssetReservationService::class)->syncFromApplication($application);

        if ($this->allPaid($application)) {
            app(ApplicationDisbursementReadinessService::class)->syncBorrowerProgress($application->fresh());
            app(LoanAgreementService::class)->ensureLoanContractAfterFees($application->fresh());
        }

        return [
            'base_total' => $baseTotal,
            'settlement' => $settlement,
        ];
    }
}
