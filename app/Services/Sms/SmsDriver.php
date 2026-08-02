<?php

namespace App\Services\Sms;

interface SmsDriver
{
    /**
     * Send a single SMS. Returns ['ok'=>bool, 'provider_id'=>?string, 'error'=>?string].
     *
     * @return array{ok:bool, provider_id:?string, error:?string}
     */
    public function send(string $to, string $message): array;

    /**
     * Lightweight connectivity / credential check.
     *
     * @return array{ok:bool, message:string, provider:?string}
     */
    public function healthCheck(): array;
}
