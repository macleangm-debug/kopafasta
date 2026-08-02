<?php

namespace App\Services\Messaging;

use App\Models\Setting;

class TransactionalMessagingService
{
    public const SETTING_ENABLED = 'messaging.enabled';

    public const SETTING_CHANNELS = 'messaging.channels';

    public const SETTING_EVENTS = 'messaging.events';

    public const SETTING_REMINDER_OFFSETS = 'messaging.reminder_offsets_days';

    public const SETTING_OVERDUE = 'messaging.overdue_reminders';

    public const SETTING_FORCE_LOG = 'messaging.force_log_driver';

    public const SETTING_WHATSAPP = 'messaging.whatsapp';

    /**
     * Seed defaults if missing (safe to call repeatedly).
     */
    public function ensureDefaults(): void
    {
        if (Setting::get(self::SETTING_ENABLED) === null) {
            Setting::set(self::SETTING_ENABLED, true);
        }

        if (! is_array(Setting::get(self::SETTING_CHANNELS))) {
            Setting::set(self::SETTING_CHANNELS, [
                'sms' => true,
                'email' => true,
                'in_app' => true,
                'whatsapp' => false,
                'push' => false,
            ]);
        }

        if (! is_array(Setting::get(self::SETTING_REMINDER_OFFSETS))) {
            Setting::set(self::SETTING_REMINDER_OFFSETS, [3, 1, 0]);
        }

        if (Setting::get(self::SETTING_OVERDUE) === null) {
            Setting::set(self::SETTING_OVERDUE, true);
        }

        if (Setting::get(self::SETTING_FORCE_LOG) === null) {
            Setting::set(self::SETTING_FORCE_LOG, false);
        }

        if (! is_array(Setting::get(self::SETTING_WHATSAPP))) {
            Setting::set(self::SETTING_WHATSAPP, [
                'provider' => 'log',
                'api_url' => '',
                'api_token' => '',
                'from_number' => '',
            ]);
        }

        $events = Setting::get(self::SETTING_EVENTS);
        if (! is_array($events)) {
            $events = [];
        }

        foreach (MessagingCatalog::events() as $event) {
            if (! isset($events[$event['code']])) {
                $events[$event['code']] = [
                    'enabled' => $event['default_enabled'],
                    'channels' => $event['default_channels'],
                ];
            }
        }

        Setting::set(self::SETTING_EVENTS, $events);
    }

