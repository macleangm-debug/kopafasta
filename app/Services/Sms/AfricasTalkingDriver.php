<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Africa's Talking SMS driver.
 *
 * Endpoint: https://api.africastalking.com/version1/messaging
 * Auth:     header `apiKey`
 */
class AfricasTalkingDriver implements SmsDriver
{
    public function __construct(
        private readonly string $username,
        private readonly string $apiKey,
        private readonly string $senderId,
        private readonly string $endpoint = 'https://api.africastalking.com/version1/messaging',
    ) {}

    public function send(string $to, string $message): array
    {
        try {
            $response = Http::asForm()
                ->withHeaders(['apiKey' => $this->apiKey, 'Accept' => 'application/json'])
                ->timeout(15)
                ->post($this->endpoint, [
                    'username' => $this->username,
                    'to'       => $this->normalize($to),
                    'message'  => $message,
                    'from'     => $this->senderId ?: null,
                ]);

            $body = $response->json();
            $first = $body['SMSMessageData']['Recipients'][0] ?? null;
            $status = $first['status'] ?? null;

            if ($response->successful() && in_array($status, ['Success', 'Sent'], true)) {
                return ['ok' => true, 'provider_id' => (string) ($first['messageId'] ?? ''), 'error' => null];
            }

            return [
                'ok' => false,
                'provider_id' => null,
                'error' => $status ? "AT: {$status}" : ('HTTP '.$response->status()),
            ];
        } catch (\Throwable $e) {
            Log::error('[SMS:africastalking] '.$e->getMessage());
            return ['ok' => false, 'provider_id' => null, 'error' => $e->getMessage()];
        }
    }

    public function healthCheck(): array
    {
        if ($this->username === '' || $this->apiKey === '') {
            return [
                'ok' => false,
                'message' => "Africa's Talking username or API key is missing.",
                'provider' => 'africastalking',
            ];
        }

        try {
            $response = Http::withHeaders(['apiKey' => $this->apiKey, 'Accept' => 'application/json'])
                ->timeout(12)
                ->get('https://api.africastalking.com/version1/user', [
                    'username' => $this->username,
                ]);

            if ($response->successful()) {
                $balance = $response->json('UserData.balance') ?? $response->json('balance');

                return [
                    'ok' => true,
                    'message' => $balance
                        ? "Africa's Talking connected. Balance: {$balance}"
                        : "Africa's Talking connected. Sender ID: ".($this->senderId ?: '(default)'),
                    'provider' => 'africastalking',
                ];
            }

            return [
                'ok' => false,
                'message' => "Africa's Talking health check failed HTTP ".$response->status().': '.substr((string) $response->body(), 0, 180),
                'provider' => 'africastalking',
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => "Africa's Talking health check error: ".$e->getMessage(),
                'provider' => 'africastalking',
            ];
        }
    }

    private function normalize(string $phone): string
    {
        $p = preg_replace('/[^\d]/', '', $phone) ?? '';
        if (str_starts_with($p, '0')) {
            $p = '255'.substr($p, 1);
        }
        return '+'.$p;
    }
}
