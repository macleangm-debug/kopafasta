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
                'detail'     => $customer->hasMembership() ? 'Membership issued' : 'Pay registration fee to activate membership',
                'action_url' => $customer->hasMembership() ? null : route('site.membership.renew'),
            ],
            [
                'key'        => 'membership',
                'label'      => 'Membership active',
                'complete'   => $customer->isMembershipActive() || $customer->isMembershipInGrace(),
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
                'detail'     => $nida->isVerified($customer) ? 'Identity confirmed via CRB' : 'Enter and verify your NIDA number',
                'action_url' => $nida->isVerified($customer) ? null : route('site.borrower.profile', ['section' => 'personal']),
            ];
        }

        $items[] = [
            'key'        => 'face',
            'label'      => 'Face verification approved',
            'complete'   => $face->canApply($customer),
            'detail'     => match ($customer->face_verification_status) {
                'verified' => 'Approved by underwriting',
                'pending'  => 'Photos submitted — awaiting review',
                'rejected' => 'Rejected — please recapture photos',
                default    => 'Complete the 4-step face capture',
            },
            'action_url' => $face->canApply($customer) ? route('site.borrower.face-verification') : route('site.borrower.face-verification'),
        ];

        $profileResult = $profile->calculate($customer);
        $items[] = [
            'key'        => 'profile',
            'label'      => 'Profile completion',
            'complete'   => $profile->meetsThreshold($customer),
            'detail'     => $profileResult['percent'].'% complete (minimum '.$profileResult['threshold'].'%)',
            'action_url' => $profile->meetsThreshold($customer) ? null : route('site.borrower.profile'),
        ];

        if (! $freshness->canApply($customer)) {
            $items[] = [
                'key'        => 'kyc_freshness',
                'label'      => 'KYC reconfirmation',
                'complete'   => false,
                'detail'     => 'Confirm activity and residence details are current',
                'action_url' => route('site.borrower.kyc-reconfirm'),
            ];
        }

        $canApply = collect($items)->every(fn (array $item) => $item['complete']);

        return [
            'can_apply' => $canApply,
            'items'     => $items,
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
