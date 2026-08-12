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

if (! function_exists('support_phones')) {
    /**
     * Up to 3 public hotline / contact numbers from company settings.
     *
     * @return list<string>
     */
    function support_phones(): array
    {
        $phones = [];
        foreach (['company.phone', 'company.phone_2', 'company.phone_3'] as $key) {
            $value = class_exists(\App\Models\Setting::class) ? \App\Models\Setting::get($key) : null;
            if (filled($value)) {
                $phones[] = (string) $value;
            }
        }

        if ($phones === []) {
            $fallback = support_contact('phone');
            if (filled($fallback)) {
                $phones[] = $fallback;
            }
        }

        return array_values(array_unique($phones));
    }
}

if (! function_exists('support_emails')) {
    /**
     * Up to 2 public support emails from company settings.
     *
     * @return list<string>
     */
    function support_emails(): array
    {
        $emails = [];
        foreach (['company.email', 'company.support_email'] as $key) {
            $value = class_exists(\App\Models\Setting::class) ? \App\Models\Setting::get($key) : null;
            if (filled($value)) {
                $emails[] = (string) $value;
            }
        }

        if ($emails === []) {
            $fallback = support_contact('email');
            if (filled($fallback)) {
                $emails[] = $fallback;
            }
        }

        return array_values(array_unique($emails));
    }
}

if (! function_exists('brand_name')) {
    function brand_name(): string
    {
        return (string) brand('app_name', 'kopafasta');
    }
}

if (! function_exists('brand_legal_name')) {
    /** Licensed institution name for BoT OTP / transactional SMS and disclosures. */
    function brand_legal_name(): string
    {
        return (string) brand('legal_name', brand_name());
    }
}

if (! function_exists('brand_title')) {
    function brand_title(string $page): string
    {
        return $page.' — '.brand_name();
    }
}
