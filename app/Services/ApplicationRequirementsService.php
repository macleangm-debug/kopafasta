<?php

namespace App\Services;

use App\Models\Customer;
use App\Support\KinName;
use App\Models\Setting;
use Illuminate\Http\Request;

class ApplicationRequirementsService
{
    /** Whether the borrower may submit a loan application (profile/KYC must be complete). */
    public function canSubmit(Customer $customer): bool
    {
        return $this->checklist($customer)['can_apply'];
    }

    /** @return array{can_apply: bool, can_submit: bool, items: list<array{key: string, label: string, complete: bool, action_url: string|null, detail: string}>} */
    public function checklist(Customer $customer): array
    {
        $nida = app(NidaVerificationService::class);
        $face = app(FaceVerificationService::class);
        $freshness = app(KycFreshnessService::class);
        $profile = app(ProfileCompletionService::class);
        $identityPolicy = app(IdentityVerificationPolicyService::class);
        $requireNida = (bool) (Setting::group('kyc')['require_nida'] ?? true)
            && $identityPolicy->requiredDuringProfileCreation()
            && $identityPolicy->nidaRequired();

        $items = [
            [
                'key'        => 'registration_fee',
                'label'      => __('borrower.apply.checklist.registration_fee'),
                'complete'   => $customer->hasMembership(),
                'pending'    => false,
                'detail'     => $customer->hasMembership()
                    ? __('borrower.apply.checklist.registration_issued')
                    : __('borrower.apply.checklist.registration_pay'),
                'action_url' => $customer->hasMembership() ? null : route('site.membership.renew'),
            ],
            [
                'key'        => 'membership',
                'label'      => __('borrower.apply.checklist.membership'),
                'complete'   => $customer->isMembershipActive() || $customer->isMembershipInGrace(),
                'pending'    => false,
                'detail'     => $customer->isMembershipActive()
                    ? __('borrower.apply.checklist.membership_valid_until', [
                        'date' => optional($customer->membership_expires_at)->format('d M Y'),
                    ])
                    : __('borrower.apply.checklist.membership_renew'),
                'action_url' => ($customer->isMembershipActive() || $customer->isMembershipInGrace()) ? null : route('site.membership.renew'),
            ],
        ];

        if ($requireNida) {
            $items[] = [
                'key'        => 'nida',
                'label'      => __('borrower.apply.checklist.nida'),
                'complete'   => $nida->isVerified($customer),
                'pending'    => in_array($customer->nida_verification_status, ['name_mismatch', 'multihit'], true),
                'detail'     => match (true) {
                    $nida->isVerified($customer) => __('borrower.apply.checklist.nida_confirmed'),
                    $customer->nida_verification_status === 'name_mismatch' => __('borrower.apply.checklist.nida_name_mismatch'),
                    $customer->nida_verification_status === 'multihit' => __('borrower.apply.checklist.nida_multihit'),
                    default => __('borrower.apply.checklist.nida_enter'),
                },
                'action_url' => $nida->isVerified($customer) ? null : route('site.borrower.profile', ['section' => 'personal']),
            ];
        }

        $faceStatus = $customer->face_verification_status ?? 'incomplete';
        $faceSubmitted = in_array($faceStatus, ['pending', 'verified'], true);

        if ($identityPolicy->requiredDuringProfileCreation() && $identityPolicy->facialRequired()) {
            $items[] = [
                'key'        => 'face_submitted',
                'label'      => __('borrower.apply.checklist.face_submitted'),
                'complete'   => $faceSubmitted,
                'pending'    => false,
                'detail'     => match ($faceStatus) {
                    'verified', 'pending' => __('borrower.apply.checklist.face_captured'),
                    'rejected' => __('borrower.apply.checklist.face_rejected'),
                    'revision_required' => __('borrower.apply.checklist.face_revision'),
                    default    => __('borrower.apply.checklist.face_incomplete'),
                },
                'action_url' => $faceSubmitted ? null : route('site.borrower.face-verification'),
            ];

            $items[] = [
                'key'        => 'face_approval',
                'label'      => __('borrower.apply.checklist.face_approval'),
                'complete'   => $face->canApply($customer),
                'pending'    => $faceStatus === 'pending',
                'detail'     => match ($faceStatus) {
                    'verified' => __('borrower.apply.checklist.face_approved'),
                    'pending'  => __('borrower.apply.checklist.face_pending'),
                    'rejected' => __('borrower.apply.checklist.face_rejected'),
                    'revision_required' => __('borrower.apply.checklist.face_revision'),
                    default    => __('borrower.apply.checklist.face_submit_first'),
                },
                'action_url' => in_array($faceStatus, ['rejected', 'revision_required'], true)
                    ? route('site.borrower.face-verification')
                    : null,
            ];
        }

        $validation = app(ProfileValidationService::class);
        $profileResult = $profile->calculate($customer);

        $personalComplete = $validation->isCorePersonalComplete($customer);
        $items[] = [
            'key'        => 'personal',
            'label'      => __('borrower.loan_profile.sections.personal'),
            'complete'   => $personalComplete,
            'pending'    => ! $personalComplete,
            'detail'     => $personalComplete
                ? __('borrower.profile.section_complete')
                : __('borrower.apply.kyc_section_personal_detail'),
            'action_url' => $personalComplete ? null : route('site.borrower.profile', ['section' => 'personal']),
        ];

        $kinComplete = $validation->isKinComplete($customer);
        $items[] = [
            'key'        => 'kin',
            'label'      => __('borrower.loan_profile.sections.kin'),
            'complete'   => $kinComplete,
            'pending'    => ! $kinComplete,
            'detail'     => $kinComplete
                ? __('borrower.profile.section_complete')
                : __('borrower.apply.kyc_section_kin_detail'),
            'action_url' => $kinComplete
                ? null
                : route('site.borrower.profile', ['section' => 'personal', 'focus' => 'kin']).'#next-of-kin',
        ];

        $residenceComplete = $profile->isResidenceComplete($customer)
            && (! $validation->requiresResidenceLetter() || $validation->hasResidenceLetter($customer));
        $items[] = [
            'key'        => 'residence',
            'label'      => __('borrower.loan_profile.sections.residence'),
            'complete'   => $residenceComplete,
            'pending'    => ! $residenceComplete,
            'detail'     => $residenceComplete
                ? __('borrower.profile.section_complete')
                : __('borrower.apply.kyc_section_residence_detail'),
            'action_url' => $residenceComplete ? null : route('site.borrower.profile', ['section' => 'residence']),
        ];

        $activityComplete = $profile->isActivityComplete($customer);
        $items[] = [
            'key'        => 'activity',
            'label'      => __('borrower.loan_profile.sections.employment'),
            'complete'   => $activityComplete,
            'pending'    => ! $activityComplete,
            'detail'     => $activityComplete
                ? __('borrower.profile.section_complete')
                : __('borrower.apply.kyc_section_activity_detail'),
            'action_url' => $activityComplete ? null : route('site.borrower.profile', ['section' => 'activity']),
        ];

        if (app(IncomeProofService::class)->isRequired()) {
            $satisfied = app(IncomeProofService::class)->satisfiesRequirement($customer);
            $items[] = [
                'key'        => 'income_proof',
                'label'      => __('borrower.loan_profile.sections.proof_of_income'),
                'complete'   => $satisfied,
                'pending'    => ! $satisfied,
                'detail'     => $satisfied
                    ? __('borrower.profile.income_proof_complete')
                    : __('borrower.profile.income_proof_required'),
                'action_url' => $satisfied ? null : route('site.borrower.profile', ['section' => 'kyc']),
            ];
        }

        if (app(ProfileSectionBuilderService::class)->paymentRequiredBeforeLoan()) {
            $paymentComplete = app(CustomerDisbursementDetailsService::class)->isComplete($customer);
            $items[] = [
                'key'        => 'payment',
                'label'      => __('borrower.payment_details.section_title'),
                'complete'   => $paymentComplete,
                'pending'    => ! $paymentComplete,
                'detail'     => $paymentComplete
                    ? __('borrower.profile.section_complete')
                    : __('borrower.payment_details.incomplete_hint'),
                'action_url' => $paymentComplete ? null : route('site.borrower.profile', ['section' => 'payment', 'add' => 1]),
            ];
        }

        if (! $freshness->canApply($customer)) {
            $staleSections = $freshness->sectionsDueForRefresh($customer);
            $staleLabels = $freshness->staleSectionLabels($customer);
            $items[] = [
                'key'        => 'kyc_freshness',
                'label'      => __('borrower.kyc.kyc_reconfirm_title'),
                'complete'   => false,
                'pending'    => true,
                'detail'     => $staleLabels !== []
                    ? __('borrower.kyc.stale_sections_detail', ['sections' => implode(', ', $staleLabels)])
                    : __('borrower.apply.checklist.kyc_freshness_fallback'),
                'stale_sections' => $staleSections,
                'action_url' => route('site.borrower.kyc-reconfirm'),
            ];
        }

        $signatureService = app(BorrowerSignatureService::class);
        $hasLegalSignature = $signatureService->hasProfileSignature($customer);
        $items[] = [
            'key'        => 'legal_signature',
            'label'      => __('borrower.apply.checklist.legal_signature'),
            'complete'   => $hasLegalSignature,
            'pending'    => ! $hasLegalSignature,
            'detail'     => $hasLegalSignature
                ? __('borrower.apply.checklist.legal_signature_complete')
                : __('borrower.apply.checklist.legal_signature_missing'),
            'action_url' => $hasLegalSignature
                ? null
                : route('site.borrower.profile', ['section' => 'personal', 'focus' => 'signature']),
        ];

        $completed = collect($items)->where('complete', true)->count();
        $total = count($items);

        $canApply = collect($items)
            ->reject(fn (array $item) => in_array($item['key'], ['face_approval'], true))
            ->every(fn (array $item) => $item['complete']);

        $firstIncomplete = $this->firstIncompleteItem($items);

        return [
            'can_apply'            => $canApply,
            'can_submit'           => $canApply,
            'items'                => $items,
            'completion_percent'   => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            'profile_percent'      => $profileResult['percent'],
            'first_incomplete'     => $firstIncomplete,
            'first_action_url'     => $firstIncomplete['action_url'] ?? null,
        ];
    }

