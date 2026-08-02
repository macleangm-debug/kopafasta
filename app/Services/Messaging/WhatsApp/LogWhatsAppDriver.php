<?php

namespace App\Services\Messaging\WhatsApp;

use Illuminate\Support\Facades\Log;

class LogWhatsAppDriver implements WhatsAppDriver
{
    public function send(string $to, string $message): array
    {
        Log::info('[whatsapp:log] Would send WhatsApp message', [
            'to' => $to,
            'message' => $message,
        ]);

        return ['ok' => true, 'provider_id' => 'log-'.uniqid(), 'error' => null];
    }
}
