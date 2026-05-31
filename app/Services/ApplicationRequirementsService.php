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

        $items[] = [
            'key'        => 'face',
            'label'      => 'Face verification approved',
            'complete'   => $face->canApply($customer),
            'pending'    => ($customer->face_verification_status ?? '') === 'pending',
            'detail'     => match ($customer->face_verification_status) {
                'verified' => 'Approved by underwriting',
                'pending'  => 'Photos submitted — awaiting review',
                'rejected' => 'Rejected — please recapture photos',
                default    => 'Complete the 4-step face capture',
            },
            'action_url' => route('site.borrower.face-verification'),
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
                'label'      => 'KYC reconfirmation',
                'complete'   => false,
                'pending'    => true,
                'detail'     => 'Confirm activity and residence details are current',
                'action_url' => route('site.borrower.kyc-reconfirm'),
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
        ];
    }

    public function onboardingSteps(Customer $customer): array
    {
        $nida = app(NidaVerificationService::class);
        $face = app(FaceVerificationService::class);

        return [
            'show'  => $customer->hasMembership() && (! $nida->isVerified($customer) || ! $face->isVerified($customer)),
            'title' => 'Complete your identity verification',
            'steps' => [
                [
                    'number'   => 1,
                    'label'    => 'Enter NIDA number',
                    'complete' => $nida->isVerified($customer),
                    'url'      => route('site.borrower.profile', ['section' => 'personal']),
                ],
                [
                    'number'   => 2,
                    'label'    => 'Complete face verification',
                    'complete' => in_array($customer->face_verification_status, ['pending', 'verified'], true),
                    'url'      => route('site.borrower.face-verification'),
                ],
            ],
        ];
    }
}
