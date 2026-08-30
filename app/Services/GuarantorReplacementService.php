<?php

namespace App\Services;

use App\Models\CustomerGuarantor;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Replace a required guarantor without restarting the loan application.
 * Mirrors group-member replacement: previous link stays historically attached.
 */
class GuarantorReplacementService
{
    public function __construct(
        private readonly GuarantorInvitationService $invitations,
        private readonly UnderwritingSettingsService $settings,
    ) {}

    public function replaceInternal(
        LoanApplication $application,
        CustomerGuarantor $current,
        User $actor,
        string $reason,
        string $membershipId,
        string $phone,
        string $name,
    ): array {
        $this->assertReplaceable($application, $current);

        return DB::transaction(function () use ($application, $current, $actor, $reason, $membershipId, $phone, $name): array {
            $this->markReplaced($application, $current, $actor, $reason);
            [$link, $invitation] = $this->invitations->attachInternal(
                $application->customer,
                $application,
                $membershipId,
                $phone,
                $name,
            );

            $this->rememberReplacement($application, $current, $link, $actor, $reason, 'internal');

            return [
                'previous' => $current->fresh(),
                'link' => $link,
                'invitation' => $invitation,
            ];
        });
    }

    public function replaceExternal(
        LoanApplication $application,
        CustomerGuarantor $current,
        User $actor,
        string $reason,
        string $firstName,
        ?string $middleName,
        string $lastName,
        string $phone,
        ?string $email,
        string $relationship,
        string $region,
        string $district,
        string $channel = 'sms',
    ): array {
        $this->assertReplaceable($application, $current);

        return DB::transaction(function () use (
            $application, $current, $actor, $reason, $firstName, $middleName, $lastName,
            $phone, $email, $relationship, $region, $district, $channel
        ): array {
            $this->markReplaced($application, $current, $actor, $reason);
            [$link, $invitation] = $this->invitations->attachExternal(
                $application->customer,
                $application,
                $firstName,
                $middleName,
                $lastName,
                $phone,
                $email,
                $relationship,
                $region,
                $district,
                $channel,
            );

            $this->rememberReplacement($application, $current, $link, $actor, $reason, 'external');

            return [
                'previous' => $current->fresh(),
                'link' => $link,
                'invitation' => $invitation,
            ];
        });
    }

    private function assertReplaceable(LoanApplication $application, CustomerGuarantor $current): void
    {
        if ((int) $current->loan_application_id !== (int) $application->id) {
            throw new \InvalidArgumentException('This guarantor is not attached to this application.');
        }
        if (in_array((string) $current->status, ['replaced'], true)) {
            throw new \InvalidArgumentException('This guarantor has already been replaced.');
        }
        if ($this->settings->guarantorHardFailAction() === CreditEligibilityPolicyService::GUARANTOR_FAIL_REJECT) {
            throw new \InvalidArgumentException('Product policy rejects the application instead of replacing the guarantor.');
        }
    }

    private function markReplaced(
        LoanApplication $application,
        CustomerGuarantor $current,
        User $actor,
        string $reason,
    ): void {
        $current->update([
            'status' => 'replaced',
        ]);

        $invitation = $current->invitation;
        if ($invitation && in_array((string) $invitation->status, ['pending', 'accepted'], true)) {
            $invitation->update([
                'status' => 'replaced',
                'responded_at' => now(),
                'response_notes' => $reason,
            ]);
        }

        $payload = is_array($application->screening_payload) ? $application->screening_payload : [];
        $payload['guarantor_replacements'] = $payload['guarantor_replacements'] ?? [];
        $payload['guarantor_replacements'][] = [
            'previous_link_id' => $current->id,
            'reason' => $reason,
            'hours_allowed' => $this->settings->guarantorReplacementHours(),
            'by' => $actor->id,
            'at' => now()->toIso8601String(),
            'status' => 'replaced',
        ];
        $application->forceFill(['screening_payload' => $payload])->save();
    }

    private function rememberReplacement(
        LoanApplication $application,
        CustomerGuarantor $previous,
        CustomerGuarantor $next,
        User $actor,
        string $reason,
        string $type,
    ): void {
        $payload = is_array($application->screening_payload) ? $application->screening_payload : [];
        $rows = (array) ($payload['guarantor_replacements'] ?? []);
        foreach ($rows as $i => $row) {
            if ((int) ($row['previous_link_id'] ?? 0) === (int) $previous->id && empty($row['new_link_id'])) {
                $rows[$i]['new_link_id'] = $next->id;
                $rows[$i]['type'] = $type;
                $rows[$i]['by'] = $actor->id;
                $rows[$i]['reason'] = $reason;
            }
        }
        $payload['guarantor_replacements'] = $rows;
        $application->forceFill(['screening_payload' => $payload])->save();
    }
}
