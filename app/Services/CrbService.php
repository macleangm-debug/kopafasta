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
        // Production never uses stub/fixture CRB as underwriting evidence.
        // Missing live credentials must fail closed (unavailable), not fall back to stub.
        if (app()->environment('production')) {
            return false;
        }

        $kyc = Setting::group('kyc');

        if (! empty($kyc['crb_sandbox'])) {
            return true;
        }

        return config('crb.driver') !== 'live';
    }

    /**
     * Operational diagnostics for Admin — no secrets.
     *
     * @return array{
     *     provider: string,
     *     mode: string,
     *     mode_short: string,
     *     driver_config: string,
     *     endpoint_configured: bool,
     *     credentials_configured: bool,
     *     sandbox_setting: bool,
     *     production_stub_blocked: bool,
     *     last_success_at: string|null,
     *     last_error: string|null,
     *     last_checked_at: string|null
     * }
     */
    public function operationalStatus(): array
    {
        $kyc = Setting::group('kyc');
        $endpoint = filled($kyc['crb_endpoint'] ?? null) || filled(config('crb.endpoint'));
        $email = filled($kyc['crb_email'] ?? null) || filled(config('crb.email'));
        $password = filled(config('crb.password'));
        $health = Setting::get('integrations.health.crb', []);
        $health = is_array($health) ? $health : [];
        $stub = $this->usesStub();

        return [
            'provider' => 'Dun & Bradstreet Tanzania (D&B Live CIR)',
            'mode' => $stub ? 'TEST / CRB STUB' : 'D&B LIVE',
            'mode_short' => $stub ? 'STUB' : 'LIVE',
            'driver_config' => (string) config('crb.driver', 'stub'),
            'endpoint_configured' => $endpoint,
            'credentials_configured' => $email && $password,
            'sandbox_setting' => ! empty($kyc['crb_sandbox']),
            'production_stub_blocked' => app()->environment('production'),
            'last_success_at' => ! empty($health['ok']) ? ($health['checked_at'] ?? null) : null,
            'last_error' => empty($health['ok']) ? ($health['message'] ?? $health['reason'] ?? null) : null,
            'last_checked_at' => $health['checked_at'] ?? null,
        ];
    }
}