    /**
     * @param  list<array{key?: string, complete: bool, action_url: string|null, label?: string, detail?: string}>  $items
     * @return array{key?: string, complete: bool, action_url: string|null, label?: string, detail?: string}|null
     */
    public function firstIncompleteItem(array $items): ?array
    {
        foreach ($items as $item) {
            if (($item['key'] ?? null) === 'face_approval') {
                continue;
            }
            if (! ($item['complete'] ?? false) && ! empty($item['action_url'])) {
                return $item;
            }
        }

        return null;
    }

    /** @param list<array{complete: bool, action_url: string|null}> $items */
    public function firstIncompleteActionUrl(array $items): ?string
    {
        return $this->firstIncompleteItem($items)['action_url'] ?? null;
    }

    public function withReturnUrl(?string $url, ?string $returnUrl): ?string
    {
        if (! $url || ! $returnUrl) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';
        $hashPos = strpos($url, '#');
        if ($hashPos === false) {
            return $url.$separator.'return='.urlencode($returnUrl);
        }

        return substr($url, 0, $hashPos)
            .$separator.'return='.urlencode($returnUrl)
            .substr($url, $hashPos);
    }

    /**
     * Checklist with every incomplete action_url carrying a return path back to the apply wizard.
     *
     * @return array{can_apply: bool, can_submit: bool, items: list<array>, completion_percent: int, profile_percent: int, first_incomplete: ?array, first_action_url: ?string}
     */
    public function checklistForApply(Customer $customer, ?string $returnUrl = null): array
    {
        $checklist = $this->checklist($customer);

        if (! $returnUrl) {
            return $checklist;
        }

        $checklist['items'] = collect($checklist['items'])->map(function (array $item) use ($returnUrl) {
            if (! empty($item['action_url'])) {
                $item['action_url'] = $this->withReturnUrl($item['action_url'], $returnUrl);
            }

            return $item;
        })->all();

        $firstIncomplete = $this->firstIncompleteItem($checklist['items']);
        $checklist['first_incomplete'] = $firstIncomplete;
        $checklist['first_action_url'] = $firstIncomplete['action_url'] ?? null;

        return $checklist;
    }

