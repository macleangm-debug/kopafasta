<?php

namespace App\Services\Sms;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class SmsManager
{
    private ?SmsDriver $driver = null;

    public function driver(): SmsDriver
    {
        if ($this->driver) {
            return $this->driver;
        }

        $cfg = $this->settings();
        $provider = strtolower(trim((string) ($cfg['provider'] ?? '')));

        if (app(\App\Services\Messaging\TransactionalMessagingService::class)->forceLogDriver()) {
            $this->driver = new LogDriver;

            return $this->driver;
        }

        $this->driver = match ($provider) {
            'beem'            => new BeemDriver(
                apiKey:    (string) ($cfg['api_key'] ?? ''),
                secretKey: (string) ($cfg['api_secret'] ?? ''),
                senderId:  (string) ($cfg['sender_id'] ?? 'INFO'),
                endpoint:  (string) ($cfg['endpoint'] ?? 'https://apisms.beem.africa/v1/send'),
            ),
            'africastalking', 'at' => new AfricasTalkingDriver(
                username: (string) ($cfg['api_key'] ?? 'sandbox'),
                apiKey:   (string) ($cfg['api_secret'] ?? ''),
                senderId: (string) ($cfg['sender_id'] ?? ''),
                endpoint: (string) ($cfg['endpoint'] ?? 'https://api.africastalking.com/version1/messaging'),
            ),
            default => new LogDriver(),
        };

        return $this->driver;
    }

    /** Force a specific driver (mainly for tests). */
    public function setDriver(SmsDriver $driver): void
    {
        $this->driver = $driver;
    }

    /**
     * @return array{ok:bool, message:string, provider:?string}
     */
    public function healthCheck(): array
    {
        // Always rebuild so we pick up freshly saved credentials.
        $this->driver = null;
        Cache::forget('sms.settings.v1');

        return $this->driver()->healthCheck();
    }

    /**
     * Read sms_* settings from system_settings table, with 60s cache.
     *
     * @return array{provider:?string, sender_id:?string, api_key:?string, api_secret:?string, endpoint:?string}
     */
    private function settings(): array
    {
        return Cache::remember('sms.settings.v1', 60, function () {
            $rows = SystemSetting::query()
                ->whereIn('key', [
                    'gateway.sms_provider',
                    'gateway.sms_sender_id',
                    'gateway.sms_api_key',
                    'gateway.sms_api_secret',
                    'gateway.sms_endpoint',
                ])
                ->pluck('value', 'key')
                ->toArray();

            return [
                'provider'   => $rows['gateway.sms_provider']   ?? null,
                'sender_id'  => $rows['gateway.sms_sender_id']  ?? null,
                'api_key'    => $rows['gateway.sms_api_key']    ?? null,
                'api_secret' => $rows['gateway.sms_api_secret'] ?? null,
                'endpoint'   => $rows['gateway.sms_endpoint']   ?? null,
            ];
        });
    }
}
