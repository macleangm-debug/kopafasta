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
        $requireNida = (bool) (Setting::group('kyc')['require_nida'] ?? true);

        $items = [
            [
                'key'        => 'registration_fee',
                'label'      => 'Registration fee paid',
                'complete'   => $customer->hasMembership(),
                'pending'    => false,
                'detail'     => $customer->hasMembership() ? 'Membership issued' : 'Pay registration fee to activate membership',
                'action_url' => $customer->hasMembership() ? null : route('site.membership.renew'),
            ],
            [
                'key'        => 'membership',
                'label'      => 'Membership active',
                'complete'   => $customer->isMembershipActive() || $customer->isMembershipInGrace(),
                'pending'    => false,
                'detail'     => $customer->isMembershipActive()
                    ? 'Valid until '.optional($customer->membership_expires_at)->format('d M Y')
                    : 'Renew membership to apply',
                'action_url' => ($customer->isMembershipActive() || $customer->isMembershipInGrace()) ? null : route('site.membership.renew'),
            ],
        ];

        if ($requireNida) {
            $items[] = [
                'key'        => 'nida',
                'label'      => 'NIDA verified',
                'complete'   => $nida->isVerified($customer),
                'pending'    => in_array($customer->nida_verification_status, ['name_mismatch', 'multihit'], true),
                'detail'     => match (true) {
                    $nida->isVerified($customer) => 'Identity confirmed',
                    $customer->nida_verification_status === 'name_mismatch' => 'Name mismatch — review on profile',
                    $customer->nida_verification_status === 'multihit' => 'Select your record on profile',
                    default => 'Enter and verify your NIDA number',
                },
                'action_url' => $nida->isVerified($customer) ? null : route('site.borrower.profile', ['section' => 'personal']),
            ];
        }

        $faceStatus = $customer->face_verification_status ?? 'incomplete';
        $faceSubmitted = in_array($faceStatus, ['pending', 'verified'], true);

        $items[] = [
            'key'        => 'face_submitted',
            'label'      => 'Face verification submitted',
            'complete'   => $faceSubmitted,
            'pending'    => false,
            'detail'     => match ($faceStatus) {
                'verified', 'pending' => 'Photos captured and uploaded',
                'rejected' => 'Rejected — please recapture photos',
                default    => 'Complete the 4-step face capture',
            },
            'action_url' => $faceSubmitted ? null : route('site.borrower.face-verification'),
        ];

        $items[] = [
            'key'        => 'face_approval',
            'label'      => 'Face verification approval',
            'complete'   => $face->canApply($customer),
            'pending'    => $faceStatus === 'pending',
            'detail'     => match ($faceStatus) {
                'verified' => 'Approved by underwriting',
                'pending'  => 'Awaiting loan officer review',
                'rejected' => 'Rejected — please recapture photos',
                default    => 'Submit face photos first',
            },
            'action_url' => $faceStatus === 'rejected' ? route('site.borrower.face-verification') : null,
        ];

        $profileResult = $profile->calculate($customer);
        $items[] = [
            'key'        => 'profile',
            'label'      => 'Profile completion',
            'complete'   => $profile->meetsThreshold($customer),
            'pending'    => ! $profile->meetsThreshold($customer),
            'detail'     => $profileResult['percent'].'% complete (minimum '.$profileResult['threshold'].'%)',
            'action_url' => $profile->meetsThreshold($customer) ? null : route('site.borrower.profile'),
        ];

        if (! $freshness->canApply($customer)) {
            $staleSections = $freshness->sectionsDueForRefresh($customer);
            $staleLabels = $freshness->staleSectionLabels($customer);
            $items[] = [
                'key'        => 'kyc_freshness',
                'label'      => 'Profile review due',
                'complete'   => false,
                'pending'    => true,
                'detail'     => $staleLabels !== []
                    ? __('borrower.kyc.stale_sections_detail', ['sections' => implode(', ', $staleLabels)])
                    : 'Confirm activity and residence details are current',
                'stale_sections' => $staleSections,
                'action_url' => route('site.borrower.kyc-reconfirm'),
            ];
        }

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

        $completed = collect($items)->where('complete', true)->count();
        $total = count($items);

        $canApply = collect($items)
            ->reject(fn (array $item) => in_array($item['key'], ['face_approval'], true))
            ->every(fn (array $item) => $item['complete']);

        return [
            'can_apply'            => $canApply,
            'can_submit'           => $canApply,
            'items'                => $items,
            'completion_percent'   => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            'profile_percent'      => $profileResult['percent'],
            'first_action_url'     => $this->firstIncompleteActionUrl($items),
        ];
    }

    /** @param list<array{complete: bool, action_url: string|null}> $items */
    public function firstIncompleteActionUrl(array $items): ?string
    {
        foreach ($items as $item) {
            if (! ($item['complete'] ?? false) && ! empty($item['action_url'])) {
                return $item['action_url'];
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
        $faceComplete = in_array($faceStatus, ['pending', 'verified'], true);
        $facePending = in_array($faceStatus, ['pending'], true);
        $activityComplete = $profile->isActivityComplete($customer);
        $residenceComplete = $profile->isResidenceComplete($customer);
        $kinComplete = $validation->isKinComplete($customer);
        $documentsComplete = $profile->isDocumentsComplete($customer);
        $staleKeys = $freshness->sectionsDueForRefresh($customer);
        $profilePercent = $profile->calculate($customer)['percent'];

        $items = [
            [
                'key'        => 'registration_fee',
                'label'      => 'Registration fee paid',
                'status'     => $registrationComplete ? 'complete' : 'missing',
                'action_url' => $registrationComplete ? null : route('site.membership.renew'),
            ],
            [
                'key'        => 'nida',
                'label'      => 'NIDA verified',
                'status'     => $nidaComplete ? 'complete' : 'missing',
                'action_url' => $nidaComplete ? null : route('site.borrower.profile', ['section' => 'personal']),
            ],
            [
                'key'        => 'face',
                'label'      => 'Face verification',
                'status'     => $faceComplete ? 'complete' : ($facePending ? 'pending' : 'missing'),
                'action_url' => $faceComplete ? null : route('site.borrower.face-verification'),
            ],
            [
                'key'        => 'activity',
                'label'      => 'Activity information',
                'status'     => $activityComplete ? (in_array('activity', $staleKeys, true) ? 'stale' : 'complete') : 'missing',
                'action_url' => route('site.borrower.profile', ['section' => 'activity']),
            ],
            [
                'key'        => 'residence',
                'label'      => 'Residence information',
                'status'     => $residenceComplete ? (in_array('residence', $staleKeys, true) ? 'stale' : 'complete') : 'missing',
                'action_url' => route('site.borrower.profile', ['section' => 'residence']),
            ],
            [
                'key'        => 'kin',
                'label'      => 'Next of kin',
                'status'     => $kinComplete ? (in_array('kin', $staleKeys, true) ? 'stale' : 'complete') : 'missing',
                'action_url' => $kinComplete ? null : route('site.borrower.profile', ['section' => 'personal', 'focus' => 'kin']).'#next-of-kin',
            ],
        ];

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
                'label'      => 'Confirm activity and residence details',
                'status'     => 'stale',
                'action_url' => route('site.borrower.kyc-reconfirm'),
            ];
        }

        $actionable = collect($items)->filter(fn (array $item) => ! in_array($item['status'], ['complete'], true))->values();
        $allComplete = $actionable->isEmpty();
        $firstIncomplete = $actionable->first();

        return [
            'show'     => ! $allComplete,
            'title'    => $staleKeys !== [] ? 'Profile review due' : 'Complete your profile',
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
