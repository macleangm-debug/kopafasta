<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDocumentRequest;

class ProfileRevisionService
{
    /** @var array<string, list<string>> */
    private const LABEL_TARGETS = [
        'Updated National ID'            => ['nida', 'nida_docs'],
        'Image Not Clear'                => ['face', 'nida_docs'],
        'New face verification photo'    => ['face'],
        'New National ID photo'          => ['nida', 'nida_docs'],
        'Identity verification photo'    => ['face'],
        'Signature Not Visible'          => ['signature'],
    ];

    public function applyForDocumentRequest(LoanApplication $application, LoanApplicationDocumentRequest $request, bool $notify = true): void
    {
        $application->loadMissing('customer');
        $request->loadMissing(['subjectCustomer', 'groupMember.customer']);

        // Clear/revise the subject profile (group member / guarantor), never the wrong person.
        $customer = $request->subjectCustomer
            ?? $request->groupMember?->customer
            ?? ($request->subject_customer_id ? Customer::query()->find($request->subject_customer_id) : null)
            ?? $application->customer;
        if (! $customer) {
            return;
        }

        // Always clear linked profile source docs so the borrower replaces, not stacks.
        $this->clearProfileDocumentsForLabel($customer, (string) $request->label);

        $targets = $this->targetsForLabel($request->label);
        if ($targets === []) {
            return;
        }

        $this->markRevisionRequired($customer->fresh(), $targets);
        if ($notify) {
            $this->notifyBorrower($application, $request, $customer);
        }
    }

    /** @param  list<string>  $labels */
    public function applyForLabels(LoanApplication $application, array $labels, ?Customer $customer = null): void
    {
        $application->loadMissing('customer');
        $customer = $customer ?? $application->customer;
        if (! $customer) {
            return;
        }

        foreach ($labels as $label) {
            $this->clearProfileDocumentsForLabel($customer, (string) $label);
        }

        $targets = collect($labels)
            ->flatMap(fn (string $label) => $this->targetsForLabel($label))
            ->unique()
            ->values()
            ->all();

        if ($targets === []) {
            return;
        }

        $this->markRevisionRequired($customer->fresh(), $targets);
    }

    /**
     * For already-open requests, refresh revision flags so hub CTAs stay accurate.
     * Do NOT re-delete profile documents — that wiped re-uploads every time the
     * borrower opened the loan profile (bank statement / income loop).
     */
    public function ensureClearedForOpenRequests(LoanApplication $application): void
    {
        $application->loadMissing(['customer', 'documentRequests']);
        $customer = $application->customer;
        if (! $customer) {
            return;
        }

        foreach ($application->documentRequests as $request) {
            if (! $request->needsBorrowerAction()) {
                continue;
            }
            $targets = $this->targetsForLabel((string) $request->label);
            if ($targets !== []) {
                $this->markRevisionRequired($customer->fresh(), $targets);
            }
        }
    }

    public function clearProfileDocumentsForLabel(Customer $customer, string $label): void
    {
        $codes = $this->documentCodesForLabel($label);
        if ($codes === []) {
            return;
        }

        $docs = app(ProfileDocumentService::class);
        foreach ($codes as $code) {
            $docs->deleteProfileDocument($customer, $code);
        }
    }

    /** @return list<string> */
    public function documentCodesForLabel(string $label): array
    {
        $lower = mb_strtolower(trim($label));

        return match (true) {
            str_contains($lower, 'bank statement') => ['bank_statement'],
            str_contains($lower, 'mobile money') || str_contains($lower, 'mpesa') => ['mobile_money_statement', 'mpesa_statement'],
            str_contains($lower, 'salary slip') => ['salary_slip'],
            str_contains($lower, 'employment contract') => ['employment_contract'],
            str_contains($lower, 'national id') || str_contains($lower, 'nida') => ['national_id_front', 'national_id_back'],
            str_contains($lower, 'residence') => ['residence_letter'],
            default => [],
        };
    }

    public function hasOpenRevision(Customer $customer, string $target): bool
    {
        $flags = $this->revisionFlags($customer);

        return in_array($target, $flags, true);
    }

    public function nidaStepComplete(Customer $customer): bool
    {
        if ($this->hasOpenRevision($customer, 'nida') || $this->hasOpenRevision($customer, 'nida_docs')) {
            return false;
        }

        $nida = app(NidaVerificationService::class)->isVerified($customer);
        if (! $nida) {
            return false;
        }

        $validation = app(ProfileValidationService::class);
        if ($customer->no_physical_nida_card) {
            return true;
        }

        return $validation->hasDocument($customer, 'national_id_front');
    }

