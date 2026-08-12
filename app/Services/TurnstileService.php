<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class TurnstileService
{
    public function enabled(): bool
    {
        $site = trim((string) (Setting::get('security.turnstile_site_key') ?? config('security.turnstile_site_key', '')));
        $secret = trim((string) (Setting::get('security.turnstile_secret_key') ?? config('security.turnstile_secret_key', '')));

        return $site !== '' && $secret !== '';
    }

    public function siteKey(): string
    {
        return trim((string) (Setting::get('security.turnstile_site_key') ?? config('security.turnstile_site_key', '')));
    }

    public function secretKey(): string
    {
        return trim((string) (Setting::get('security.turnstile_secret_key') ?? config('security.turnstile_secret_key', '')));
    }

    public function verify(?string $token, ?string $ip = null): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        // Local/dev hosts often cannot complete production Turnstile challenges.
        if (app()->environment(['local', 'testing'])) {
            return true;
        }

        $token = trim((string) $token);
        if ($token === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(8)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array_filter([
                    'secret' => $this->secretKey(),
                    'response' => $token,
                    'remoteip' => $ip,
                ]));
        } catch (ConnectionException) {
            // Fail closed when Turnstile is configured but unreachable.
            return false;
        }

        return (bool) ($response->json('success') ?? false);
    }
}
