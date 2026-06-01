<?php

namespace App\Services;

use App\Models\ApplicationSignature;
use App\Models\CustomerGuarantor;
use App\Models\GuarantorInvitation;
use App\Models\LoanApplication;

class GuarantorSignatureService
{
    public function record(
        LoanApplication $application,
        string $signerName,
        string $signatureData,
        ?CustomerGuarantor $link = null,
        ?GuarantorInvitation $invitation = null,
    ): ApplicationSignature {
        return ApplicationSignature::updateOrCreate(
            [
                'loan_application_id' => $application->id,
                'signer_type'         => 'guarantor',
            ],
            [
                'signer_name'             => $signerName,
                'signature_data'          => $signatureData,
                'signed_at'               => now(),
                'guarantor_invitation_id' => $invitation?->id,
            ],
        );
    }

    public function hasSignature(LoanApplication $application): bool
    {
        return ApplicationSignature::query()
            ->where('loan_application_id', $application->id)
            ->where('signer_type', 'guarantor')
            ->exists();
    }
}
