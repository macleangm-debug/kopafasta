<?php

namespace App\Contracts;

use App\DataTransferObjects\CrbIdentityResult;

interface CrbClientInterface
{
    /**
     * Verify a consumer identity via CRB Live Request (NIDA / identifier lookup).
     */
    public function verifyConsumerIdentity(
        string $identifierNumber,
        ?string $fullName = null,
        ?string $dateOfBirth = null,
        ?string $mobile = null,
    ): CrbIdentityResult;
}
