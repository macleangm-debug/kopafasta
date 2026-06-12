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
        $signature = ApplicationSignature::updateOrCreate(
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

        app(LoanAgreementService::class)->refreshGuarantorOnDocuments($application->fresh());

        return $signature;
    }

    public function hasSignature(LoanApplication $application): bool
    {
        return ApplicationSignature::query()
            ->where('loan_application_id', $application->id)
            ->where('signer_type', 'guarantor')
            ->exists();
    }

    public function recordForInvitation(
        GuarantorInvitation $invitation,
        string $signerName,
        string $signatureData,
    ): GuarantorInvitation {
        $invitation->update([
            'guarantor_signer_name'    => $signerName,
            'guarantor_signature_data' => $signatureData,
            'guarantor_signed_at'      => now(),
        ]);

        return $invitation->fresh();
    }

    public function attachToApplication(
        GuarantorInvitation $invitation,
        LoanApplication $application,
        ?CustomerGuarantor $link = null,
    ): ?ApplicationSignature {
        if (! filled($invitation->guarantor_signature_data)) {
            return null;
        }

        return $this->record(
            $application,
            trim($invitation->guarantor_signer_name ?? ''),
            $invitation->guarantor_signature_data,
            $link ?? $invitation->customerGuarantor,
            $invitation,
        );
    }
}
