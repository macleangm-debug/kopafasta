<?php

namespace App\Services;

use App\Models\ApplicationStageHistory;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CapacityAutoRejectService
{
    public const REASON_CODE = 'repayment_exceeds_limit';

    public const ADVICE_CODE = 'reapply_smaller_amount';

    public const STATUS_PENDING = 'pending';

    public const STATUS_FIRED = 'fired';

    public const STATUS_CANCELLED = 'cancelled';

    public function __construct(
        private readonly AffordabilityService $affordability,
        private readonly UnderwritingSettingsService $settings,
        private readonly LoanRejectionReasonService $rejectionReasons,
    ) {}

    public function isPending(LoanApplication $application): bool
    {
        return ($this->state($application)['status'] ?? null) === self::STATUS_PENDING;
    }

    /** Credit committee (and admin) may send now or keep in screening. Credit management does not. */
    public function canAct(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return in_array((string) $user->role, ['credit_committee', 'admin', 'super_admin'], true);
    }

    /** @return array<string, mixed>|null */
    public function state(LoanApplication $application): ?array
    {
        $state = data_get($application->screening_payload, 'capacity_auto_reject');

        return is_array($state) ? $state : null;
    }

    /**
     * Park a capacity-fail application for delayed rejection, or no-op if not applicable.
     *
     * @return array<string, mixed>|null  Pending state when parked
     */
    public function evaluateAndPark(LoanApplication $application): ?array
    {
        if (! $this->settings->capacityAutoRejectEnabled()) {
            return null;
        }

        $application->loadMissing(['customer', 'product', 'loanGroup.members.customer']);

        if (in_array($application->status, ['rejected', 'expired', 'withdrawn', 'cancelled', 'approved', 'disbursed', 'awaiting_guarantor'], true)) {
            return null;
        }

        if (! in_array((string) $application->current_stage, ['submitted', 'screening', 'credit_appraisal'], true)) {
            return null;
        }

        $existing = $this->state($application);
        if (($existing['status'] ?? null) === self::STATUS_PENDING) {
            return $existing;
        }
        if (($existing['status'] ?? null) === self::STATUS_FIRED) {
            return null;
        }

        return $this->parkFromPolicy($application, verified: false);
    }

    /**
     * Park after Gate 2 (verified / statement) policy FAIL. Qualitative Concern does not call this.
     *
     * @return array<string, mixed>|null
     */
    public function evaluateVerifiedAndPark(LoanApplication $application): ?array
    {
        if (! $this->settings->capacityAutoRejectEnabled()) {
            return null;
        }

        $application->loadMissing(['customer', 'product', 'loanGroup.members.customer']);

        if (in_array($application->status, ['rejected', 'expired', 'withdrawn', 'cancelled', 'approved', 'disbursed', 'awaiting_guarantor'], true)) {
            return null;
        }

        if (! in_array((string) $application->current_stage, ['submitted', 'screening', 'credit_appraisal'], true)) {
            return null;
        }

        $existing = $this->state($application);
        if (($existing['status'] ?? null) === self::STATUS_PENDING) {
            return $existing;
        }
        if (($existing['status'] ?? null) === self::STATUS_FIRED) {
            return null;
        }

        return $this->parkFromPolicy($application, verified: true);
    }

    public function remainingLabel(LoanApplication $application): ?string
    {
        $state = $this->state($application);
        if (($state['status'] ?? null) !== self::STATUS_PENDING || empty($state['auto_reject_at'])) {
            return null;
        }

        $seconds = now()->diffInSeconds(\Illuminate\Support\Carbon::parse($state['auto_reject_at']), false);
        if ($seconds <= 0) {
            return '0m';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $hours > 0 ? $hours.'h '.$minutes.'m' : $minutes.'m';
    }

    /**
     * @param  array<string, mixed>  $policy
     */
    public function storeEligibility(LoanApplication $application, array $policy): void
    {
        $payload = $application->screening_payload ?? [];
        $payload['credit_eligibility'] = [
            'application_action' => $policy['application_action'] ?? null,
            'reason' => $policy['reason'] ?? null,
            'failing_gate' => $policy['failing_gate'] ?? null,
            'resolution' => $policy['resolution'] ?? null,
            'participants' => $policy['participants'] ?? [],
            'settings_version' => $policy['settings_version'] ?? null,
            'evaluated_at' => $policy['evaluated_at'] ?? now()->toIso8601String(),
        ];
        $application->screening_payload = $payload;
        if ($application->exists) {
            $application->update(['screening_payload' => $payload]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parkFromPolicy(LoanApplication $application, bool $verified): ?array
    {
        $policy = app(CreditEligibilityPolicyService::class)->evaluate($application, $verified);
        $this->storeEligibility($application, $policy);

        if (($policy['application_action'] ?? '') !== CreditEligibilityPolicyService::ACTION_PENDING_REJECTION) {
            return null;
        }

        $groupEval = $policy['affordability'] ?? app(GroupAffordabilityService::class)->evaluate($application, declaredOnly: ! $verified);
        $isGroup = (bool) ($groupEval['is_group'] ?? false);
        $hours = $verified
            ? $this->settings->verifiedCapacityAutoRejectDelayHours()
            : $this->settings->capacityAutoRejectDelayHours();
        $snapshot = $this->settings->policySnapshot();

        $requested = (float) ($groupEval['total_requested'] ?? $application->requested_amount ?? 0);
        $installment = (float) ($groupEval['total_installment'] ?? 0);
        $capacity = (float) ($groupEval['total_capacity'] ?? 0);
        $failedMembers = $groupEval['failed_members'] ?? [];

        $state = [
            'status' => self::STATUS_PENDING,
            'gate' => $verified ? 'verified' : 'declared',
            'reason_code' => self::REASON_CODE,
            'advice_code' => self::ADVICE_CODE,
            'parked_at' => now()->toIso8601String(),
            'auto_reject_at' => now()->addHours($hours)->toIso8601String(),
            'delay_hours' => $hours,
            'settings_key' => $verified
                ? 'underwriting.verified_capacity_auto_reject_delay_hours'
                : 'underwriting.capacity_auto_reject_delay_hours',
            'settings_version' => $snapshot,
            'is_group' => $isGroup,
            'requested_amount' => $requested,
            'proposed_installment' => $installment,
            'available_capacity' => $capacity,
            'repayment_ratio_pct' => (float) ($groupEval['repayment_ratio_pct'] ?? 33.33),
            'failed_members' => $failedMembers,
            'group_members' => $groupEval['members'] ?? [],
            'affordability_reason' => $policy['reason'] ?? ($groupEval['reason'] ?? null),
        ];

        if (! $isGroup) {
            $single = $groupEval['affordability'] ?? app(AffordabilityService::class)->evaluate($application, $verified ? false : true);
            $state['net_income'] = (float) ($single['net_income'] ?? 0);
            $state['proposed_installment'] = (float) ($single['proposed_installment'] ?? $installment);
            $state['available_capacity'] = (float) ($single['available_capacity'] ?? $capacity);
            $state['requested_amount'] = (float) ($application->requested_amount ?? $requested);
        }

        $payload = $application->screening_payload ?? [];
        $payload['capacity_auto_reject'] = $state;

        $appraisal = $application->credit_appraisal_payload ?? [];
        $appraisal['affordability'] = $isGroup
            ? ['verdict' => 'fail', 'reason' => $groupEval['reason'] ?? $policy['reason'], 'group' => $groupEval]
            : ($groupEval['affordability'] ?? []);
        $appraisal['group_affordability'] = $isGroup ? $groupEval : null;

        $application->update([
            'screening_payload' => $payload,
            'credit_appraisal_payload' => $appraisal,
            'screening_rejection_reason_code' => self::REASON_CODE,
        ]);

        return $state;
    }

    /** Keep in screening — cancel pending auto-reject. */
    public function cancel(LoanApplication $application, ?User $actor = null, ?string $note = null): LoanApplication
    {
        $state = $this->state($application) ?? [];
        if (($state['status'] ?? null) !== self::STATUS_PENDING) {
            return $application;
        }

        $state['status'] = self::STATUS_CANCELLED;
        $state['cancelled_at'] = now()->toIso8601String();
        $state['cancelled_by'] = $actor?->id;
        $state['cancel_note'] = $note;

        $payload = $application->screening_payload ?? [];
        $payload['capacity_auto_reject'] = $state;

        $application->update(['screening_payload' => $payload]);

        ApplicationStageHistory::create([
            'loan_application_id' => $application->id,
            'from_stage' => $application->current_stage,
            'to_stage' => $application->current_stage,
            'changed_by' => $actor?->id,
            'remarks' => 'Capacity auto-reject cancelled'.($note ? ': '.$note : ''),
        ]);

        $fresh = $application->fresh(['customer', 'product', 'loanGroup.members']);
        // Management kept the file — run the paid CIR + profile cross-check now.
        $groupMembers = $fresh->loanGroup?->members
            ?->map(fn ($m) => ['customer_id' => (int) $m->customer_id, 'invitation_id' => $m->invitation_id ?? null])
            ->filter(fn ($row) => ($row['customer_id'] ?? 0) > 0)
            ->values()
            ->all();
        app(CrbCreditCheckService::class)->pullAndAttachAfterCapacityPass(
            $fresh,
            $groupMembers !== [] ? $groupMembers : null,
        );

        return $fresh->fresh();
    }

    public function fireNow(LoanApplication $application, ?User $actor = null): LoanApplication
    {
        return $this->fire($application, $actor, immediate: true);
    }

    /** @return Collection<int, LoanApplication> */
    public function fireDue(): Collection
    {
        $fired = collect();

        LoanApplication::query()
            ->with(['customer.user', 'product'])
            ->whereNotIn('status', ['rejected', 'expired', 'withdrawn', 'cancelled', 'approved', 'disbursed'])
            ->where('screening_payload->capacity_auto_reject->status', self::STATUS_PENDING)
            ->orderBy('id')
            ->chunkById(50, function ($rows) use (&$fired): void {
                foreach ($rows as $application) {
                    $state = $this->state($application);
                    if (($state['status'] ?? null) !== self::STATUS_PENDING) {
                        continue;
                    }
                    $at = $state['auto_reject_at'] ?? null;
                    if (! $at || now()->lt(\Illuminate\Support\Carbon::parse($at))) {
                        continue;
                    }
                    $fired->push($this->fire($application, null, immediate: false));
                }
            });

        return $fired;
    }

    public function borrowerReasonMessage(LoanApplication $application, ?array $state = null, ?string $locale = null): string
    {
        $state ??= $this->state($application) ?? [];
        $locale ??= $this->localeFor($application);

        if (! empty($state['is_group']) && ! empty($state['failed_members'])) {
            $names = collect($state['failed_members'])
                ->map(fn ($m) => $m['name'] ?? 'Member')
                ->filter()
                ->values()
                ->all();

            return __('borrower.loan_profile.capacity_auto_reject_reason_group', [
                'amount' => format_money((float) ($state['requested_amount'] ?? $application->requested_amount ?? 0)),
                'members' => implode(', ', $names),
                'ratio' => rtrim(rtrim(number_format((float) ($state['repayment_ratio_pct'] ?? 33.33), 2), '0'), '.'),
            ], $locale);
        }

        return __('borrower.loan_profile.capacity_auto_reject_reason', [
            'amount' => format_money((float) ($state['requested_amount'] ?? $application->requested_amount ?? 0)),
            'installment' => format_money((float) ($state['proposed_installment'] ?? 0)),
            'capacity' => format_money((float) ($state['available_capacity'] ?? 0)),
        ], $locale);
    }

    /** Hours remaining until feedback is sent (ceil), or null. */
    public function hoursRemaining(LoanApplication $application): ?int
    {
        $state = $this->state($application);
        if (($state['status'] ?? null) !== self::STATUS_PENDING || empty($state['auto_reject_at'])) {
            return null;
        }

        $seconds = now()->diffInSeconds(\Illuminate\Support\Carbon::parse($state['auto_reject_at']), false);
        if ($seconds <= 0) {
            return 0;
        }

        return (int) max(1, (int) ceil($seconds / 3600));
    }

    private function fire(LoanApplication $application, ?User $actor, bool $immediate): LoanApplication
    {
        // Closes on profile (declared) capacity — outstanding documents must not block this path.
        return DB::transaction(function () use ($application, $actor, $immediate) {
            $application = LoanApplication::query()
                ->with(['customer.user', 'product'])
                ->lockForUpdate()
                ->findOrFail($application->id);

            if (in_array($application->status, ['rejected', 'expired', 'withdrawn', 'cancelled'], true)) {
                return $application;
            }

            $state = $this->state($application) ?? [];
            if (($state['status'] ?? null) === self::STATUS_FIRED) {
                return $application;
            }

            // Re-evaluate in case income was updated while parked
            if (($state['status'] ?? null) === self::STATUS_PENDING || $state === []) {
                $verified = ($state['gate'] ?? '') === 'verified';
                $policy = app(CreditEligibilityPolicyService::class)->evaluate($application, $verified);
                $this->storeEligibility($application, $policy);
                if (($policy['application_action'] ?? '') !== CreditEligibilityPolicyService::ACTION_PENDING_REJECTION) {
                    return $this->cancel($application, $actor, 'Affordability no longer fails');
                }
                $groupEval = $policy['affordability'] ?? app(GroupAffordabilityService::class)->evaluate($application, declaredOnly: ! $verified);
                $state['is_group'] = (bool) ($groupEval['is_group'] ?? $state['is_group'] ?? false);
                $state['requested_amount'] = (float) ($groupEval['total_requested'] ?? $application->requested_amount ?? 0);
                $state['proposed_installment'] = (float) ($groupEval['total_installment'] ?? 0);
                $state['available_capacity'] = (float) ($groupEval['total_capacity'] ?? 0);
                $state['failed_members'] = $groupEval['failed_members'] ?? [];
                $state['group_members'] = $groupEval['members'] ?? [];
                $state['repayment_ratio_pct'] = (float) ($groupEval['repayment_ratio_pct'] ?? 33.33);
                if (! ($state['is_group'] ?? false)) {
                    $single = $groupEval['affordability'] ?? [];
                    $state['net_income'] = (float) ($single['net_income'] ?? 0);
                    $state['proposed_installment'] = (float) ($single['proposed_installment'] ?? $state['proposed_installment']);
                    $state['available_capacity'] = (float) ($single['available_capacity'] ?? $state['available_capacity']);
                }
            }

            $locale = $this->localeFor($application);
            $message = $this->borrowerReasonMessage($application, $state, $locale);
            $from = $application->current_stage ?? 'screening';

            $state['status'] = self::STATUS_FIRED;
            $state['fired_at'] = now()->toIso8601String();
            $state['fired_immediate'] = $immediate;
            $state['fired_by'] = $actor?->id;

            $payload = $application->screening_payload ?? [];
            $payload['capacity_auto_reject'] = $state;

            $application->update([
                'current_stage' => 'rejected',
                'status' => 'rejected',
                'rejection_reason_code' => self::REASON_CODE,
                'rejection_reason_codes' => [self::REASON_CODE],
                'rejection_reason' => $message,
                'rejection_advice_code' => self::ADVICE_CODE,
                'rejection_advice' => null,
                'rejection_internal_notes' => $immediate
                    ? 'Capacity auto-reject sent immediately'
                    : 'Capacity auto-reject after delay',
                'screening_rejection_reason_code' => self::REASON_CODE,
                'screening_payload' => $payload,
            ]);

            ApplicationStageHistory::create([
                'loan_application_id' => $application->id,
                'from_stage' => $from,
                'to_stage' => 'rejected',
                'changed_by' => $actor?->id,
                'remarks' => 'Capacity auto-reject: '.$message,
            ]);

            $fresh = $application->fresh(['customer.user', 'product']);
            try {
                app(LoanAgreementService::class)->generateRejectionLetter($fresh);
            } catch (\Throwable $e) {
                report($e);
            }

            $this->notifyBorrower($fresh->fresh(['customer.user']), $message, $locale);

            return $fresh->fresh();
        });
    }

    private function notifyBorrower(LoanApplication $application, string $message, string $locale): void
    {
        $customer = $application->customer;
        if (! $customer) {
            return;
        }

        $advice = $this->rejectionReasons->resolveBorrowerAdvice(self::ADVICE_CODE, null, $locale);
        $body = $message;
        if ($advice) {
            $body .= "\n".$advice;
        }

        $letter = \App\Models\LoanAgreement::query()
            ->where('loan_application_id', $application->id)
            ->where('document_type', 'rejection_letter')
            ->latest('id')
            ->first();

        $url = $letter
            ? route('site.borrower.application.rejection-letter', $application->id)
            : route('site.borrower.application', $application->id);

        app(NotificationService::class)->notifyInApp(
            $customer,
            $body,
            'application',
            'application_rejected',
            __('borrower.loan_profile.capacity_auto_reject_notify_title', [], $locale),
            $url,
            $letter
                ? __('borrower.rejection_letter.notify_cta', [], $locale)
                : __('borrower.loan_profile.actions.view_reason', [], $locale),
        );
    }

    private function localeFor(LoanApplication $application): string
    {
        $user = $application->customer?->user;
        $locale = $user?->locale
            ?? data_get($user?->preferences, 'preferred_locale')
            ?? data_get($user?->preferences, 'locale')
            ?? app()->getLocale();

        return in_array($locale, ['en', 'sw'], true) ? $locale : 'en';
    }
}
