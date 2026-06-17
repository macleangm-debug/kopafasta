<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\Repayment;

class AssetLendingRepaymentGlService
{
    public function postSupplierPrincipalLiability(Loan $loan, Repayment $repayment): ?JournalEntry
    {
        $loan->loadMissing('product');

        if (! app(AssetLendingService::class)->isAssetLendingProduct($loan->product)) {
            return null;
        }

        $vendor = app(AssetLendingRepaymentService::class)->supplierForLoan($loan);
        if (! $vendor || ! app(AssetLendingService::class)->isManagedLoanSupplier($vendor)) {
            return null;
        }

        $principal = (float) $repayment->principal_component;
        if ($principal <= 0) {
            return null;
        }

        $existing = JournalEntry::query()
            ->where('source_type', Repayment::class)
            ->where('source_id', $repayment->id)
            ->where('memo', 'managed_loan_supplier_payable')
            ->first();

        if ($existing) {
            return $existing;
        }

        $ledger = app(LedgerService::class);
        $payableId = $ledger->supplierPayableAccountId();
        $clearingId = $ledger->assetLendingPrincipalClearingAccountId() ?: $ledger->loanReceivableAccountId();

        if (! $payableId || ! $clearingId) {
            return null;
        }

        $amount = round($principal, 2);

        try {
            return $ledger->post(
                [
                    ['account_id' => $clearingId, 'debit' => $amount, 'credit' => 0, 'description' => 'Principal to supplier · '.$loan->loan_number],
                    ['account_id' => $payableId, 'debit' => 0, 'credit' => $amount, 'description' => 'Supplier payable · '.$vendor->name],
                ],
                'Managed loan principal · '.$repayment->reference,
                $repayment,
                optional($repayment->paid_at)->toDateString() ?? now()->toDateString(),
                'managed_loan_supplier_payable',
            );
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
