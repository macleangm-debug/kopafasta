<?php

if (! function_exists('brand')) {
    function brand(string $key, mixed $default = null): mixed
    {
        return config("branding.{$key}", $default);
    }
}

if (! function_exists('support_contact')) {
    /**
     * Public support contacts from admin company settings, falling back to branding config.
     *
     * @param  'phone'|'email'|'whatsapp'  $key
     */
    function support_contact(string $key): string
    {
        $map = [
            'phone' => ['company.phone', 'company.support_phone', 'support_phone'],
            'email' => ['company.email', 'company.support_email', 'support_email'],
            'whatsapp' => ['company.whatsapp', 'company.support_whatsapp', 'support_whatsapp'],
        ];

        $keys = $map[$key] ?? [];
        foreach ($keys as $settingKey) {
            if (str_starts_with($settingKey, 'company.') && class_exists(\App\Models\Setting::class)) {
                $value = \App\Models\Setting::get($settingKey);
                if (filled($value)) {
                    return (string) $value;
                }
            } else {
                $value = brand($settingKey);
                if (filled($value)) {
                    return (string) $value;
                }
            }
        }

        return match ($key) {
            'email' => (string) brand('support_email', 'hello@kopafasta.com'),
            'whatsapp' => preg_replace('/\D+/', '', (string) brand('support_phone', '255700000000')) ?: '255700000000',
            default => (string) brand('support_phone', '+255 700 000 000'),
        };
    }
}

if (! function_exists('brand_name')) {
    function brand_name(): string
    {
        return (string) brand('app_name', 'kopafasta');
    }
}

if (! function_exists('brand_title')) {
    function brand_title(string $page): string
    {
        return $page.' — '.brand_name();
    }
}
