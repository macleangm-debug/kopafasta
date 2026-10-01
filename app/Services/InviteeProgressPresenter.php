<?php

namespace App\Services;

/**
 * Shared five-step invitee journey for guarantor + external group-member progress.
 *
 * Mwaliko umetumwa → Mwaliko umekubaliwa → Akaunti imefunguliwa → Wasifu → Tayari kwa ukaguzi
 */
class InviteeProgressPresenter
{
    /**
     * @return list<array{key: string, label: string, complete: bool, current: bool}>
     */
    public function steps(
        bool $acceptedDone,
        bool $accountDone,
        bool $profileDone,
        bool $readyDone,
        ?int $profilePercent = null,
    ): array {
        $invitedDone = true;

        $current = match (true) {
            ! $acceptedDone => 'accepted',
            ! $accountDone => 'account',
            ! $profileDone => 'profile',
            ! $readyDone => 'ready',
            default => 'ready',
        };

        return [
            [
                'key' => 'invited',
                'label' => __('borrower.apply.guarantor_progress.invited'),
                'complete' => $invitedDone,
                'current' => false,
            ],
            [
                'key' => 'accepted',
                'label' => __('borrower.apply.guarantor_progress.accepted'),
                'complete' => $acceptedDone,
                'current' => $current === 'accepted',
            ],
            [
                'key' => 'account',
                'label' => __('borrower.apply.guarantor_progress.account'),
                'complete' => $accountDone || $readyDone,
                'current' => $current === 'account',
            ],
            [
                'key' => 'profile',
                'label' => $profilePercent !== null && $profilePercent > 0 && ! $profileDone
                    ? __('borrower.apply.guarantor_progress.profile_pct', ['percent' => $profilePercent])
                    : __('borrower.apply.guarantor_progress.profile'),
                'complete' => $profileDone || $readyDone,
                'current' => $current === 'profile',
            ],
            [
                'key' => 'ready',
                'label' => __('borrower.apply.guarantor_progress.ready'),
                'complete' => $readyDone,
                'current' => $current === 'ready',
            ],
        ];
    }

    public function badgeLabel(
        bool $acceptedDone,
        bool $accountDone,
        bool $profileDone,
        bool $readyDone,
        ?int $profilePercent = null,
    ): string {
        return match (true) {
            ! $acceptedDone => __('borrower.apply.guarantor_status.invitation_sent'),
            ! $accountDone => __('borrower.apply.guarantor_status.invitation_accepted'),
            ! $profileDone && ($profilePercent ?? 0) <= 0 => __('borrower.apply.guarantor_status.account_opened'),
            ! $profileDone => __('borrower.apply.guarantor_status.profile_in_progress'),
            ! $readyDone => __('borrower.apply.guarantor_status.profile_in_progress'),
            default => __('borrower.apply.guarantor_status.ready_for_review'),
        };
    }
}
