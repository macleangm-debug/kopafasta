<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AffiliateDeviceFingerprintService
{
    public function fromRequest(Request $request): ?string
    {
        return $this->generate(
            (string) $request->userAgent(),
            $request->ip(),
            $request->header('Accept-Language'),
        );
    }

    public function fromAttributes(array $attributes): ?string
    {
        return $this->generate(
            (string) ($attributes['user_agent'] ?? ''),
            (string) ($attributes['ip_address'] ?? ''),
            null,
        );
    }

    public function generate(?string $userAgent, ?string $ipAddress, ?string $acceptLanguage = null): ?string
    {
        if (blank($userAgent) && blank($ipAddress)) {
            return null;
        }

        $ipPrefix = $this->ipPrefix($ipAddress);
        $payload = Str::lower(trim($userAgent ?? '')).'|'.$ipPrefix.'|'.Str::lower(trim($acceptLanguage ?? ''));

        return hash('sha256', $payload);
    }

    protected function ipPrefix(?string $ip): string
    {
        if (blank($ip)) {
            return '';
        }

        if (str_contains($ip, ':')) {
            $parts = explode(':', $ip);

            return implode(':', array_slice($parts, 0, 4));
        }

        $parts = explode('.', $ip);

        return count($parts) >= 3
            ? implode('.', array_slice($parts, 0, 3))
            : $ip;
    }
}
