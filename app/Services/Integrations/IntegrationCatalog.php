<?php

namespace App\Services\Integrations;

use App\Models\Setting;
use Illuminate\Support\Str;

class IntegrationCatalog
{
    /** @return array<string, array{label: string, description: string}> */
    public function categories(): array
    {
        return config('integrations.categories', []);
    }

    /** @return array<string, string> */
    public function channelOptions(): array
    {
        return config('integrations.channel_options', [
            'mobile_money' => 'Mobile money',
            'bank' => 'Bank transfer',
        ]);
    }

    /** @return array<string, array<string, mixed>> */
    public function partners(): array
    {
        $builtin = config('integrations.partners', []);
        $custom = Setting::get('integrations.custom_partners');
        if (! is_array($custom)) {
            $custom = [];
        }

        $merged = $builtin;
        foreach ($custom as $key => $partner) {
            if (! is_array($partner) || ! is_string($key) || $key === '') {
                continue;
            }
            $merged[$key] = array_merge([
                'builtin' => false,
                'status' => 'available',
                'channels' => [],
            ], $partner, ['builtin' => false]);
        }

        // Channel overrides saved by admin (which rails this partner serves).
        $channelOverrides = Setting::get('integrations.partner_channels');
        if (is_array($channelOverrides)) {
            foreach ($channelOverrides as $key => $channels) {
                if (! isset($merged[$key]) || ! is_array($channels)) {
                    continue;
                }
                $merged[$key]['channels'] = array_values(array_intersect(
                    array_keys($this->channelOptions()),
                    $channels
                ));
            }
        }

        return $merged;
    }

    public function partner(string $key): ?array
    {
        $partner = $this->partners()[$key] ?? null;

        return is_array($partner) ? array_merge(['key' => $key], $partner) : null;
    }

    /**
     * @return array<string, array{meta: array, partners: list<array<string, mixed>>}>
     */
    public function grouped(): array
    {
        $health = app(IntegrationHealthService::class);
        $out = [];

        foreach ($this->categories() as $category => $meta) {
            $partners = [];
            $primary = $this->primaryKey($category);

            foreach ($this->partners() as $key => $partner) {
                if (($partner['category'] ?? null) !== $category) {
                    continue;
                }

                $status = $health->lastStatus($key);
                $partners[] = array_merge($partner, [
                    'key' => $key,
                    'is_primary' => $primary === $key,
                    'health' => $status,
                    'guidance' => $status['guidance'] ?? [],
                ]);
            }

            $out[$category] = [
                'meta' => $meta,
                'partners' => $partners,
                'primary' => $primary,
            ];
        }

        return $out;
    }

    public function primaryKey(string $category): ?string
    {
        $stored = Setting::get("integrations.primary.{$category}");
        $all = $this->partners();
        if (is_string($stored) && $stored !== '' && isset($all[$stored])) {
            return $stored;
        }

        foreach ($all as $key => $partner) {
            if (($partner['category'] ?? null) === $category && ($partner['status'] ?? '') === 'available') {
                return $key;
            }
        }

        return null;
    }

    public function setPrimary(string $category, string $partnerKey): void
    {
        $partner = $this->partner($partnerKey);
        if (! $partner || ($partner['category'] ?? null) !== $category) {
            throw new \InvalidArgumentException("Partner {$partnerKey} is not in category {$category}.");
        }

        Setting::set("integrations.primary.{$category}", $partnerKey);
    }

    /**
     * @param  list<string>  $channels
     */
    public function setPartnerChannels(string $partnerKey, array $channels): void
    {
        if (! $this->partner($partnerKey)) {
            throw new \InvalidArgumentException("Unknown partner {$partnerKey}.");
        }

        $allowed = array_keys($this->channelOptions());
        $channels = array_values(array_intersect($allowed, $channels));
        if ($channels === []) {
            throw new \InvalidArgumentException('Select at least one channel (mobile money and/or bank).');
        }

        $all = Setting::get('integrations.partner_channels');
        if (! is_array($all)) {
            $all = [];
        }
        $all[$partnerKey] = $channels;
        Setting::set('integrations.partner_channels', $all);
    }

    /**
     * @param  array{label: string, category: string, description?: string, channels?: list<string>, docs_url?: string}  $data
     */
    public function addCustomPartner(array $data): string
    {
        $label = trim((string) ($data['label'] ?? ''));
        $category = (string) ($data['category'] ?? '');
        if ($label === '' || ! isset($this->categories()[$category])) {
            throw new \InvalidArgumentException('Partner name and category are required.');
        }

        $key = Str::slug($label, '_');
        if ($key === '') {
            $key = 'partner_'.Str::lower(Str::random(6));
        }
        if (isset($this->partners()[$key])) {
            $key .= '_'.Str::lower(Str::random(4));
        }

        $channels = array_values(array_intersect(
            array_keys($this->channelOptions()),
            $data['channels'] ?? []
        ));
        if ($category === 'payment' && $channels === []) {
            $channels = ['mobile_money'];
        }

        $custom = Setting::get('integrations.custom_partners');
        if (! is_array($custom)) {
            $custom = [];
        }

        $custom[$key] = [
            'label' => $label,
            'category' => $category,
            'description' => (string) ($data['description'] ?? ''),
            'docs_url' => (string) ($data['docs_url'] ?? ''),
            'settings_route' => null,
            'health_route' => null,
            'status' => 'available',
            'channels' => $channels,
            'builtin' => false,
        ];

        Setting::set('integrations.custom_partners', $custom);

        return $key;
    }

    /** Partners with an automated health probe (excludes stubs like Selcom until wired). */
    private const HEALTH_PROBE_KEYS = ['payin', 'unitxt', 'email_smtp', 'crb'];

    /** @return list<array<string, mixed>> */
    public function availableForHealthCheck(): array
    {
        $list = [];
        foreach ($this->partners() as $key => $partner) {
            if (($partner['status'] ?? '') !== 'available') {
                continue;
            }
            if (! in_array($key, self::HEALTH_PROBE_KEYS, true)) {
                continue;
            }
            $list[] = array_merge($partner, ['key' => $key]);
        }

        return $list;
    }
}
