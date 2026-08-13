<?php

namespace App\Services;

use App\Models\CustomerDocument;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDocumentReview;
use App\Models\LoanProductRequirement;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApplicationDocumentReviewService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly ApplicationDocumentRequestService $requests,
    ) {}

    /**
     * Status for this document on this application (profile docs always re-reviewed per application).
     */
    public function statusFor(LoanApplication $application, CustomerDocument $document): string
    {
        $review = $this->reviewFor($application, $document);
        if ($review) {
            return (string) $review->status;
        }

        // Uploads that belong to this application keep their row status.
        if ((int) ($document->loan_application_id ?? 0) === (int) $application->id) {
            return (string) ($document->status ?: 'pending_review');
        }

        return 'pending_review';
    }

    public function reviewFor(LoanApplication $application, CustomerDocument $document): ?LoanApplicationDocumentReview
    {
        if ($document->relationLoaded('applicationReviews')) {
            return $document->applicationReviews
                ->firstWhere('loan_application_id', $application->id);
        }

        return LoanApplicationDocumentReview::query()
            ->where('loan_application_id', $application->id)
            ->where('customer_document_id', $document->id)
            ->first();
    }

    /** @return Collection<int, LoanApplicationDocumentReview> keyed by customer_document_id */
    public function reviewsForApplication(LoanApplication $application): Collection
    {
        return LoanApplicationDocumentReview::query()
            ->where('loan_application_id', $application->id)
            ->get()
            ->keyBy('customer_document_id');
    }

    /**
     * @param  array{subject_kind?: string, subject_customer_id?: ?int, loan_group_member_id?: ?int}  $subject
     */
    public function verify(
        CustomerDocument $document,
        LoanApplication $application,
        User $user,
        array $subject = [],
    ): LoanApplicationDocumentReview {
        $this->assertCanReview($document, $application, $user);

        $review = $this->upsertReview($document, $application, $user, [
            'status' => 'verified',
            'fail_reason_code' => null,
            'fail_reason_custom' => null,
            'remedy' => null,
            'notes' => null,
        ], $subject);

        // Application-owned uploads also flip the document row (product / request files).
        if ((int) ($document->loan_application_id ?? 0) === (int) $application->id) {
            $document->update([
                'status' => 'verified',
                'verified_at' => now(),
                'verified_by' => $user->id,
            ]);
        }

        $this->audit->log($user, 'admin.loan_applications.document_verified', $application, [], [
            'document_id' => $document->id,
            'requirement_id' => $document->loan_product_requirement_id,
            'application_review_id' => $review->id,
            'scope' => 'application',
        ]);

        app(ChecklistDocumentBridge::class)->syncChecklistAfterDocumentReview(
            $application,
            $document,
            $review,
            $user,
        );

        return $review->fresh();
    }

    /**
     * @param  array{
     *   notes?: ?string,
     *   fail_reason_code?: ?string,
     *   fail_reason_custom?: ?string,
     *   remedy?: ?string,
     *   request_again_label?: ?string,
     *   subject_kind?: string,
     *   subject_customer_id?: ?int,
     *   loan_group_member_id?: ?int,
     * }  $options
     */
    public function reject(
        CustomerDocument $document,
        LoanApplication $application,
        User $user,
        array $options = [],
    ): LoanApplicationDocumentReview {
        $this->assertCanReview($document, $application, $user);

        $failCode = trim((string) ($options['fail_reason_code'] ?? ''));
        $failCustom = trim((string) ($options['fail_reason_custom'] ?? ''));
        $notes = trim((string) ($options['notes'] ?? ''));
        $remedy = (string) ($options['remedy'] ?? 'request_again');
        if (! in_array($remedy, ['request_again', 'none'], true)) {
            $remedy = 'request_again';
        }

        $reasons = config('application_document_review.fail_reasons', []);
        if ($failCode === '' || ! array_key_exists($failCode, $reasons)) {
            throw ValidationException::withMessages([
                'fail_reason_code' => 'Select a fail reason for this document.',
            ]);
        }
        if ($failCode === 'custom' && $failCustom === '') {
            throw ValidationException::withMessages([
                'fail_reason_custom' => 'Write the custom fail reason.',
            ]);
        }

        $reasonLabel = $failCode === 'custom'
            ? $failCustom
            : (string) $reasons[$failCode];
        $combinedNotes = $notes !== ''
            ? $reasonLabel.' — '.$notes
            : $reasonLabel;

        $subject = [
            'subject_kind' => $options['subject_kind'] ?? 'borrower',
            'subject_customer_id' => $options['subject_customer_id'] ?? null,
            'loan_group_member_id' => $options['loan_group_member_id'] ?? null,
        ];

        $review = $this->upsertReview($document, $application, $user, [
            'status' => 'rejected',
            'fail_reason_code' => $failCode,
            'fail_reason_custom' => $failCode === 'custom' ? $failCustom : null,
            'remedy' => $remedy,
            'notes' => $combinedNotes,
        ], $subject);

        if ((int) ($document->loan_application_id ?? 0) === (int) $application->id) {
            $document->update([
                'status' => 'rejected',
                'notes' => $combinedNotes,
            ]);
        }

        if ($remedy === 'request_again') {
            $label = trim((string) ($options['request_again_label'] ?? ''));
            if ($label === '') {
                $label = $document->documentType?->name
                    ?: $document->original_name
                    ?: 'Replacement document';
            }
            $this->requests->create(
                $application,
                $user,
                $label,
                'Previous upload rejected for this application: '.$combinedNotes,
                null,
                'document',
                (string) ($subject['subject_kind'] ?? 'borrower'),
                isset($subject['subject_customer_id']) ? (int) $subject['subject_customer_id'] : null,
                isset($subject['loan_group_member_id']) ? (int) $subject['loan_group_member_id'] : null,
            );
        }

        $this->audit->log($user, 'admin.loan_applications.document_rejected', $application, [], [
            'document_id' => $document->id,
            'fail_reason_code' => $failCode,
            'remedy' => $remedy,
            'application_review_id' => $review->id,
            'scope' => 'application',
        ]);

        app(ChecklistDocumentBridge::class)->syncChecklistAfterDocumentReview(
            $application,
            $document,
            $review,
            $user,
        );

        return $review->fresh();
    }

    /** @return array{title: string, items: list<string>} */
    public function guidanceForRequirement(LoanProductRequirement $requirement): array
    {
        $config = config('underwriting_document_guidance', []);
        $defaults = $config['defaults'] ?? ['title' => 'What to verify', 'items' => []];

        $keys = array_filter([
            $requirement->code ?? null,
            Str::slug((string) $requirement->name, '_'),
            Str::slug((string) ($requirement->document_type ?? ''), '_'),
        ]);

        foreach ($keys as $key) {
            if (! empty($config[$key])) {
                return $config[$key];
            }
        }

        foreach ($config as $key => $guidance) {
            if ($key === 'defaults' || ! is_array($guidance)) {
                continue;
            }
            if (Str::contains(Str::lower((string) $requirement->name), str_replace('_', ' ', $key))) {
                return $guidance;
            }
        }

        return $defaults;
    }

    /** @return array{title: string, items: list<string>} */
    public function guidanceForDocument(CustomerDocument $document): array
    {
        $config = config('underwriting_document_guidance', []);
        $defaults = $config['defaults'] ?? ['title' => 'What to verify', 'items' => []];

        $code = $document->documentType?->code ?? Str::slug((string) ($document->documentType?->name ?? ''), '_');

        if ($code && ! empty($config[$code])) {
            return $config[$code];
        }

        return $defaults;
    }

    /**
     * @param  array{status: string, fail_reason_code: ?string, fail_reason_custom: ?string, remedy: ?string, notes: ?string}  $payload
     * @param  array{subject_kind?: string, subject_customer_id?: ?int, loan_group_member_id?: ?int}  $subject
     */
    private function upsertReview(
        CustomerDocument $document,
        LoanApplication $application,
        User $user,
        array $payload,
        array $subject = [],
    ): LoanApplicationDocumentReview {
        $kind = (string) ($subject['subject_kind'] ?? 'borrower');
        if (! in_array($kind, ['borrower', 'guarantor', 'member'], true)) {
            $kind = 'borrower';
        }

        return LoanApplicationDocumentReview::query()->updateOrCreate(
            [
                'loan_application_id' => $application->id,
                'customer_document_id' => $document->id,
            ],
            [
                'subject_kind' => $kind,
                'subject_customer_id' => $subject['subject_customer_id']
                    ?? $document->customer_id,
                'loan_group_member_id' => $subject['loan_group_member_id'] ?? null,
                'status' => $payload['status'],
                'fail_reason_code' => $payload['fail_reason_code'],
                'fail_reason_custom' => $payload['fail_reason_custom'],
                'remedy' => $payload['remedy'],
                'notes' => $payload['notes'],
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
            ],
        );
    }

    private function assertCanReview(CustomerDocument $document, LoanApplication $application, User $user): void
    {
        if (! app(PermissionService::class)->has($user, 'applications.review')) {
            throw ValidationException::withMessages(['document' => 'You do not have permission to review documents.']);
        }

        $allowedCustomerIds = $this->subjectCustomerIds($application);
        abort_unless(in_array((int) $document->customer_id, $allowedCustomerIds, true), 404);
    }

    /** @return list<int> */
    private function subjectCustomerIds(LoanApplication $application): array
    {
        $application->loadMissing([
            'customer',
            'customerGuarantors.guarantor',
            'loanGroup.members.customer',
        ]);

        $ids = [(int) $application->customer_id];
        foreach ($application->customerGuarantors as $link) {
            if ($link->guarantor_id) {
                $ids[] = (int) $link->guarantor_id;
            }
        }
        foreach ($application->loanGroup?->members ?? [] as $member) {
            if ($member->customer_id) {
                $ids[] = (int) $member->customer_id;
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }
}