    /**
     * Map submit validation errors to the profile section the borrower should finish.
     *
     * @param  array<string, mixed>  $errors
     */
    public function profileActionUrlForValidationErrors(array $errors): ?string
    {
        $keys = array_keys($errors);

        foreach ($keys as $key) {
            if (str_starts_with($key, 'nok_') || $key === 'nok_name') {
                return route('site.borrower.profile', ['section' => 'personal', 'focus' => 'kin']).'#next-of-kin';
            }
        }

        foreach (['region', 'district', 'ward', 'street'] as $key) {
            if (array_key_exists($key, $errors)) {
                return route('site.borrower.profile', ['section' => 'residence']);
            }
        }

        foreach (['activity_type', 'income_range', 'activity_details'] as $key) {
            if (array_key_exists($key, $errors) || str_starts_with($key, 'activity_details')) {
                return route('site.borrower.profile', ['section' => 'activity']);
            }
        }

        foreach (['first_name', 'last_name', 'date_of_birth', 'gender', 'national_id'] as $key) {
            if (array_key_exists($key, $errors)) {
                return route('site.borrower.profile', ['section' => 'personal']);
            }
        }

        return null;
    }

    /** @deprecated Use onboardingBanner() — single source of truth for onboarding progress. */
    public function onboardingSteps(Customer $customer): array
    {
        $banner = $this->onboardingBanner($customer);

        return [
            'show'  => $banner['show'],
            'title' => $banner['title'],
            'steps' => collect($banner['items'])->map(fn (array $item, int $i) => [
                'number'   => $i + 1,
                'label'    => $item['label'],
                'complete' => $item['status'] === 'complete',
                'url'      => $item['action_url'],
            ])->values()->all(),
        ];
    }

