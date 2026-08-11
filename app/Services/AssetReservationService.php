<?php

namespace App\Services;

use App\Models\AssetReservation;
use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\MarketplaceAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

        // Keep the listing open until deposit is paid (first-come, first-served).
        // Cash sales or a faster depositor can still take the asset.

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
        if ($reservation->status !== 'reservation_fee_paid') {
            throw new \InvalidArgumentException(__('borrower.marketplace.viewing_after_fee_only'));
        }

        $reservation->update([
            'viewing_date' => $viewingDate,
            'viewing_time' => $viewingTime,
            'status'       => 'viewing_scheduled',
        ]);

        $reservation = $reservation->refresh()->loadMissing(['customer', 'asset.vendor']);
        $this->notifyViewingScheduled($reservation);

        return $reservation;
    }

    private function notifyViewingScheduled(AssetReservation $reservation): void
    {
        $customer = $reservation->customer;
        $asset = $reservation->asset;
        if (! $customer || ! $asset) {
            return;
        }

        $when = trim(($reservation->viewing_date?->format('d M Y') ?? '').' '.($reservation->viewing_time ?? ''));
        $assetUrl = route('site.borrower.marketplace.reserve', $asset->slug ?: $asset->id);
        $notifier = app(NotificationService::class);

        $notifier->notifyInApp(
            $customer,
            __('borrower.marketplace.viewing_scheduled_notice', [
                'asset' => $asset->title,
                'when' => $when,
            ]),
            'marketplace',
            'marketplace_viewing_scheduled',
            __('borrower.marketplace.viewing_scheduled_title'),
            $assetUrl,
            __('borrower.marketplace.viewing_scheduled_cta'),
        );

        $notifier->notifyCustomer($customer, 'marketplace_viewing_scheduled', [
            'name' => $customer->first_name ?: $customer->full_name,
            'asset_title' => $asset->title,
            'viewing_when' => $when,
            'reserve_url' => $assetUrl,
            '_fallback_subject' => __('borrower.marketplace.viewing_scheduled_title'),
            '_fallback_body' => __('borrower.marketplace.viewing_scheduled_notice', [
                'asset' => $asset->title,
                'when' => $when,
            ]).' '.$assetUrl,
        ]);
    }

    public function canScheduleViewing(AssetReservation $reservation): bool
    {
        return $reservation->status === 'reservation_fee_paid'
            && ! $reservation->viewing_completed_at;
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
        $payload = ['viewing_completed_at' => now()];

        if ($reservation->reservation_fee_status === 'paid') {
            $payload['status'] = 'reservation_fee_paid';
        } else {
            $payload['status'] = 'viewing_completed';
        }

        $reservation->update($payload);

        return $reservation->refresh();
    }

    public function markInterestConfirmed(AssetReservation $reservation): AssetReservation
    {
        $reservation->update(['status' => 'interest_confirmed']);

        return $reservation->refresh();
    }

    public function markDepositPaid(AssetReservation $reservation, ?string $paymentReference = null): AssetReservation
    {
        return DB::transaction(function () use ($reservation, $paymentReference) {
            $reservation = AssetReservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $asset = MarketplaceAsset::query()->lockForUpdate()->find($reservation->marketplace_asset_id);

            if ($asset && ! $asset->isAvailable()) {
                $ownsLock = AssetReservation::query()
                    ->where('marketplace_asset_id', $asset->id)
                    ->where('id', $reservation->id)
                    ->where('deposit_status', 'paid')
                    ->exists();

                if (! $ownsLock && $reservation->deposit_status !== 'paid') {
                    throw ValidationException::withMessages([
                        'payment' => __('borrower.marketplace.deposit_lost_to_other'),
                    ]);
                }
            }

            $otherDeposit = AssetReservation::query()
                ->where('marketplace_asset_id', $reservation->marketplace_asset_id)
                ->where('id', '!=', $reservation->id)
                ->where('deposit_status', 'paid')
                ->whereNotIn('status', ['cancelled'])
                ->exists();

            if ($otherDeposit && $reservation->deposit_status !== 'paid') {
                throw ValidationException::withMessages([
                    'payment' => __('borrower.marketplace.deposit_lost_to_other'),
                ]);
            }

            $reservation->update([
                'deposit_status'            => 'paid',
                'deposit_paid_at'           => now(),
                'deposit_payment_reference' => $paymentReference,
                'status'                    => 'deposit_paid',
            ]);

            // First successful deposit locks the listing for everyone else.
            $asset?->lock();

            try {
                $this->accrueSupplierDeposit($reservation->fresh(['asset.vendor']));
                app(AssetLendingRevenuePostingService::class)->postDepositMarkup($reservation->fresh(['asset']));
            } catch (\Throwable $e) {
                report($e);
            }

            $reservation = $reservation->refresh()->loadMissing('loanApplication');
            if ($reservation->loanApplication
                && app(PostApprovalFeeService::class)->allPaid($reservation->loanApplication)) {
                $reservation->update(['status' => 'post_approval_fees_paid']);
                app(UpfrontSettlementService::class)->accrueIfNeeded($reservation->fresh(['asset.vendor']), 'post_approval_fees');
            }

            return $reservation->refresh();
        });
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
            ->where('partner_id', $vendor->id)
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

        $rank = [
            'application_started' => 1,
            'viewing_scheduled' => 2,
            'viewing_completed' => 3,
            'interest_confirmed' => 4,
            'reservation_fee_paid' => 5,
            'application_submitted' => 6,
            'approved' => 7,
            'deposit_paid' => 8,
            'post_approval_fees_paid' => 9,
            'gps_installation' => 10,
            'insurance_active' => 11,
            'registration_complete' => 12,
            'released' => 13,
        ];

        $currentRank = $rank[$reservation->status] ?? 0;
        $nextRank = $rank[$status] ?? 0;

        // Never move the reservation backwards (e.g. approved must not overwrite deposit_paid).
        if ($nextRank > $currentRank) {
            $reservation->update([
                'status'      => $status,
                'released_at' => $status === 'released' ? now() : $reservation->released_at,
            ]);
        } elseif ($application->status === 'disbursed' && $reservation->status !== 'released') {
            $reservation->update([
                'status'      => 'released',
                'released_at' => now(),
            ]);
        }

        $reservation->refresh();
        $status = (string) $reservation->status;

        if (in_array($status, ['approved', 'deposit_paid'], true)
            && $reservation->deposit_status === 'paid'
            && app(PostApprovalFeeService::class)->allPaid($application)) {
            $reservation->update(['status' => 'post_approval_fees_paid']);
            app(UpfrontSettlementService::class)->accrueIfNeeded($reservation->fresh(['asset.vendor']), 'post_approval_fees');
            $status = 'post_approval_fees_paid';
        }

        if (in_array($status, ['approved', 'deposit_paid', 'post_approval_fees_paid'], true)) {
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

        if ($reservation->deposit_status !== 'paid') {
            return false;
        }

        $readyStatuses = ['registration_complete', 'released'];

        if ((bool) ($reqs['ownership_transfer_required'] ?? false)) {
            return in_array($reservation->status, $readyStatuses, true);
        }

        if ((bool) ($reqs['insurance_required'] ?? false) || (bool) ($reqs['gps_required'] ?? false)) {
            return in_array($reservation->status, ['insurance_active', 'registration_complete', 'released'], true);
        }

        return in_array($reservation->status, ['post_approval_fees_paid', 'insurance_active', 'registration_complete', 'released'], true);
    }

    public function unlockIdleListings(): int
    {
        $locked = MarketplaceAsset::query()
            ->where('availability_status', 'locked')
            ->get();

        $unlocked = 0;
        foreach ($locked as $asset) {
            $depositHeld = AssetReservation::query()
                ->where('marketplace_asset_id', $asset->id)
                ->where('deposit_status', 'paid')
                ->whereNotIn('status', ['cancelled', 'released'])
                ->exists();

            if ($depositHeld) {
                continue;
            }

            $asset->unlock();
            $unlocked++;
        }

        return $unlocked;
    }

    public function unlockAssetIfIdle(AssetReservation $reservation): void
    {
        $asset = $reservation->asset;
        if (! $asset) {
            return;
        }

        $depositHeld = AssetReservation::query()
            ->where('marketplace_asset_id', $asset->id)
            ->where('deposit_status', 'paid')
            ->whereNotIn('status', ['cancelled', 'released'])
            ->exists();

        if (! $depositHeld) {
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
            'registration_complete' => $reservation->update(['status' => 'registration_complete']),
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
        $registrationRequired = (bool) ($reqs['ownership_transfer_required'] ?? false);

        // Spine: apply → screen → approve → deposit → post-approval fees → GPS → insurance → registration → handover.
        $labels = [
            ['key' => 'start', 'label' => __('borrower.marketplace.steps.start'), 'phase' => 'apply'],
            ['key' => 'loan_application', 'label' => __('borrower.marketplace.steps.loan_application'), 'phase' => 'loan'],
            ['key' => 'loan_offer', 'label' => __('borrower.marketplace.steps.loan_offer'), 'phase' => 'loan'],
            ['key' => 'deposit', 'label' => __('borrower.marketplace.steps.deposit'), 'phase' => 'loan'],
            ['key' => 'post_approval_fees', 'label' => __('borrower.marketplace.steps.post_approval_fees'), 'phase' => 'loan'],
            ['key' => 'contract', 'label' => __('borrower.marketplace.steps.contract'), 'phase' => 'loan'],
        ];

        if ($gpsRequired) {
            $labels[] = ['key' => 'gps', 'label' => __('borrower.marketplace.steps.gps'), 'phase' => 'handover'];
        }

        if ($insuranceRequired) {
            $labels[] = ['key' => 'insurance', 'label' => __('borrower.marketplace.steps.insurance'), 'phase' => 'handover'];
        }

        if ($registrationRequired) {
            $labels[] = ['key' => 'registration', 'label' => __('borrower.marketplace.steps.registration'), 'phase' => 'handover'];
        }

        $labels[] = ['key' => 'handover', 'label' => __('borrower.marketplace.steps.handover'), 'phase' => 'handover'];

        $currentKey = $this->resolvePipelineStepKey(
            $reservation,
            $application,
            $readiness,
            $gpsRequired,
            $insuranceRequired,
            $registrationRequired,
        );
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
        bool $registrationRequired = false,
    ): string {
        $status = (string) $reservation->status;
        $depositPaid = $reservation->deposit_status === 'paid';

        if ($status === 'released') {
            return 'handover';
        }

        if ($status === 'registration_complete') {
            return 'handover';
        }

        if ($status === 'insurance_active') {
            return $registrationRequired ? 'registration' : 'handover';
        }

        if ($status === 'gps_installation') {
            return $insuranceRequired ? 'insurance' : ($registrationRequired ? 'registration' : 'handover');
        }

        if ($application) {
            if ($readiness->contractSigned($application)) {
                if ($gpsRequired && ! in_array($status, ['insurance_active', 'registration_complete', 'released'], true)) {
                    return 'gps';
                }

                if ($insuranceRequired && ! in_array($status, ['insurance_active', 'registration_complete', 'released'], true)) {
                    return 'insurance';
                }

                if ($registrationRequired && $status !== 'registration_complete' && $status !== 'released') {
                    return 'registration';
                }

                if ($readiness->canMarkAssetHandover($application)) {
                    return 'handover';
                }

                return $gpsRequired ? 'gps' : ($insuranceRequired ? 'insurance' : ($registrationRequired ? 'registration' : 'handover'));
            }

            if (! $depositPaid && in_array($status, ['approved', 'deposit_paid'], true)) {
                return $status === 'deposit_paid' ? 'post_approval_fees' : 'deposit';
            }

            if ($status === 'approved' && ! $depositPaid) {
                return 'deposit';
            }

            if ($depositPaid || $status === 'deposit_paid' || $status === 'post_approval_fees_paid') {
                if ($readiness->needsPostApprovalFees($application) || ($status === 'post_approval_fees_paid' && ! $readiness->contractSigned($application))) {
                    return $readiness->feesPaid($application) ? 'contract' : 'post_approval_fees';
                }
            }

            if ($readiness->needsBorrowerSignature($application) || in_array($application->offer_status, ['pending_borrower'], true)) {
                return 'loan_offer';
            }

            if ($readiness->offerSigned($application)) {
                return $depositPaid ? 'post_approval_fees' : 'deposit';
            }

            return 'loan_application';
        }

        return match ($status) {
            'application_started', 'interest_confirmed', 'viewing_completed', 'viewing_scheduled', 'reservation_fee_paid', 'application_submitted' => 'loan_application',
            'deposit_paid' => 'deposit',
            'approved' => 'deposit',
            'post_approval_fees_paid' => 'post_approval_fees',
            default => 'start',
        };
    }
}
