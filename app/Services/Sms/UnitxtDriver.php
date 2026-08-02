<?php

namespace App\Services\Sms;

/**
 * Unitxt SMS gateway (credentials pending).
 *
 * Health check validates that API key + Sender ID are present.
 * Live send will be wired once Unitxt publish their API contract.
 */
class UnitxtDriver implements SmsDriver
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly string $senderId,
        private readonly string $endpoint = '',
    ) {}

    public function send(string $to, string $message): array
    {
        if ($this->apiKey === '' || $this->senderId === '') {
            return [
                'ok' => false,
                'provider_id' => null,
                'error' => 'Unitxt API key or Sender ID is missing.',
            ];
        }

        // API integration pending — do not silently pretend success.
        return [
            'ok' => false,
            'provider_id' => null,
            'error' => 'Unitxt live send is not connected yet. Keep Force log mode on until API keys and endpoint are confirmed.',
        ];
    }

    public function healthCheck(): array
    {
        if ($this->apiKey === '' || $this->senderId === '') {
            return [
                'ok' => false,
                'message' => 'Unitxt credentials incomplete. Enter API key and Sender ID, then check again.',
                'provider' => 'unitxt',
            ];
        }

        if ($this->endpoint === '') {
            return [
                'ok' => true,
                'message' => 'Unitxt credentials saved (API key + Sender ID). Waiting for Unitxt API endpoint — health is configuration-only until the API is connected.',
                'provider' => 'unitxt',
            ];
        }

        return [
            'ok' => true,
            'message' => 'Unitxt credentials and endpoint are configured. Live connectivity will be verified once the API contract is available.',
            'provider' => 'unitxt',
        ];
    }
}
