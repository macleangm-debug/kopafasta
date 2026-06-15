<?php

namespace App\Services;

use App\Models\AssetReservation;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\MarketplaceAsset;
use Illuminate\Support\Facades\DB;

class AssetReservationService
{
    public function createReservation(Customer $customer, MarketplaceAsset $asset, ?string $viewingDate = null, ?string $viewingTime = null): AssetReservation
    {
        $blocked = app(LoanPolicyService::class)->canUseAsset($asset, $customer);
        if ($blocked) {
            throw new \InvalidArgumentException($blocked);
        }

        $applicationFee = app(AssetMarketplaceFeeService::class)->applicationFeeAmount($customer);

        $reservation = AssetReservation::create([
            'customer_id'            => $customer->id,
            'marketplace_asset_id'   => $asset->id,
            'status'                 => ($viewingDate && $viewingTime) ? 'viewing_scheduled' : 'application_started',
            'viewing_date'           => $viewingDate,
            'viewing_time'           => $viewingTime,
            'reservation_fee_amount' => $applicationFee,
            'reservation_fee_status' => 'pending',
            'deposit_amount'         => $asset->customer_deposit ?: $asset->computeCustomerDeposit(),
            'deposit_status'         => 'pending',
        ]);

        $asset->lock();

        return $reservation;
    }

    public function startApplication(Customer $customer, MarketplaceAsset $asset): AssetReservation
    {
        $existing = $this->activeForCustomer($customer, $asset);
        if ($existing) {
            return $existing;
        }

        return $this->createReservation($customer, $asset);
    }

    public function scheduleViewing(AssetReservation $reservation, string $viewingDate, string $viewingTime): AssetReservation
    {
        $reservation->update([
            'viewing_date' => $viewingDate,
            'viewing_time' => $viewingTime,
            'status'       => 'viewing_scheduled',
        ]);

        return $reservation->refresh();
    }

    public function activeForCustomer(Customer $customer, MarketplaceAsset $asset): ?AssetReservation
    {
        return AssetReservation::query()
            ->where('customer_id', $customer->id)
            ->where('marketplace_asset_id', $asset->id)
            ->whereNotIn('status', ['released', 'cancelled'])
            ->latest()
            ->first();
    }

    public function markReservationFeePaid(AssetReservation $reservation, ?string $paymentReference = null): AssetReservation
    {
        $reservation->update([
            'reservation_fee_status'        => 'paid',
            'reservation_fee_paid_at'       => now(),
            'reservation_payment_reference' => $paymentReference,
            'status'                        => 'reservation_fee_paid',
        ]);

        return $reservation->refresh();
    }

    public function markViewingCompleted(AssetReservation $reservation): AssetReservation
    {
        $reservation->update([
            'viewing_completed_at' => now(),
            'status'               => 'viewing_completed',
        ]);

        return $reservation->refresh();
    }

    public function markInterestConfirmed(AssetReservation $reservation): AssetReservation
    {
        $reservation->update(['status' => 'interest_confirmed']);

        return $reservation->refresh();
    }

    public function markDepositPaid(AssetReservation $reservation, ?string $paymentReference = null): AssetReservation
    {
        $reservation->update([
            'deposit_status'            => 'paid',
            'deposit_paid_at'           => now(),
            'deposit_payment_reference' => $paymentReference,
            'status'                    => 'deposit_paid',
        ]);

        $this->accrueSupplierDeposit($reservation->fresh(['asset.vendor']));
        app(AssetLendingRevenuePostingService::class)->postDepositMarkup($reservation->fresh(['asset']));

        return $reservation->refresh();
    }

    public function accrueSupplierDeposit(AssetReservation $reservation): void
    {
        $reservation->loadMissing('asset.vendor');
        $asset = $reservation->asset;
        $vendor = $asset?->vendor;

        if (! $vendor || ! $reservation->deposit_paid_at) {
            return;
        }

        if (app(UpfrontSettlementService::class)->isUpfrontSupplier($vendor)) {
            return;
        }

        $supplierDeposit = (int) round((float) ($asset->supplier_deposit ?? 0));
        if ($supplierDeposit <= 0) {
            return;
        }

        $exists = \App\Models\VendorPayment::query()
            ->where('vendor_id', $vendor->id)
            ->where('source_type', 'supplier_deposit')
            ->where('source_id', $reservation->id)
            ->exists();

        if ($exists) {
            return;
        }

        app(PartnerSettlementService::class)->accrue(
            $vendor,
            $supplierDeposit,
            'supplier_deposit',
            $reservation->id,
            'Supplier deposit payout for '.$asset->title,
        );
    }

    public function linkApplication(AssetReservation $reservation, LoanApplication $application): AssetReservation
    {
        $reservation->update([
            'loan_application_id' => $application->id,
            'status'              => 'application_submitted',
        ]);

        $reservation->asset?->lock();

        return $reservation->refresh();
    }

