<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Setting;

class ApplicationRequirementsService
{
    /** @return array{can_apply: bool, items: list<array{key: string, label: string, complete: bool, action_url: string|null, detail: string}>} */
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
            $items[] = [
                'key'        => 'kyc_freshness',
                'label'      => 'Profile review due',
                'complete'   => false,
                'pending'    => true,
                'detail'     => 'Confirm activity and residence details are current',
                'action_url' => route('site.borrower.kyc-reconfirm'),
            ];
        }

        if ($this->requiresIncomeProof($customer)) {
            $items[] = [
                'key'        => 'income_proof',
                'label'      => 'Proof of income',
                'complete'   => false,
                'pending'    => true,
                'detail'     => 'Upload a 6-month bank or mobile money statement on your activity profile',
                'action_url' => route('site.borrower.profile', ['section' => 'activity']),
            ];
        }

        $completed = collect($items)->where('complete', true)->count();
        $total = count($items);

        $canApply = collect($items)->every(fn (array $item) => $item['complete']);

        return [
            'can_apply'            => $canApply,
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

    private function requiresIncomeProof(Customer $customer): bool
    {
        if (! (bool) (Setting::group('kyc')['require_income_proof'] ?? false)) {
            return false;
        }

        $types = ['bank_statement', 'mobile_money_statement', 'mpesa_statement'];
        $uploaded = \App\Models\CustomerDocument::query()
            ->where('customer_id', $customer->id)
            ->whereHas('documentType', fn ($q) => $q->whereIn('code', $types))
            ->exists();

        return ! $uploaded;
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
        $freshness = app(KycFreshnessService::class);
        $faceStatus = $customer->face_verification_status ?? 'incomplete';

        $registrationComplete = $customer->hasMembership();
        $nidaComplete = $nida->isVerified($customer);
        $faceComplete = $faceStatus === 'verified';
        $facePending = in_array($faceStatus, ['pending'], true);
        $activityComplete = $profile->isActivityComplete($customer);
        $residenceComplete = $profile->isResidenceComplete($customer);
        $kinComplete = filled($customer->nok_name) && filled($customer->nok_phone) && filled($customer->nok_relationship)
            && filled($customer->nok_region) && filled($customer->nok_district);
        $documentsComplete = $profile->isDocumentsComplete($customer);
        $staleKeys = $freshness->sectionsDueForRefresh($customer);

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
                'status'     => ($activityComplete && ! in_array('activity', $staleKeys, true)) ? 'complete' : 'missing',
                'action_url' => route('site.borrower.profile', ['section' => 'activity']),
            ],
            [
                'key'        => 'residence',
                'label'      => 'Residence information',
                'status'     => ($residenceComplete && ! in_array('residence', $staleKeys, true)) ? 'complete' : 'missing',
                'action_url' => route('site.borrower.profile', ['section' => 'residence']),
            ],
            [
                'key'        => 'kin',
                'label'      => 'Next of kin',
                'status'     => $kinComplete ? 'complete' : 'missing',
                'action_url' => $kinComplete ? null : route('site.borrower.profile', ['section' => 'kin']),
            ],
        ];

        if (! $documentsComplete || in_array('documents', $staleKeys, true)) {
            $items[] = [
                'key'        => 'documents',
                'label'      => in_array('documents', $staleKeys, true)
                    ? 'Proof of income & residence letter (refresh required)'
                    : 'Proof of income & residence letter',
                'status'     => 'missing',
                'action_url' => route('site.borrower.profile', ['section' => 'kyc']),
            ];
        }

        if ($freshness->isStale($customer)) {
            $items[] = [
                'key'        => 'kyc_freshness',
                'label'      => 'Confirm activity & residence details',
                'status'     => 'missing',
                'action_url' => route('site.borrower.kyc-reconfirm'),
            ];
        }

        $actionable = collect($items)->filter(fn (array $item) => $item['status'] !== 'complete')->values();
        $allComplete = $actionable->isEmpty();
        $completed = collect($items)->where('status', 'complete')->count();
        $total = count($items);
        $firstIncomplete = $actionable->first();

        return [
            'show'     => ! $allComplete,
            'title'    => $freshness->isStale($customer) ? 'Profile review due' : 'Complete your profile',
            'percent'  => $total > 0 ? (int) round(($completed / $total) * 100) : 100,
            'cta_url'  => $firstIncomplete['action_url'] ?? route('site.borrower.profile'),
            'items'    => $actionable->all(),
        ];
    }
}
