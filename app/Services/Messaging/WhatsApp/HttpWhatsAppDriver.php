<?php

namespace App\Services\Messaging\WhatsApp;

use Illuminate\Support\Facades\Http;

class HttpWhatsAppDriver implements WhatsAppDriver
{
    public function __construct(
        protected string $apiUrl,
        protected string $apiToken,
        protected string $fromNumber,
    ) {}

    public function send(string $to, string $message): array
    {
        if ($this->apiUrl === '' || $this->apiToken === '') {
            return ['ok' => false, 'provider_id' => null, 'error' => 'WhatsApp API not configured'];
        }

        try {
            $response = Http::withToken($this->apiToken)
                ->acceptJson()
                ->timeout(20)
                ->post($this->apiUrl, [
                    'from' => $this->fromNumber,
                    'to' => $to,
                    'type' => 'text',
                    'text' => ['body' => $message],
                ]);

            if ($response->successful()) {
                return [
                    'ok' => true,
                    'provider_id' => (string) ($response->json('messages.0.id') ?? $response->json('id') ?? ''),
                    'error' => null,
                ];
            }

            return [
                'ok' => false,
                'provider_id' => null,
                'error' => 'HTTP '.$response->status().': '.$response->body(),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'provider_id' => null, 'error' => $e->getMessage()];
        }
    }
}