    public function syncFromApplication(LoanApplication $application): void
    {
        $reservation = AssetReservation::query()
            ->where('loan_application_id', $application->id)
            ->first();

        if (! $reservation) {
            return;
        }

        if (in_array($application->status, ['rejected', 'withdrawn'], true)) {
            $reservation->update(['status' => 'cancelled']);
            $this->unlockAssetIfIdle($reservation->fresh(['asset']));

            return;
        }

        $status = match ($application->status) {
            'approved'  => 'approved',
            'disbursed' => 'released',
            default     => $reservation->status,
        };

        if ($status !== $reservation->status) {
            $reservation->update([
                'status'      => $status,
                'released_at' => $status === 'released' ? now() : $reservation->released_at,
            ]);
        }

        if ($status === 'approved' && app(PostApprovalFeeService::class)->allPaid($application)) {
            $reservation->update(['status' => 'post_approval_fees_paid']);
            app(UpfrontSettlementService::class)->accrueIfNeeded($reservation->fresh(['asset.vendor']), 'post_approval_fees');
        }

        if (in_array($status, ['approved', 'post_approval_fees_paid'], true)) {
            app(UpfrontSettlementService::class)->accrueIfNeeded($reservation->fresh(['asset.vendor']), 'approval');
        }

        if ($status === 'released') {
            $policy = app(LoanPolicyService::class);
            if ($policy->settings()['allow_asset_reuse']) {
                $this->unlockAssetIfIdle($reservation->fresh(['asset']));
            }
        }
    }

    public function markHandoverComplete(LoanApplication $application): void
    {
        $reservation = AssetReservation::query()
            ->where('loan_application_id', $application->id)
            ->first();

        if (! $reservation) {
            return;
        }

        $reservation->update([
            'status'      => 'released',
            'released_at' => now(),
        ]);

        app(UpfrontSettlementService::class)->accrueIfNeeded($reservation->fresh(['asset.vendor']), 'handover');

        $policy = app(LoanPolicyService::class);
        if ($policy->settings()['allow_asset_reuse']) {
            $this->unlockAssetIfIdle($reservation->fresh(['asset']));
        }
    }

    public function reservationForApplication(LoanApplication $application): ?AssetReservation
    {
        return AssetReservation::query()
            ->where('loan_application_id', $application->id)
            ->with('asset')
            ->first();
    }

    public function handoverReady(AssetReservation $reservation): bool
    {
        $reservation->loadMissing('asset');
        $category = $reservation->asset?->category;
        $reqs = app(AssetLendingService::class)->categoryRequirements($category);

        if ((bool) ($reqs['gps_required'] ?? false)) {
            return in_array($reservation->status, ['insurance_active', 'released'], true);
        }

        return in_array($reservation->status, ['post_approval_fees_paid', 'insurance_active', 'released'], true);
    }

    public function unlockAssetIfIdle(AssetReservation $reservation): void
    {
        $asset = $reservation->asset;
        if (! $asset) {
            return;
        }

        $otherActive = AssetReservation::query()
            ->where('marketplace_asset_id', $asset->id)
            ->where('id', '!=', $reservation->id)
            ->whereNotIn('status', ['released', 'cancelled'])
            ->exists();

        if (! $otherActive) {
            $asset->unlock();
        }
    }

    public function advance(AssetReservation $reservation, string $action): AssetReservation
    {
        match ($action) {
            'skip_viewing' => $this->markViewingCompleted($reservation),
            'complete_viewing' => $this->markViewingCompleted($reservation),
            'confirm_interest' => $this->markInterestConfirmed($reservation),
            'pay_reservation_fee' => $this->markReservationFeePaid($reservation),
            'pay_deposit' => $this->markDepositPaid($reservation),
            'gps_installation' => $reservation->update(['status' => 'gps_installation']),
            'insurance_active' => $reservation->update(['status' => 'insurance_active']),
            'release' => $reservation->update(['status' => 'released', 'released_at' => now()]),
            'cancel' => (function () use ($reservation): void {
                $reservation->update(['status' => 'cancelled']);
                $this->unlockAssetIfIdle($reservation->fresh(['asset']));
            })(),
            default => null,
        };

        return $reservation->refresh();
    }

    public function syncGpsFromTask(\App\Models\VendorTask $task): void
    {
        if (! str_contains((string) $task->task_type, 'gps') || ! $task->loan_application_id) {
            return;
        }

        $reservation = AssetReservation::query()
            ->where('loan_application_id', $task->loan_application_id)
            ->first();

        if (! $reservation || $reservation->status === 'cancelled') {
            return;
        }

        if (in_array($reservation->status, ['insurance_active', 'released'], true)) {
            return;
        }

        $this->advance($reservation, 'gps_installation');

        if (filled($task->gps_serial) && $reservation->asset) {
            $reservation->asset->update(['serial_number' => $task->gps_serial]);
        }
    }

