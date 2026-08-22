<?php

namespace App\Services;

use App\Models\ArrearCase;
use App\Models\LoanFee;
use App\Models\RecoveryAssignment;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecoveryAssignmentService
{
    public function __construct(
        private readonly RecoveryPolicyService $policy,
        private readonly RecoveryPartnerService $partners,
        private readonly LoanBalanceService $balances,
        private readonly PartnerSettlementService $settlements,
        private readonly LoanCollectionActionService $collectionActions,
    ) {}

    public function assign(
        ArrearCase $arrearCase,
        Vendor $vendor,
        string $partnerType,
        User $actor,
        ?string $notes = null,
    ): RecoveryAssignment {
        if (! $this->partners->isRecoveryPartner($vendor)) {
            throw ValidationException::withMessages([
                'vendor_id' => 'Selected partner is not configured as a recovery partner.',
            ]);
        }

        $loan = $arrearCase->loan;
        if (! $loan) {
            throw ValidationException::withMessages([
                'loan' => 'Collection case is not linked to a loan.',
            ]);
        }

        if (! $this->policy->partnerTypeAppliesToLoan($partnerType, $loan)) {
            throw ValidationException::withMessages([
                'partner_type' => 'This partner type is not configured for this loan product or collateral type.',
            ]);
        }

        $expectedCategory = $this->policy->vendorCategoryForType($partnerType);
        if ($expectedCategory && ! $vendor->hasPartnerRole($expectedCategory) && $vendor->category !== $expectedCategory) {
            throw ValidationException::withMessages([
                'partner_type' => 'Partner type does not match the selected vendor category.',
            ]);
        }

        $open = RecoveryAssignment::query()
            ->where('arrear_case_id', $arrearCase->id)
            ->where('partner_type', $partnerType)
            ->whereIn('status', [RecoveryAssignment::STATUS_ASSIGNED, RecoveryAssignment::STATUS_IN_PROGRESS])
            ->exists();

        if ($open) {
            throw ValidationException::withMessages([
                'partner_type' => 'This case already has an open assignment for that partner type.',
            ]);
        }

        $originalOutstanding = $this->balances->breakdown($loan)['total_outstanding'];
        $feeBase = $this->policy->feeBase() === 'principal'
            ? (float) ($loan->approved_amount ?? $loan->principal_amount ?? 0)
            : $originalOutstanding;
        $rates = $this->policy->ratesForVendor($vendor, $partnerType);

        $repossessionCharge = $partnerType === 'debt_collector'
            ? app(RepossessionChargeService::class)->calculateForLoan($loan)
            : null;

        $charge = $repossessionCharge && $repossessionCharge['total_charge'] > 0
            ? $repossessionCharge
            : $this->policy->calculateRecoveryCharge(
                $feeBase,
                $partnerType,
                $rates['commission_percent'],
                $rates['company_markup_percent'],
                $vendor,
            );

        return DB::transaction(function () use (
            $arrearCase,
            $vendor,
            $partnerType,
            $actor,
            $notes,
            $originalOutstanding,
            $rates,
            $charge,
            $loan,
        ) {
            $slaDays = $this->policy->slaDaysForType($partnerType);

            $task = VendorTask::create([
                'vendor_id'      => $vendor->id,
                'loan_id'        => $loan->id,
                'task_type'      => $this->taskTypeForPartner($partnerType),
                'status'         => 'assigned',
                'due_at'         => now()->addDays($slaDays),
                'customer_name'  => trim(($loan->customer->first_name ?? '').' '.($loan->customer->last_name ?? '')),
                'customer_phone' => $loan->customer->phone ?? null,
                'fee_amount'     => $charge['partner_amount'],
                'notes'          => $notes,
            ]);

            $assignment = RecoveryAssignment::create([
                'arrear_case_id'           => $arrearCase->id,
                'vendor_id'                => $vendor->id,
                'partner_type'             => $partnerType,
                'status'                   => RecoveryAssignment::STATUS_ASSIGNED,
                'original_outstanding'     => $originalOutstanding,
                'commission_percent'       => $rates['commission_percent'],
                'company_markup_percent'   => $rates['company_markup_percent'],
                'recovery_charge'          => $charge['total_charge'],
                'commission_earned'        => $charge['partner_amount'],
                'sla_due_at'               => now()->addDays($slaDays),
                'assigned_by'              => $actor->id,
                'assigned_at'              => now(),
                'notes'                    => $notes,
                'vendor_task_id'           => $task->id,
            ]);

            $this->accrueRecoveryFee($loan, $charge, $partnerType);

            $this->collectionActions->logForCase(
                $arrearCase,
                $actor,
                'recovery_partner_assigned',
                'Assigned to '.$vendor->name.' ('.$this->policy->partnerTypeLabel($partnerType).'). SLA '.$slaDays.' days.',
                'assigned',
                null,
                $assignment,
            );

            $fresh = $assignment->fresh(['vendor', 'arrearCase.loan.customer', 'vendorTask']);

            app(PartnerAssignmentNotifier::class)->notifyAssigned($vendor, $this->policy->partnerTypeLabel($partnerType).' recovery', [
                'title' => 'New recovery assignment',
                'body' => 'Case assigned for loan '.($loan->loan_number ?? '#'.$loan->id).'. SLA '.$slaDays.' day(s).',
                'action_url' => '/partner/recovery',
                'staff_permission' => 'loans.view',
                'staff_url' => route('admin.arrear-cases.show', $arrearCase),
            ]);

            return $fresh;
        });
    }

    public function reassignTo(
        RecoveryAssignment $assignment,
        Vendor $replacement,
        User $actor,
        string $reason = 'Reassigned by staff.',
    ): RecoveryAssignment {
        if (! in_array($assignment->status, [RecoveryAssignment::STATUS_ASSIGNED, RecoveryAssignment::STATUS_IN_PROGRESS], true)) {
            throw ValidationException::withMessages([
                'status' => 'This recovery assignment is already closed.',
            ]);
        }

        $assignment->loadMissing(['arrearCase.loan.customer', 'vendorTask', 'vendor']);

        return DB::transaction(function () use ($assignment, $replacement, $actor, $reason) {
            $assignment->update([
                'status' => RecoveryAssignment::STATUS_CANCELLED,
                'completed_at' => now(),
                'outcome' => 'reassigned',
                'notes' => trim(($assignment->notes ? $assignment->notes."\n" : '').$reason),
            ]);

            if ($assignment->vendorTask && ! in_array($assignment->vendorTask->status, ['completed', 'cancelled'], true)) {
                $assignment->vendorTask->update([
                    'status' => 'cancelled',
                    'completed_at' => now(),
                    'notes' => trim(($assignment->vendorTask->notes ? $assignment->vendorTask->notes."\n" : '').$reason),
                ]);
            }

            $fresh = $this->assign(
                $assignment->arrearCase,
                $replacement,
                (string) $assignment->partner_type,
                $actor,
                $reason,
            );

            if ($assignment->arrearCase) {
                $this->collectionActions->logForCase(
                    $assignment->arrearCase,
                    $actor,
                    'recovery_partner_reassigned',
                    'Reassigned from '.($assignment->vendor?->name ?? 'previous partner').' to '.$replacement->name.'. '.$reason,
                    'reassigned',
                    null,
                    $fresh,
                );
            }

            return $fresh;
        });
    }

    public function start(RecoveryAssignment $assignment, User $actor): RecoveryAssignment
    {
        if ($assignment->status !== RecoveryAssignment::STATUS_ASSIGNED) {
            throw ValidationException::withMessages([
                'status' => 'Only assigned recovery cases can be started.',
            ]);
        }

        $assignment->update(['status' => RecoveryAssignment::STATUS_IN_PROGRESS]);
        $assignment->vendorTask?->update(['status' => 'in_progress']);

        return $assignment->fresh();
    }

    public function complete(
        RecoveryAssignment $assignment,
        User $actor,
        string $outcome,
        ?string $notes = null,
    ): RecoveryAssignment {
        if (! in_array($assignment->status, [RecoveryAssignment::STATUS_ASSIGNED, RecoveryAssignment::STATUS_IN_PROGRESS], true)) {
            throw ValidationException::withMessages([
                'status' => 'This recovery assignment is already closed.',
            ]);
        }

        return DB::transaction(function () use ($assignment, $actor, $outcome, $notes) {
            $assignment->update([
                'status'       => RecoveryAssignment::STATUS_COMPLETED,
                'completed_at' => now(),
                'outcome'      => $outcome,
                'notes'        => trim(($assignment->notes ? $assignment->notes."\n" : '').($notes ?? '')),
            ]);

            $assignment->vendorTask?->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);

            if ((float) $assignment->commission_earned > 0) {
                $this->settlements->accrue(
                    $assignment->vendor,
                    (int) round((float) $assignment->commission_earned),
                    'recovery_commission',
                    $assignment->id,
                    'Recovery commission · '.$this->policy->partnerTypeLabel($assignment->partner_type),
                    $assignment->vendor_task_id,
                );
            }

            if ($assignment->arrearCase) {
                $this->collectionActions->logForCase(
                    $assignment->arrearCase,
                    $actor,
                    'recovery_partner_completed',
                    'Partner completed case with outcome: '.$outcome,
                    $outcome,
                    null,
                    $assignment,
                );
            }

            return $assignment->fresh();
        });
    }

    public function escalate(RecoveryAssignment $assignment, User $actor, ?string $notes = null): RecoveryAssignment
    {
        $assignment->update([
            'status' => RecoveryAssignment::STATUS_ESCALATED,
            'notes'  => trim(($assignment->notes ? $assignment->notes."\n" : '').($notes ?? 'SLA expired — escalated')),
        ]);

        if ($assignment->vendorTask && $assignment->vendorTask->status !== 'completed') {
            $assignment->vendorTask->update([
                'status' => 'cancelled',
                'notes'  => trim(($assignment->vendorTask->notes ? $assignment->vendorTask->notes."\n" : '')
                    .($notes ?? 'SLA expired — case escalated.')),
            ]);
        }

        if ($assignment->arrearCase) {
            $assignment->arrearCase->update(['status' => 'escalated']);
            $this->collectionActions->logForCase(
                $assignment->arrearCase,
                $actor,
                'recovery_partner_escalated',
                $notes ?? 'Recovery partner SLA expired.',
                'escalated',
                null,
                $assignment,
            );
        }

        return $assignment->fresh();
    }

    public function remindPartner(RecoveryAssignment $assignment, User $actor): void
    {
        if (! $assignment->isOpen()) {
            throw ValidationException::withMessages([
                'status' => 'This recovery case is already closed.',
            ]);
        }

        $assignment->loadMissing(['vendor', 'arrearCase.loan.customer']);
        $vendor = $assignment->vendor;
        if (! $vendor) {
            throw ValidationException::withMessages([
                'vendor' => 'No partner is assigned to this case.',
            ]);
        }

        $loan = $assignment->arrearCase?->loan;
        $borrower = trim((string) ($loan?->customer?->full_name ?? ''));
        $loanNumber = $loan?->loan_number ?? ('case #'.$assignment->id);
        $sla = $assignment->sla_due_at?->format('d M Y') ?? 'the SLA date';
        $typeLabel = $this->policy->partnerTypeLabel((string) $assignment->partner_type);

        app(PartnerAssignmentNotifier::class)->notifyAssigned($vendor, $typeLabel.' recovery reminder', [
            'title' => 'Reminder: recovery assignment',
            'body' => 'Follow up on '.$loanNumber.($borrower !== '' ? ' ('.$borrower.')' : '').'. SLA '.$sla.'.',
            'action_url' => '/partner/recovery',
            'staff_permission' => 'partners.manage',
            'staff_url' => route('admin.recovery.assignments.show', $assignment),
        ]);

        if ($assignment->arrearCase) {
            $this->collectionActions->logForCase(
                $assignment->arrearCase,
                $actor,
                'partner_reminder',
                'Partner support reminded '.$vendor->name.' about '.$loanNumber.'.',
                'reminded',
                null,
                $assignment,
            );
        }
    }

    public function remindBorrower(RecoveryAssignment $assignment, User $actor, ?string $viaPartnerName = null): void
    {
        if (! $assignment->isOpen()) {
            throw ValidationException::withMessages([
                'status' => 'This recovery case is already closed.',
            ]);
        }

        $assignment->loadMissing(['arrearCase.loan.customer', 'vendor']);
        $loan = $assignment->arrearCase?->loan;
        $customer = $loan?->customer;

        if (! $customer) {
            throw ValidationException::withMessages([
                'customer' => 'Borrower not found for this case.',
            ]);
        }

        $outstanding = $loan
            ? (float) (app(ActiveLoanServicingService::class)->forLoan($loan)['outstanding_balance'] ?? 0)
            : (float) $assignment->original_outstanding;

        $brand = function_exists('brand_name') ? brand_name() : 'KopaFasta';
        $name = trim((string) ($customer->full_name ?: 'Customer'));
        $loanNumber = $loan?->loan_number ?? 'your loan';
        $amount = format_money($outstanding);

        app(NotificationService::class)->notifyCustomer($customer, 'recovery_case_reminder', [
            'name' => $name,
            'loan_number' => $loanNumber,
            'amount' => $amount,
            '_fallback_subject' => 'Payment reminder',
            '_fallback_body' => "Hi {$name}, reminder: loan {$loanNumber} has {$amount} outstanding. Please pay today or contact us. — {$brand}",
        ]);

        $who = $viaPartnerName ?: 'Partner support';
        if ($assignment->arrearCase) {
            $this->collectionActions->logForCase(
                $assignment->arrearCase,
                $actor,
                'reminder_sent',
                '['.$who.'] Payment reminder sent to borrower',
                'reminded',
                null,
                $assignment,
            );
        }
    }

    private function taskTypeForPartner(string $partnerType): string
    {
        return match ($partnerType) {
            'call_center'    => 'collection_call',
            'debt_collector' => 'field_visit',
            'repossession'   => 'repossession',
            'auctioneer'     => 'auction',
            'legal_partner'  => 'legal_notice',
            'gps_partner'    => 'gps_removal',
            default          => 'collection',
        };
    }

    /** @param array{partner_amount: float, company_amount: float, total_charge: float} $charge */
    private function accrueRecoveryFee($loan, array $charge, string $partnerType): void
    {
        $totalCharge = (float) ($charge['total_charge'] ?? 0);
        if ($totalCharge <= 0) {
            return;
        }

        $fee = LoanFee::firstOrCreate(
            [
                'loan_id' => $loan->id,
                'code'    => 'RECOVERY_'.$partnerType,
            ],
            [
                'name'            => 'Recovery charge · '.$this->policy->partnerTypeLabel($partnerType),
                'type'            => 'recovery',
                'basis'           => 'fixed',
                'rate_or_amount'  => $totalCharge,
                'computed_amount' => $totalCharge,
                'status'          => 'charged',
                'charge_when'     => 'recovery',
                'charged_at'      => now(),
            ],
        );

        if ((float) $fee->computed_amount !== $totalCharge) {
            $fee->update([
                'computed_amount' => $totalCharge,
                'rate_or_amount'  => $totalCharge,
            ]);
        }

        app(RecoveryChargePostingService::class)->postFeeAccrual(
            $loan,
            $fee->fresh(),
            (float) ($charge['partner_amount'] ?? 0),
            (float) ($charge['company_amount'] ?? 0),
        );

        app(LoanBalanceService::class)->syncOutstandingBalance($loan->fresh());

        $customer = $loan->customer;
        if ($customer) {
            try {
                $name = trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: 'Customer';
                $partnerLabel = $this->policy->partnerTypeLabel($partnerType);
                $outstanding = (float) $loan->fresh()->outstanding_balance;
                app(\App\Services\NotificationService::class)->notifyCustomer($customer, 'recovery_fee_accrued', [
                    'name' => $name,
                    'loan_number' => $loan->loan_number,
                    'partner_type' => $partnerLabel,
                    'recovery_amount' => format_money($totalCharge),
                    'amount' => format_money($outstanding),
                    '_fallback_body' => "Hi {$name}, a recovery fee of ".format_money($totalCharge)." ({$partnerLabel}) was added to loan {$loan->loan_number}. Total owed: ".format_money($outstanding).'. Please pay soon to avoid escalation. — '.brand_name(),
                    '_fallback_subject' => 'Recovery fee added to your loan',
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