    /**
     * Persistent onboarding hero banner — visible until all core requirements are complete.
     *
     * @return array{show: bool, title: string, percent: int, cta_url: string|null, items: list<array{key: string, label: string, status: string, action_url: string|null}>}
     */
    public function onboardingBanner(Customer $customer): array
    {
        $nida = app(NidaVerificationService::class);
        $profile = app(ProfileCompletionService::class);
        $validation = app(ProfileValidationService::class);
        $freshness = app(KycFreshnessService::class);
        $faceStatus = $customer->face_verification_status ?? 'incomplete';

        $registrationComplete = $customer->hasMembership();
        $nidaComplete = $nida->isVerified($customer);
        $nidaRevision = ($customer->nida_verification_status ?? '') === 'revision_required'
            || app(ProfileRevisionService::class)->hasOpenRevision($customer, 'nida')
            || app(ProfileRevisionService::class)->hasOpenRevision($customer, 'nida_docs');
        $faceComplete = in_array($faceStatus, ['pending', 'verified'], true);
        $facePending = $faceStatus === 'pending';
        $faceRevision = $faceStatus === 'revision_required'
            || app(ProfileRevisionService::class)->hasOpenRevision($customer, 'face');
        $activityComplete = $profile->isActivityComplete($customer);
        $residenceComplete = $profile->isResidenceComplete($customer);
        $kinComplete = $validation->isKinComplete($customer);
        $documentsComplete = $profile->isDocumentsComplete($customer);
        $staleKeys = $freshness->sectionsDueForRefresh($customer);
        $profilePercent = $profile->calculate($customer)['percent'];
        $requireIdentity = app(IdentityVerificationPolicyService::class)->requiredDuringProfileCreation();

        $items = [
            [
                'key'        => 'registration_fee',
                'label'      => __('borrower.onboarding.registration_fee'),
                'status'     => $registrationComplete ? 'complete' : 'missing',
                'action_url' => $registrationComplete ? null : route('site.membership.renew'),
            ],
        ];

        if ($requireIdentity) {
            $items[] = [
                'key'        => 'nida',
                'label'      => __('borrower.onboarding.nida'),
                'status'     => $nidaComplete ? 'complete' : ($nidaRevision ? 'stale' : 'missing'),
                'action_url' => $nidaComplete ? null : route('site.borrower.profile', ['section' => 'personal']),
            ];
            $items[] = [
                'key'        => 'face',
                'label'      => __('borrower.onboarding.face'),
                'status'     => $faceComplete
                    ? 'complete'
                    : ($facePending ? 'pending' : ($faceRevision ? 'stale' : 'missing')),
                'action_url' => $faceComplete ? null : route('site.borrower.face-verification'),
            ];
        }

        $items = array_merge($items, [
            [
                'key'        => 'activity',
                'label'      => __('borrower.onboarding.activity'),
                'status'     => $activityComplete ? (in_array('activity', $staleKeys, true) ? 'stale' : 'complete') : 'missing',
                'action_url' => route('site.borrower.profile', ['section' => 'activity']),
            ],
            [
                'key'        => 'residence',
                'label'      => __('borrower.onboarding.residence'),
                'status'     => $residenceComplete ? (in_array('residence', $staleKeys, true) ? 'stale' : 'complete') : 'missing',
                'action_url' => route('site.borrower.profile', ['section' => 'residence']),
            ],
            [
                'key'        => 'kin',
                'label'      => __('borrower.onboarding.kin'),
                'status'     => $kinComplete ? (in_array('kin', $staleKeys, true) ? 'stale' : 'complete') : 'missing',
                'action_url' => $kinComplete ? null : route('site.borrower.profile', ['section' => 'personal', 'focus' => 'kin']).'#next-of-kin',
            ],
        ]);

        if (! $documentsComplete) {
            $income = app(IncomeProofService::class);
            $needsLetter = $validation->requiresResidenceLetter() && ! $validation->hasResidenceLetter($customer);
            $needsIncome = $income->isRequired() && ! $income->satisfiesRequirement($customer);
            $documentsUrl = match (true) {
                $needsLetter && ! $needsIncome => route('site.borrower.profile', ['section' => 'residence']),
                $needsIncome && ! $needsLetter => route('site.borrower.profile', ['section' => 'kyc']),
                default => route('site.borrower.profile', ['section' => 'kyc']),
            };

            $documentsLabel = match (true) {
                $needsLetter && $needsIncome => __('borrower.profile.documents_proof'),
                $needsLetter => __('borrower.profile.residence_letter'),
                $needsIncome => __('borrower.loan_profile.sections.proof_of_income'),
                default => __('borrower.profile.documents_proof'),
            };

            $items[] = [
                'key'        => 'documents',
                'label'      => $documentsLabel,
                'status'     => 'missing',
                'action_url' => $documentsUrl,
            ];
        } elseif (in_array('documents', $staleKeys, true)) {
            $items[] = [
                'key'        => 'documents',
                'label'      => __('borrower.profile.documents_proof').' '.__('borrower.profile.refresh_required'),
                'status'     => 'stale',
                'action_url' => route('site.borrower.profile', ['section' => 'kyc']),
            ];
        }

        if ($staleKeys !== []) {
            $items[] = [
                'key'        => 'kyc_freshness',
                'label'      => __('borrower.onboarding.kyc_freshness'),
                'status'     => 'stale',
                'action_url' => route('site.borrower.kyc-reconfirm'),
            ];
        }

        $actionable = collect($items)->filter(fn (array $item) => ! in_array($item['status'], ['complete'], true))->values();
        $allComplete = $actionable->isEmpty();
        $firstIncomplete = $actionable->first();

        return [
            'show'     => ! $allComplete,
            'title'    => $staleKeys !== []
                ? __('borrower.onboarding.title_review')
                : __('borrower.onboarding.title_complete'),
            'percent'  => $profilePercent,
            'cta_url'  => $firstIncomplete['action_url'] ?? route('site.borrower.profile'),
            'items'    => $actionable->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function submitProfilePayload(Customer $customer): array
    {
        $customer->refresh();

        $nokName = filled($customer->nok_name)
            ? $customer->nok_name
            : KinName::full($customer->nok_first_name, $customer->nok_middle_name, $customer->nok_last_name);

        return [
            'first_name'       => $customer->first_name,
            'last_name'        => $customer->last_name,
            'date_of_birth'    => $customer->date_of_birth?->format('Y-m-d'),
            'gender'           => $customer->gender,
            'national_id'      => $customer->national_id,
            'region'           => $customer->region,
            'district'         => $customer->district,
            'ward'             => $customer->ward,
            'street'           => $customer->street ?: $customer->address,
            'nok_first_name'   => $customer->nok_first_name,
            'nok_middle_name'  => $customer->nok_middle_name,
            'nok_last_name'    => $customer->nok_last_name,
            'nok_name'         => $nokName,
            'nok_relationship' => $customer->nok_relationship,
            'nok_phone'        => $customer->nok_phone,
            'nok_region'       => $customer->nok_region,
            'nok_district'     => $customer->nok_district,
            'activity_type'    => $customer->activity_type ?? $customer->employment_type,
            'income_range'     => $customer->income_range,
            'activity_details' => $customer->activity_details ?? [],
        ];
    }

    public function mergeSubmitProfileFromCustomer(Request $request, Customer $customer): void
    {
        $request->merge($this->submitProfilePayload($customer));
    }

    public function hasCompleteResidence(Customer $customer): bool
    {
        return app(ProfileCompletionService::class)->isResidenceComplete($customer);
    }
}
