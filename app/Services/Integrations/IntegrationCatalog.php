<?php

namespace App\Services\Integrations;

use App\Models\Setting;

class IntegrationCatalog
{
    /** @return array<string, array{label: string, description: string}> */
    public function categories(): array
    {
        return config('integrations.categories', []);
    }

    /** @return array<string, array<string, mixed>> */
    public function partners(): array
    {
        return config('integrations.partners', []);
    }

    public function partner(string $key): ?array
    {
        $partner = $this->partners()[$key] ?? null;

        return is_array($partner) ? array_merge(['key' => $key], $partner) : null;
    }

    /**
     * Partners grouped by category, with primary + last health overlay.
     *
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

                $partners[] = array_merge($partner, [
                    'key' => $key,
                    'is_primary' => $primary === $key,
                    'health' => $health->lastStatus($key),
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
        if (is_string($stored) && $stored !== '' && isset($this->partners()[$stored])) {
            return $stored;
        }

        foreach ($this->partners() as $key => $partner) {
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

    /** @return list<array<string, mixed>> */
    public function availableForHealthCheck(): array
    {
        $list = [];
        foreach ($this->partners() as $key => $partner) {
            if (($partner['status'] ?? '') !== 'available') {
                continue;
            }
            $list[] = array_merge($partner, ['key' => $key]);
        }

        return $list;
    }
}
