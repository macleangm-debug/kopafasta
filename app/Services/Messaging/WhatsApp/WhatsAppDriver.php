<?php

namespace App\Services\Messaging\WhatsApp;

interface WhatsAppDriver
{
    /**
     * @return array{ok: bool, provider_id?: string|null, error?: string|null}
     */
    public function send(string $to, string $message): array;
}
