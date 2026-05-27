<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Beem Africa SMS gateway driver.
 *
 * Endpoint: https://apisms.beem.africa/v1/send
 * Auth:     HTTP Basic (api_key:secret_key)
 */
class BeemDriver implements SmsDriver
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $secretKey,
        private readonly string $senderId,
        private readonly string $endpoint = 'https://apisms.beem.africa/v1/send',
    ) {}

    public function send(string $to, string $message): array
    {
        try {
            $response = Http::withBasicAuth($this->apiKey, $this->secretKey)
                ->acceptJson()
                ->timeout(15)
                ->post($this->endpoint, [
                    'source_addr'   => $this->senderId,
                    'schedule_time' => '',
                    'encoding'      => 0,
                    'message'       => $message,
                    'recipients'    => [['recipient_id' => 1, 'dest_addr' => $this->normalize($to)]],
                ]);

            if ($response->successful()) {
                return [
                    'ok' => true,
                    'provider_id' => (string) ($response->json('request_id') ?? $response->json('id') ?? ''),
                    'error' => null,
                ];
            }

            return [
                'ok' => false,
                'provider_id' => null,
                'error' => 'HTTP '.$response->status().': '.substr((string) $response->body(), 0, 200),
            ];
        } catch (\Throwable $e) {
            Log::error('[SMS:beem] '.$e->getMessage());
            return ['ok' => false, 'provider_id' => null, 'error' => $e->getMessage()];
        }
    }

    private function normalize(string $phone): string
    {
        $p = preg_replace('/[^\d]/', '', $phone) ?? '';
        if (str_starts_with($p, '0')) {
            $p = '255'.substr($p, 1);
        }
        return $p;
    }
}
