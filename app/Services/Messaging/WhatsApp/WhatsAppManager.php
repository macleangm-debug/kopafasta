<?php

namespace App\Services\Messaging\WhatsApp;

use App\Services\Messaging\TransactionalMessagingService;

class WhatsAppManager
{
    public function __construct(
        protected TransactionalMessagingService $messaging,
    ) {}

    public function driver(): WhatsAppDriver
    {
        if ($this->messaging->forceLogDriver() || ! $this->messaging->channelEnabled('whatsapp')) {
            return new LogWhatsAppDriver;
        }

        $cfg = $this->messaging->whatsappConfig();
        $provider = strtolower((string) ($cfg['provider'] ?? 'log'));

        if (in_array($provider, ['http', 'meta', 'cloud'], true)) {
            return new HttpWhatsAppDriver(
                apiUrl: (string) ($cfg['api_url'] ?? ''),
                apiToken: (string) ($cfg['api_token'] ?? ''),
                fromNumber: (string) ($cfg['from_number'] ?? ''),
            );
        }

        return new LogWhatsAppDriver;
    }
}
