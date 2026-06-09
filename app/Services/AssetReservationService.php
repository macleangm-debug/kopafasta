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
        }

        if ($status === 'released') {
            $policy = app(LoanPolicyService::class);
            if ($policy->settings()['allow_asset_reuse']) {
                $this->unlockAssetIfIdle($reservation->fresh(['asset']));
            }
        }
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

    /** @return list<array{label: string, done: bool, current: bool}> */
    public function steps(AssetReservation $reservation): array
    {
        $index = $reservation->stepIndex();

        $labels = [
            __('borrower.marketplace.steps.start'),
            __('borrower.marketplace.steps.viewing'),
            __('borrower.marketplace.steps.viewing_done'),
            __('borrower.marketplace.steps.interest'),
            __('borrower.marketplace.steps.application_fee'),
            __('borrower.marketplace.steps.deposit'),
            __('borrower.marketplace.steps.loan_approval'),
            __('borrower.marketplace.steps.release'),
        ];

        return collect($labels)->map(fn (string $label, int $i) => [
            'label'   => $label,
            'done'    => $index >= $i + 1,
            'current' => $index === $i + 1,
        ])->values()->all();
    }
}
