<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Server-side underwriting evidence lock after submission.
 * Return-for-correction unlocks only the selected section.
 */
class ApplicationEvidenceLockService
{
    public const SECTION_GUARANTOR = 'guarantor';

    public const SECTION_IDENTITY = 'identity_kyc';

    public const SECTION_INCOME = 'income_business';

    public const SECTION_RESIDENCE = 'residence';

    public const SECTION_OTHER = 'other';

    /** Profile section keys that map to underwriting evidence. */
    private const PROFILE_SECTION_MAP = [
        'personal' => self::SECTION_IDENTITY,
        'kyc' => self::SECTION_IDENTITY,
        'kin' => self::SECTION_IDENTITY,
        'activity' => self::SECTION_INCOME,
        'residence' => self::SECTION_RESIDENCE,
    ];

    /** @return list<string> */
    public function lockingStatuses(): array
    {
        return [
            'submitted',
            'awaiting_guarantor',
            'screening',
            'credit_appraisal',
            'pre_approved',
            'under_review',
            'approved',
            'awaiting_offer',
            'awaiting_signature',
            'offer_accepted',
            'awaiting_disbursement_details',
            'awaiting_contract',
            'ready_for_disbursement',
            'post_approval_fees',
            'disbursement',
            'disbursed',
        ];
    }

    public function customerHasLockingApplication(Customer $customer): bool
    {
        if (LoanApplication::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', $this->lockingStatuses())
            ->exists()) {
            return true;
        }

        return Loan::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['active', 'disbursed', 'arrears'])
            ->exists();
    }

    /**
     * @return list<string> correction section keys currently unlocked for this customer
     */
    public function unlockedSections(Customer $customer): array
    {
        $apps = LoanApplication::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', $this->lockingStatuses())
            ->get(['id', 'screening_payload']);

        $unlocked = [];
        foreach ($apps as $app) {
            $payload = $app->screening_payload ?? [];
            $correction = $payload['return_for_correction'] ?? null;
            if (! is_array($correction) || empty($correction['section'])) {
                continue;
            }
            if (! empty($correction['resubmitted_at'])) {
                continue;
            }
            $unlocked[] = (string) $correction['section'];
        }

        return array_values(array_unique($unlocked));
    }

    public function isProfileSectionLocked(Customer $customer, string $profileSection): bool
    {
        $evidenceSection = self::PROFILE_SECTION_MAP[$profileSection] ?? null;
        if ($evidenceSection === null) {
            return false; // payment / security / membership stay editable
        }

        if (! $this->customerHasLockingApplication($customer)) {
            return false;
        }

        return ! in_array($evidenceSection, $this->unlockedSections($customer), true);
    }

    public function assertProfileSectionWritable(Customer $customer, string $profileSection): void
    {
        if ($this->isProfileSectionLocked($customer, $profileSection)) {
            throw new \InvalidArgumentException(__('borrower.loan_profile.profile_locked_for_review'));
        }
    }

    /**
     * @return array{section: string, reason: string, actor_id: int, returned_at: string, before: array<string, mixed>, resubmitted_at: ?string}
     */
    public function returnForCorrection(
        LoanApplication $application,
        User $actor,
        string $section,
        string $reason,
    ): array {
        $allowed = [
            self::SECTION_GUARANTOR,
            self::SECTION_IDENTITY,
            self::SECTION_INCOME,
            self::SECTION_RESIDENCE,
            self::SECTION_OTHER,
        ];
        if (! in_array($section, $allowed, true)) {
            throw new \InvalidArgumentException('Unsupported correction section.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('A reason is required for Return for correction.');
        }

        $before = [
            'status' => $application->status,
            'current_stage' => $application->current_stage,
            'guarantor_statuses' => $application->customerGuarantors()
                ->get(['id', 'status'])
                ->map(fn ($l) => ['id' => $l->id, 'status' => $l->status])
                ->all(),
        ];

        $correction = [
            'section' => $section,
            'reason' => $reason,
            'actor_id' => $actor->id,
            'returned_at' => now()->toIso8601String(),
            'before' => $before,
            'resubmitted_at' => null,
        ];

        DB::transaction(function () use ($application, $correction, $section, $reason, $actor): void {
            $payload = $application->screening_payload ?? [];
            $payload['return_for_correction'] = $correction;
            $application->update(['screening_payload' => $payload]);

            if ($section === self::SECTION_GUARANTOR) {
                $fresh = $application->fresh();
                $link = $fresh?->customerGuarantors()
                    ->whereIn('status', ['pending', 'approved'])
                    ->latest('id')
                    ->first();
                if ($link) {
                    app(GuarantorSupplementService::class)->requestChange($fresh, $link, $actor, $reason);
                } else {
                    app(GuarantorSupplementService::class)->request($fresh, $actor, $reason);
                }
            }
        });

        $customer = $application->customer;
        if ($customer instanceof Customer) {
            app(NotificationService::class)->notifyInApp(
                $customer,
                $reason,
                category: 'loan_application',
                template: 'return_for_correction',
                title: __('borrower.loan_profile.return_for_correction_notify_title'),
                actionUrl: route('site.borrower.application', $application),
                actionLabel: __('borrower.loan_profile.return_for_correction_cta'),
                i18n: [
                    'title_key' => 'borrower.loan_profile.return_for_correction_notify_title',
                    'body_key' => 'borrower.loan_profile.return_for_correction_notify_body',
                    'params' => [
                        'section' => $section,
                        'reference' => $application->application_number,
                    ],
                ],
            );
        }

        app(AuditService::class)->log(
            $actor,
            'application.return_for_correction',
            $application,
            $before,
            [
                'section' => $section,
                'reason' => $reason,
                'returned_at' => $correction['returned_at'],
            ],
        );

        return $correction;
    }

    public function markCorrectionResubmitted(LoanApplication $application): void
    {
        $payload = $application->screening_payload ?? [];
        $correction = $payload['return_for_correction'] ?? null;
        if (! is_array($correction) || empty($correction['returned_at']) || ! empty($correction['resubmitted_at'])) {
            return;
        }
        $correction['resubmitted_at'] = now()->toIso8601String();
        $payload['return_for_correction'] = $correction;
        $application->update(['screening_payload' => $payload]);
    }
}