    public function faceStepComplete(Customer $customer): bool
    {
        if ($this->hasOpenRevision($customer, 'face')) {
            return false;
        }

        return app(FaceVerificationService::class)->profileStepComplete($customer);
    }

    public function clearForTarget(Customer $customer, string $target): void
    {
        $flags = collect($this->revisionFlags($customer))
            ->reject(fn (string $flag) => $flag === $target)
            ->values()
            ->all();

        $this->storeRevisionFlags($customer, $flags);
    }

    /** @return list<string> */
    private function targetsForLabel(string $label): array
    {
        $label = trim($label);

        if (isset(self::LABEL_TARGETS[$label])) {
            return self::LABEL_TARGETS[$label];
        }

        $lower = mb_strtolower($label);

        return match (true) {
            str_contains($lower, 'signature') => ['signature'],
            str_contains($lower, 'national id') || str_contains($lower, 'nida') => ['nida', 'nida_docs'],
            str_contains($lower, 'face') || str_contains($lower, 'selfie') => ['face'],
            str_contains($lower, 'bank statement')
                || str_contains($lower, 'mobile money')
                || str_contains($lower, 'salary slip')
                || str_contains($lower, 'income proof') => ['income'],
            default => [],
        };
    }

    /** @param  list<string>  $targets */
    private function markRevisionRequired(Customer $customer, array $targets): void
    {
        $flags = collect($this->revisionFlags($customer))
            ->merge($targets)
            ->unique()
            ->values()
            ->all();

        $updates = [];

        if (in_array('face', $targets, true)) {
            $updates['face_verification_status'] = 'revision_required';
        }

        if (in_array('nida', $targets, true) || in_array('nida_docs', $targets, true)) {
            $updates['nida_verification_status'] = 'revision_required';
        }

        if ($updates !== []) {
            $customer->update($updates);
        }

        $this->storeRevisionFlags($customer, $flags);
    }

    /** @return list<string> */
    private function revisionFlags(Customer $customer): array
    {
        $details = $customer->activity_details ?? [];
        $flags = $details['profile_revision_flags'] ?? [];

        return is_array($flags)
            ? array_values(array_filter(array_map('strval', $flags)))
            : [];
    }

    /** @param  list<string>  $flags */
    private function storeRevisionFlags(Customer $customer, array $flags): void
    {
        $details = $customer->activity_details ?? [];
        $details['profile_revision_flags'] = array_values(array_unique($flags));
        $customer->update(['activity_details' => $details]);
    }

    private function notifyBorrower(LoanApplication $application, LoanApplicationDocumentRequest $request, ?Customer $customer = null): void
    {
        $customer = $customer ?? $application->customer;
        if (! $customer) {
            return;
        }

        $actionUrl = $this->actionUrlForTargets($this->targetsForLabel((string) $request->label));
        $locale = $customer->user?->locale ?? app()->getLocale();
        $label = app(ApplicationDocumentRequestService::class)->localizedLabel((string) $request->label, $locale);

        $params = [
            'label' => $label,
            'application' => $application->application_number,
        ];

        app(NotificationService::class)->notifyInApp(
            $customer,
            __('borrower.notifications.profile_revision_body', $params, $locale),
            'profile_revision',
            'profile_revision_requested',
            __('borrower.notifications.profile_revision_title', [], $locale),
            $actionUrl,
            __('borrower.notifications.profile_revision_cta', [], $locale),
            [
                'title_key' => 'borrower.notifications.profile_revision_title',
                'body_key'  => 'borrower.notifications.profile_revision_body',
                'params'    => $params,
            ],
        );
    }

    /** @param  list<string>  $targets */
    private function actionUrlForTargets(array $targets): string
    {
        if (in_array('face', $targets, true)) {
            return route('site.borrower.profile', ['section' => 'personal']).'?focus=face#profile-face';
        }

        if (in_array('signature', $targets, true)) {
            return route('site.borrower.profile', ['section' => 'personal']).'?focus=signature#profile-signature';
        }

        if (in_array('nida', $targets, true) || in_array('nida_docs', $targets, true)) {
            return route('site.borrower.profile', ['section' => 'personal']).'?focus=identity#profile-identity';
        }

        if (in_array('income', $targets, true)) {
            return route('site.borrower.profile', ['section' => 'activity']).'?focus=income#profile-income-statement';
        }

        return route('site.borrower.profile', ['section' => 'personal']);
    }
}
