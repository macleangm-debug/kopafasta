<?php

namespace App\Services;

use App\Models\AssetReservation;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\MarketplaceAsset;
use Illuminate\Support\Facades\DB;

class AssetReservationService
{
    public function createReservation(Customer $customer, MarketplaceAsset $asset, string $viewingDate, string $viewingTime): AssetReservation
    {
        return AssetReservation::create([
            'customer_id'            => $customer->id,
            'marketplace_asset_id'   => $asset->id,
            'status'                 => 'viewing_scheduled',
            'viewing_date'           => $viewingDate,
            'viewing_time'           => $viewingTime,
            'reservation_fee_amount' => config('asset_marketplace.reservation_fee', 50000),
            'reservation_fee_status' => 'pending',
            'deposit_amount'         => $asset->customer_deposit ?: $asset->computeCustomerDeposit(),
            'deposit_status'         => 'pending',
        ]);
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

        $status = match ($application->status) {
            'approved' => 'approved',
            'disbursed' => 'released',
            default => $reservation->status,
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
    }

    public function advance(AssetReservation $reservation, string $action): AssetReservation
    {
        match ($action) {
            'complete_viewing' => $this->markViewingCompleted($reservation),
            'confirm_interest' => $this->markInterestConfirmed($reservation),
            'pay_reservation_fee' => $this->markReservationFeePaid($reservation),
            'pay_deposit' => $this->markDepositPaid($reservation),
            'gps_installation' => $reservation->update(['status' => 'gps_installation']),
            'insurance_active' => $reservation->update(['status' => 'insurance_active']),
            'release' => $reservation->update(['status' => 'released', 'released_at' => now()]),
            'cancel' => $reservation->update(['status' => 'cancelled']),
            default => null,
        };

        return $reservation->refresh();
    }

    /** @return list<array{label: string, done: bool, current: bool}> */
    public function steps(AssetReservation $reservation): array
    {
        $index = $reservation->stepIndex();

        return [
            ['label' => 'Apply for asset & schedule viewing', 'done' => $index >= 1, 'current' => $index === 1],
            ['label' => 'Complete viewing', 'done' => $index >= 2, 'current' => $index === 2],
            ['label' => 'Confirm interest', 'done' => $index >= 3, 'current' => $index === 3],
            ['label' => 'Pay application fee', 'done' => $index >= 4, 'current' => $index === 4],
            ['label' => 'Pay deposit', 'done' => $index >= 5, 'current' => $index === 5],
            ['label' => 'Loan approval & post-approval fees', 'done' => $index >= 6, 'current' => $index === 6],
            ['label' => 'GPS, insurance & asset release', 'done' => $index >= 7, 'current' => $index === 7],
        ];
    }
}
