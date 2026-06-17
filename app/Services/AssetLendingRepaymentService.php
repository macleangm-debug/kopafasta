<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Repayment;
use App\Models\Vendor;
use App\Models\VendorPayment;

class AssetLendingRepaymentService
{
    public function supplierForLoan(Loan $loan): ?Vendor
    {
        $loan->loadMissing('application.assetReservation.asset.vendor');

        return $loan->application?->assetReservation?->asset?->vendor;
    }

    public function accruePrincipalPayout(Loan $loan, Repayment $repayment): ?VendorPayment
    {
        $loan->loadMissing('product');

        if (! app(AssetLendingService::class)->isAssetLendingProduct($loan->product)) {
            return null;
        }

        $vendor = $this->supplierForLoan($loan);
        if (! $vendor || ! app(AssetLendingService::class)->isManagedLoanSupplier($vendor)) {
            return null;
        }

        $principal = (float) $repayment->principal_component;
        if ($principal <= 0) {
            return null;
        }

        $exists = VendorPayment::query()
            ->where('vendor_id', $vendor->id)
            ->where('source_type', 'managed_loan_repayment')
            ->where('source_id', $repayment->id)
            ->exists();

        if ($exists) {
            return null;
        }

        $payment = app(PartnerSettlementService::class)->accrue(
            $vendor,
            (int) round($principal),
            'managed_loan_repayment',
            $repayment->id,
            'Principal repayment · '.($loan->loan_number ?? 'loan #'.$loan->id),
        );

        try {
            app(AssetLendingRepaymentGlService::class)->postSupplierPrincipalLiability($loan, $repayment);
        } catch (\Throwable $e) {
            report($e);
        }

        return $payment;
    }
}