    /** @return list<array{label: string, done: bool, current: bool, phase?: string}> */
    public function steps(AssetReservation $reservation): array
    {
        $reservation->loadMissing(['loanApplication.postApprovalFees', 'loanApplication.loan', 'asset']);
        $application = $reservation->loanApplication;
        $readiness = app(ApplicationDisbursementReadinessService::class);
        $reqs = app(AssetLendingService::class)->categoryRequirements($reservation->asset?->category);
        $gpsRequired = (bool) ($reqs['gps_required'] ?? false);
        $insuranceRequired = (bool) ($reqs['insurance_required'] ?? false);

        $labels = [
            ['key' => 'start', 'label' => __('borrower.marketplace.steps.start'), 'phase' => 'reservation'],
            ['key' => 'viewing', 'label' => __('borrower.marketplace.steps.viewing'), 'phase' => 'reservation'],
            ['key' => 'viewing_done', 'label' => __('borrower.marketplace.steps.viewing_done'), 'phase' => 'reservation'],
            ['key' => 'interest', 'label' => __('borrower.marketplace.steps.interest'), 'phase' => 'reservation'],
            ['key' => 'application_fee', 'label' => __('borrower.marketplace.steps.application_fee'), 'phase' => 'reservation'],
            ['key' => 'deposit', 'label' => __('borrower.marketplace.steps.deposit'), 'phase' => 'reservation'],
            ['key' => 'loan_application', 'label' => __('borrower.marketplace.steps.loan_application'), 'phase' => 'loan'],
            ['key' => 'loan_offer', 'label' => __('borrower.marketplace.steps.loan_offer'), 'phase' => 'loan'],
            ['key' => 'post_approval_fees', 'label' => __('borrower.marketplace.steps.post_approval_fees'), 'phase' => 'loan'],
            ['key' => 'contract', 'label' => __('borrower.marketplace.steps.contract'), 'phase' => 'loan'],
        ];

        if ($gpsRequired) {
            $labels[] = ['key' => 'gps', 'label' => __('borrower.marketplace.steps.gps'), 'phase' => 'handover'];
        }

        if ($insuranceRequired) {
            $labels[] = ['key' => 'insurance', 'label' => __('borrower.marketplace.steps.insurance'), 'phase' => 'handover'];
        }

        $labels[] = ['key' => 'handover', 'label' => __('borrower.marketplace.steps.handover'), 'phase' => 'handover'];

        $currentKey = $this->resolvePipelineStepKey($reservation, $application, $readiness, $gpsRequired, $insuranceRequired);
        $currentIndex = max(0, collect($labels)->search(fn (array $row) => $row['key'] === $currentKey));
        $completed = $currentKey === 'handover' && (string) $reservation->status === 'released';

        return collect($labels)->map(function (array $row, int $i) use ($currentIndex, $completed) {
            return [
                'label'   => $row['label'],
                'phase'   => $row['phase'],
                'done'    => $completed ? $i <= $currentIndex : $i < $currentIndex,
                'current' => ! $completed && $i === $currentIndex,
            ];
        })->values()->all();
    }

    private function resolvePipelineStepKey(
        AssetReservation $reservation,
        ?LoanApplication $application,
        ApplicationDisbursementReadinessService $readiness,
        bool $gpsRequired,
        bool $insuranceRequired,
    ): string {
        $status = (string) $reservation->status;

        if ($status === 'released') {
            return 'handover';
        }

        if ($status === 'insurance_active') {
            return 'handover';
        }

        if ($status === 'gps_installation') {
            return $insuranceRequired ? 'insurance' : 'handover';
        }

        if ($application) {
            if ($readiness->contractSigned($application)) {
                if ($gpsRequired && ! in_array($status, ['insurance_active', 'released'], true)) {
                    return 'gps';
                }

                if ($insuranceRequired && $status !== 'insurance_active') {
                    return 'insurance';
                }

                if ($readiness->canMarkAssetHandover($application)) {
                    return 'handover';
                }

                return $gpsRequired ? 'gps' : ($insuranceRequired ? 'insurance' : 'handover');
            }

            if ($readiness->needsPostApprovalFees($application) || $status === 'post_approval_fees_paid') {
                return $readiness->feesPaid($application) ? 'contract' : 'post_approval_fees';
            }

            if ($readiness->needsBorrowerSignature($application) || in_array($application->offer_status, ['pending_borrower'], true)) {
                return 'loan_offer';
            }

            if ($readiness->offerSigned($application)) {
                return 'post_approval_fees';
            }

            return 'loan_application';
        }

        return match ($status) {
            'application_started' => 'start',
            'viewing_scheduled' => 'viewing',
            'viewing_completed' => 'viewing_done',
            'interest_confirmed' => 'interest',
            'reservation_fee_paid' => 'application_fee',
            'deposit_paid' => 'deposit',
            'application_submitted', 'approved', 'post_approval_fees_paid' => 'loan_application',
            default => 'start',
        };
    }
}
