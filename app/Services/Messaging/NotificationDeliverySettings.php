<?php

namespace App\Services\Messaging;

use App\Models\Setting;

/**
 * Settings → Communications → Notifications.
 * Workspace owns the work; this only configures delivery behaviour.
 */
class NotificationDeliverySettings
{
    /** @return array{management: array<string, mixed>, operational: array<string, mixed>} */
    public function all(): array
    {
        $stored = Setting::get('notifications.delivery');
        if (! is_array($stored)) {
            $stored = [];
        }

        return [
            'management' => array_merge($this->managementDefaults(), is_array($stored['management'] ?? null) ? $stored['management'] : []),
            'operational' => array_merge($this->operationalDefaults(), is_array($stored['operational'] ?? null) ? $stored['operational'] : []),
        ];
    }

    public function managementEnabled(): bool
    {
        return (bool) ($this->all()['management']['enabled'] ?? true);
    }

    public function managementEventEnabled(string $event): bool
    {
        if (! $this->managementEnabled()) {
            return false;
        }
        $events = $this->all()['management']['events'] ?? [];

        return (bool) ($events[$event] ?? true);
    }

    public function managementChannelEnabled(string $channel): bool
    {
        if (! $this->managementEnabled()) {
            return false;
        }
        $channels = $this->all()['management']['channels'] ?? [];

        return (bool) ($channels[$channel] ?? ($channel === 'in_app'));
    }

    public function managementCadence(): string
    {
        return (string) ($this->all()['management']['cadence'] ?? 'immediate_summary');
    }

    public function operationalEnabled(): bool
    {
        return (bool) ($this->all()['operational']['enabled'] ?? true);
    }

    public function operationalEventEnabled(string $event): bool
    {
        if (! $this->operationalEnabled()) {
            return false;
        }
        $events = $this->all()['operational']['events'] ?? [];

        return (bool) ($events[$event] ?? true);
    }

    public function operationalChannelEnabled(string $channel): bool
    {
        if (! $this->operationalEnabled()) {
            return false;
        }
        $channels = $this->all()['operational']['channels'] ?? [];

        return (bool) ($channels[$channel] ?? ($channel === 'in_app'));
    }

    /** @return array<string, mixed> */
    protected function managementDefaults(): array
    {
        return [
            'enabled' => true,
            'cadence' => 'immediate_summary',
            'channels' => ['in_app' => true, 'email' => false, 'sms' => false],
            'events' => [
                'applications' => true,
                'collections' => true,
                'failed_payments' => true,
                'integration_failures' => true,
                'partner_exceptions' => true,
                'sla_breaches' => true,
            ],
        ];
    }

    /** @return array<string, mixed> */
    protected function operationalDefaults(): array
    {
        return [
            'enabled' => true,
            'channels' => ['in_app' => true, 'email' => false, 'sms' => false],
            'events' => [
                'screening' => true,
                'credit' => true,
                'finance' => true,
                'disbursement' => true,
                'recovery' => true,
                'partners' => true,
            ],
        ];
    }
}
