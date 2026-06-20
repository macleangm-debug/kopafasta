<?php

namespace App\Services;

use App\Models\ApplicationSignature;
use App\Models\GroupMemberInvitation;
use App\Models\LoanApplication;

class GroupMemberSignatureService
{
    public function recordForInvitation(
        GroupMemberInvitation $invitation,
        string $signerName,
        string $signatureData,
    ): GroupMemberInvitation {
        $invitation->update([
            'member_signer_name'    => $signerName,
            'member_signature_data' => $signatureData,
            'member_signed_at'      => now(),
        ]);

        return $invitation->fresh();
    }

    public function attachToApplication(
        LoanApplication $application,
        GroupMemberInvitation $invitation,
    ): ?ApplicationSignature {
        if (! filled($invitation->member_signature_data)) {
            return null;
        }

        return ApplicationSignature::updateOrCreate(
            [
                'loan_application_id'         => $application->id,
                'signer_type'                 => 'group_member',
                'group_member_invitation_id'  => $invitation->id,
            ],
            [
                'signer_name'    => $invitation->member_signer_name ?: $invitation->displayName(),
                'signature_data' => $invitation->member_signature_data,
                'signed_at'      => $invitation->member_signed_at ?? now(),
            ],
        );
    }

    public function hasSignature(GroupMemberInvitation $invitation): bool
    {
        return filled($invitation->member_signature_data);
    }
}
