<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GuarantorSupplementService
{
    public function hasOpenRequest(LoanApplication $application): bool
    {
        $request = $this->openRequest($application);

        return $request !== null && empty($request['satisfied_at']);
    }

    /** @return array{requested_at?: string, requested_by?: int, notes?: string|null, satisfied_at?: string|null}|null */
    public function openRequest(LoanApplication $application): ?array
    {
        $payload = $application->screening_payload ?? [];
        $request = $payload['guarantor_supplement'] ?? null;

        if (! is_array($request) || empty($request['requested_at'])) {
            return null;
        }

        if (! empty($request['satisfied_at'])) {
            return null;
        }

        return $request;
    }

    public function request(LoanApplication $application, User $admin, ?string $notes = null): void
    {
        if (! in_array((string) $application->status, [
            'submitted',
            'awaiting_guarantor',
            'screening',
            'credit_appraisal',
            'pre_approved',
            'under_review',
        ], true) && ! in_array((string) $application->current_stage, [
            'submitted',
            'screening',
            'credit_appraisal',
            'pre_approval',
        ], true)) {
            throw new \InvalidArgumentException('Additional guarantor can only be requested while the application is under review.');
        }

        DB::transaction(function () use ($application, $admin, $notes): void {
            $payload = $application->screening_payload ?? [];
            $payload['guarantor_supplement'] = [
                'requested_at' => now()->toIso8601String(),
                'requested_by' => $admin->id,
                'notes'        => $notes,
                'satisfied_at' => null,
                'kind'         => 'additional',
            ];
            $application->update(['screening_payload' => $payload]);
        });

        $customer = $application->customer;
        if ($customer instanceof Customer) {
            $url = $this->borrowerWizardUrl($application);
            app(NotificationService::class)->notifyInApp(
                $customer,
                __('borrower.guarantor_supplement.notify_body', [
                    'reference' => $application->reference_no ?? $application->application_number ?? $application->id,
                ]),
                category: 'loan_application',
                template: 'guarantor_supplement_request',
                title: __('borrower.guarantor_supplement.notify_title'),
                actionUrl: $url,
                actionLabel: __('borrower.guarantor_supplement.cta'),
                i18n: [
                    'title_key' => 'borrower.guarantor_supplement.notify_title',
                    'body_key'  => 'borrower.guarantor_supplement.notify_body',
                    'params'    => [
                        'reference' => $application->reference_no ?? $application->application_number ?? $application->id,
                    ],
                ],
            );
        }
    }

    /**
     * Soft-reject this guarantor on this application only and ask the borrower to choose someone else.
     * Does not blacklist the guarantor person — their membership and CRB remain reusable elsewhere.
     */
    public function requestChange(
        LoanApplication $application,
        \App\Models\CustomerGuarantor $link,
        User $admin,
        ?string $notes = null,
    ): void {
        if ((int) $link->loan_application_id !== (int) $application->id) {
            throw new \InvalidArgumentException('Guarantor is not linked to this application.');
        }

        if ($link->status === 'rejected') {
            throw new \InvalidArgumentException('This guarantor is already declined for this application.');
        }

        $guarantorName = trim((string) (
            ($link->guarantor?->first_name.' '.$link->guarantor?->last_name)
            ?: 'Guarantor'
        ));

        app(GuarantorInvitationService::class)->rejectByUnderwriting($link, $notes);

        DB::transaction(function () use ($application, $admin, $notes, $link, $guarantorName): void {
            $payload = $application->screening_payload ?? [];
            $payload['guarantor_supplement'] = [
                'requested_at' => now()->toIso8601String(),
                'requested_by' => $admin->id,
                'notes'        => $notes,
                'satisfied_at' => null,
                'kind'         => 'change',
                'replaced_customer_guarantor_id' => $link->id,
                'replaced_guarantor_name' => $guarantorName,
            ];
            $application->update(['screening_payload' => $payload]);
        });

        $customer = $application->customer;
        if ($customer instanceof Customer) {
            $url = $this->borrowerWizardUrl($application);
            $reference = $application->reference_no ?? $application->application_number ?? $application->id;
            app(NotificationService::class)->notifyInApp(
                $customer,
                __('borrower.guarantor_supplement.change_notify_body', [
                    'reference' => $reference,
                    'guarantor' => $guarantorName,
                ]),
                category: 'loan_application',
                template: 'guarantor_change_request',
                title: __('borrower.guarantor_supplement.change_notify_title'),
                actionUrl: $url,
                actionLabel: __('borrower.guarantor_supplement.change_cta'),
                i18n: [
                    'title_key' => 'borrower.guarantor_supplement.change_notify_title',
                    'body_key'  => 'borrower.guarantor_supplement.change_notify_body',
                    'params'    => [
                        'reference' => $reference,
                        'guarantor' => $guarantorName,
                    ],
                ],
            );
        }
    }

    /**
     * Borrower self-service: while the application is held awaiting guarantor completion,
     * drop current pending/approved guarantors and open the guarantor step to pick someone else.
     * Keeps awaiting_guarantor + existing deadline (does not restart screening).
     */
    public function startBorrowerChangeWhileHeld(LoanApplication $application, Customer $borrower): string
    {
        if ((int) $application->customer_id !== (int) $borrower->id) {
            throw new \InvalidArgumentException('Application does not belong to this borrower.');
        }

        if ((string) $application->status !== 'awaiting_guarantor'
            && (string) $application->current_stage !== 'awaiting_guarantor') {
            throw new \InvalidArgumentException('Guarantor can only be changed while the application is waiting for guarantor completion.');
        }

        if ($this->hasOpenRequest($application)) {
            return $this->borrowerWizardUrl($application);
        }

        $inviteSvc = app(GuarantorInvitationService::class);
        $activeLinks = \App\Models\CustomerGuarantor::query()
            ->where('loan_application_id', $application->id)
            ->whereIn('status', ['pending', 'approved'])
            ->get();

        foreach ($activeLinks as $link) {
            $inviteSvc->rejectByUnderwriting(
                $link,
                'Replaced by borrower while awaiting guarantor completion'
            );
        }

        DB::transaction(function () use ($application, $borrower): void {
            $payload = $application->screening_payload ?? [];
            $payload['guarantor_supplement'] = [
                'requested_at' => now()->toIso8601String(),
                'requested_by' => $borrower->user_id,
                'notes'        => 'Borrower-initiated change while awaiting guarantor profile',
                'satisfied_at' => null,
                'kind'         => 'change',
                'initiated_by' => 'borrower',
            ];
            $application->update([
                'screening_payload' => $payload,
                'status'            => 'awaiting_guarantor',
                'current_stage'     => 'awaiting_guarantor',
            ]);
        });

        return $this->borrowerWizardUrl($application);
    }

    public function markSatisfied(LoanApplication $application): void
    {
        $payload = $application->screening_payload ?? [];
        $request = $payload['guarantor_supplement'] ?? null;
        if (! is_array($request) || empty($request['requested_at'])) {
            return;
        }

        $request['satisfied_at'] = now()->toIso8601String();
        $payload['guarantor_supplement'] = $request;
        $application->update(['screening_payload' => $payload]);
    }

    public function borrowerWizardUrl(LoanApplication $application): string
    {
        return route('site.borrower.apply', [
            'product'              => $application->loan_product_id,
            'guarantor_supplement' => 1,
            'application'          => $application->id,
            'resume'               => 1,
            'step_key'             => 'guarantor',
        ]);
    }
}
