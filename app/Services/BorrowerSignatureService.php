<?php

namespace App\Services;

use App\Models\ApplicationSignature;
use App\Models\LoanApplication;

class BorrowerSignatureService
{
    public function hasSignature(LoanApplication $application): bool
    {
        return $this->signature($application) !== null;
    }

    public function signature(LoanApplication $application): ?ApplicationSignature
    {
        $application->loadMissing('signatures');

        $signature = $application->signatures->firstWhere('signer_type', 'borrower');

        return filled($signature?->signature_data) ? $signature : null;
    }
}