    public function isGloballyEnabled(): bool
    {
        $value = Setting::get(self::SETTING_ENABLED, true);

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function forceLogDriver(): bool
    {
        return filter_var(Setting::get(self::SETTING_FORCE_LOG, false), FILTER_VALIDATE_BOOLEAN);
    }

    /** @return array<string, bool> */
    public function channelFlags(): array
    {
        $defaults = [
            'sms' => true,
            'email' => true,
            'in_app' => true,
            'whatsapp' => false,
            'push' => false,
        ];
        $stored = Setting::get(self::SETTING_CHANNELS, $defaults);

        return array_merge($defaults, is_array($stored) ? $stored : []);
    }

    public function channelEnabled(string $channel): bool
    {
        if (! $this->isGloballyEnabled()) {
            return false;
        }

        $flags = $this->channelFlags();

        return (bool) ($flags[$channel] ?? false);
    }

    /**
     * @return array{enabled: bool, channels: list<string>, critical: bool}|null
     */
    public function eventConfig(string $code): ?array
    {
        $catalog = MessagingCatalog::eventsByCode()[$code] ?? null;
        $events = Setting::get(self::SETTING_EVENTS, []);
        $row = is_array($events) ? ($events[$code] ?? null) : null;

        if (! $catalog && ! $row) {
            return null;
        }

        $enabled = $row['enabled'] ?? $catalog['default_enabled'] ?? true;
        $channels = $row['channels'] ?? $catalog['default_channels'] ?? ['sms'];

        return [
            'enabled' => (bool) $enabled,
            'channels' => array_values(array_filter((array) $channels)),
            'critical' => (bool) ($catalog['critical'] ?? false),
        ];
    }

    public function eventEnabled(string $code): bool
    {
        if (! $this->isGloballyEnabled()) {
            return false;
        }

        $config = $this->eventConfig($code);
        if ($config === null) {
            // Unknown codes still send (legacy templates) when messaging is on.
            return true;
        }

        return $config['enabled'];
    }

    /**
     * Channels allowed for this event after global + event toggles.
     *
     * @return list<string>
     */
    public function allowedChannelsFor(string $code): array
    {
        if (! $this->eventEnabled($code)) {
            return [];
        }

        $config = $this->eventConfig($code);
        $wanted = $config['channels'] ?? ['sms'];
        $flags = $this->channelFlags();

        return array_values(array_filter(
            $wanted,
            fn (string $ch) => (bool) ($flags[$ch] ?? false)
        ));
    }

    /** @return list<int> */
    public function reminderOffsetsDays(): array
    {
        $raw = Setting::get(self::SETTING_REMINDER_OFFSETS, [3, 1, 0]);
        if (is_string($raw)) {
            $raw = preg_split('/\s*,\s*/', $raw) ?: [];
        }

        return collect((array) $raw)
            ->map(fn ($d) => (int) $d)
            ->filter(fn ($d) => $d >= 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function overdueRemindersEnabled(): bool
    {
        return filter_var(Setting::get(self::SETTING_OVERDUE, true), FILTER_VALIDATE_BOOLEAN)
            && $this->eventEnabled('repayment_overdue');
    }

    /** @return array{provider: string, api_url: string, api_token: string, from_number: string} */
    public function whatsappConfig(): array
    {
        $defaults = [
            'provider' => 'log',
            'api_url' => '',
            'api_token' => '',
            'from_number' => '',
        ];
        $stored = Setting::get(self::SETTING_WHATSAPP, $defaults);

        return array_merge($defaults, is_array($stored) ? $stored : []);
    }

    /**
     * Persist admin form payload.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): void
    {
        Setting::set(self::SETTING_ENABLED, (bool) ($data['enabled'] ?? false));
        Setting::set(self::SETTING_FORCE_LOG, (bool) ($data['force_log_driver'] ?? false));
        Setting::set(self::SETTING_OVERDUE, (bool) ($data['overdue_reminders'] ?? false));

        $offsets = collect(explode(',', (string) ($data['reminder_offsets_days'] ?? '3,1,0')))
            ->map(fn ($d) => (int) trim($d))
            ->filter(fn ($d) => $d >= 0)
            ->unique()
            ->values()
            ->all();
        Setting::set(self::SETTING_REMINDER_OFFSETS, $offsets !== [] ? $offsets : [3, 1, 0]);

        $channels = [];
        foreach (array_keys(MessagingCatalog::CHANNELS) as $ch) {
            $channels[$ch] = (bool) ($data['channels'][$ch] ?? false);
        }
        Setting::set(self::SETTING_CHANNELS, $channels);

        $events = [];
        foreach (MessagingCatalog::events() as $event) {
            $code = $event['code'];
            $row = $data['events'][$code] ?? [];
            $eventChannels = array_values(array_filter(
                (array) ($row['channels'] ?? $event['default_channels']),
                fn ($ch) => is_string($ch) && isset(MessagingCatalog::CHANNELS[$ch])
            ));
            $events[$code] = [
                'enabled' => (bool) ($row['enabled'] ?? false),
                'channels' => $eventChannels !== [] ? $eventChannels : $event['default_channels'],
            ];
        }
        Setting::set(self::SETTING_EVENTS, $events);

        Setting::set(self::SETTING_WHATSAPP, [
            'provider' => (string) ($data['whatsapp']['provider'] ?? 'log'),
            'api_url' => (string) ($data['whatsapp']['api_url'] ?? ''),
            'api_token' => (string) ($data['whatsapp']['api_token'] ?? ''),
            'from_number' => (string) ($data['whatsapp']['from_number'] ?? ''),
        ]);
    }

    /** @return array<string, mixed> */
    public function formValues(): array
    {
        $this->ensureDefaults();

        return [
            'enabled' => $this->isGloballyEnabled(),
            'force_log_driver' => $this->forceLogDriver(),
            'overdue_reminders' => filter_var(Setting::get(self::SETTING_OVERDUE, true), FILTER_VALIDATE_BOOLEAN),
            'reminder_offsets_days' => implode(',', $this->reminderOffsetsDays()),
            'channels' => $this->channelFlags(),
            'events' => Setting::get(self::SETTING_EVENTS, []),
            'whatsapp' => $this->whatsappConfig(),
            'catalog' => MessagingCatalog::events(),
            'groups' => MessagingCatalog::GROUPS,
            'channel_labels' => MessagingCatalog::CHANNELS,
        ];
    }
}
