<?php

namespace App\Services;

use App\Contracts\CrbClientInterface;
use App\DataTransferObjects\CrbIdentityResult;
use App\Models\Setting;
use App\Services\Crb\DnbLiveCrbClient;
use App\Services\Crb\StubCrbClient;

class CrbService
{
    public function __construct(
        private readonly CrbClientInterface $client,
    ) {}

    public function verifyConsumerIdentity(
        string $identifierNumber,
        ?string $fullName = null,
        ?string $dateOfBirth = null,
        ?string $mobile = null,
    ): CrbIdentityResult {
        if ($this->usesStub()) {
            return app(StubCrbClient::class)->verifyConsumerIdentity(
                $identifierNumber,
                $fullName,
                $dateOfBirth,
                $mobile,
            );
        }

        return $this->client->verifyConsumerIdentity(
            $identifierNumber,
            $fullName,
            $dateOfBirth,
            $mobile,
        );
    }

    public function fetchByEntityKey(
        string $searchRequestId,
        string $entityKey,
        string $identifierNumber,
    ): CrbIdentityResult {
        if ($this->usesStub()) {
            return app(StubCrbClient::class)->fetchByEntityKey($searchRequestId, $entityKey, $identifierNumber);
        }

        if ($this->client instanceof DnbLiveCrbClient) {
            return $this->client->fetchByEntityKey($searchRequestId, $entityKey, $identifierNumber);
        }

        return CrbIdentityResult::failed('CRB entity lookup is not available.');
    }

    public function usesStub(): bool
    {
        $kyc = Setting::group('kyc');

        if (! empty($kyc['crb_sandbox'])) {
            return true;
        }

        return config('crb.driver') !== 'live';
    }
}
