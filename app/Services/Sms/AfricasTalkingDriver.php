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

    private function normalize(string $phone): string
    {
        $p = preg_replace('/[^\d]/', '', $phone) ?? '';
        if (str_starts_with($p, '0')) {
            $p = '255'.substr($p, 1);
        }
        return '+'.$p;
    }
}
