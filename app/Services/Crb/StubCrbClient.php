<?php

namespace App\Services\Crb;

use App\Contracts\CrbClientInterface;
use App\DataTransferObjects\CrbIdentityResult;
use App\Support\NidaNumber;

class StubCrbClient implements CrbClientInterface
{
    public function verifyConsumerIdentity(
        string $identifierNumber,
        ?string $fullName = null,
        ?string $dateOfBirth = null,
        ?string $mobile = null,
    ): CrbIdentityResult {
        $formatted = NidaNumber::format($identifierNumber);

        if (! $formatted) {
            return CrbIdentityResult::failed('Invalid NIDA number format.');
        }

        $name = trim((string) $fullName);
        if ($name === '') {
            $name = 'Stub Verified Citizen';
        }

        $parts = preg_split('/\s+/', $name) ?: [$name];
        $last = count($parts) > 1 ? array_pop($parts) : 'Citizen';
        $first = implode(' ', $parts) ?: 'Stub';

        return CrbIdentityResult::verified(
            fullName: trim($first.' '.$last),
            firstName: $first,
            lastName: $last,
            dateOfBirth: $dateOfBirth,
            gender: 'male',
            nationalId: $formatted,
            searchScore: '100%',
            crbRuid: 'stub-'.substr(NidaNumber::digits($formatted), -8),
            raw: ['driver' => 'stub'],
        );
    }
}
