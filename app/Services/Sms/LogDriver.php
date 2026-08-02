<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Fallback driver used in development or when no provider is configured.
 * Writes the message to laravel.log so devs can inspect what would be sent.
 */
class LogDriver implements SmsDriver
{
    public function send(string $to, string $message): array
    {
        Log::channel(config('logging.default'))->info('[SMS:log-driver] '.$to.' :: '.$message);
        return ['ok' => true, 'provider_id' => 'log-'.uniqid(), 'error' => null];
    }

    public function healthCheck(): array
    {
        return [
            'ok' => true,
            'message' => 'Log driver active — messages are written to the application log (no live SMS API).',
            'provider' => 'log',
        ];
    }
}
