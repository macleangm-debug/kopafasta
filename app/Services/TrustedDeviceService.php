<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

class TrustedDeviceService
{
    public function cookieName(): string
    {
        return (string) config('auth_portal.trusted_device_cookie', 'kopafasta_trusted_device');
    }

    public function ttlDays(): int
    {
        return (int) config('auth_portal.trusted_device_days', 30);
    }

    public function extractToken(Request $request): ?string
    {
        $token = $request->cookie($this->cookieName());

        return is_string($token) && $token !== '' ? $token : null;
    }

    public function find(User $user, ?string $plainToken): ?TrustedDevice
    {
        if (! $plainToken) {
            return null;
        }

        return TrustedDevice::query()
            ->where('user_id', $user->id)
            ->where('token_hash', hash('sha256', $plainToken))
            ->where('expires_at', '>', now())
            ->first();
    }

    public function touch(TrustedDevice $device): void
    {
        $device->forceFill(['last_used_at' => now()])->save();
    }

    public function create(User $user, Request $request): string
    {
        $plain = Str::random(64);

        TrustedDevice::create([
            'user_id'      => $user->id,
            'token_hash'   => hash('sha256', $plain),
            'name'         => $this->deviceName($request),
            'ip_address'   => $request->ip(),
            'user_agent'   => substr((string) $request->userAgent(), 0, 1000),
            'last_used_at' => now(),
            'expires_at'   => now()->addDays($this->ttlDays()),
        ]);

        try {
            AuditLog::create([
                'user_id'        => $user->id,
                'event'          => 'auth.device_trusted',
                'auditable_type' => User::class,
                'auditable_id'   => $user->id,
                'old_values'     => null,
                'new_values'     => json_encode(['channel' => 'web']),
                'ip_address'     => $request->ip(),
                'user_agent'     => substr((string) $request->userAgent(), 0, 1000),
            ]);
        } catch (\Throwable) {
        }

        return $plain;
    }

    public function makeCookie(string $plainToken): Cookie
    {
        return cookie(
            $this->cookieName(),
            $plainToken,
            $this->ttlDays() * 24 * 60,
            '/',
            null,
            request()->isSecure(),
            true,
            false,
            Cookie::SAMESITE_LAX
        );
    }

    public function forgetCookie(): Cookie
    {
        return cookie()->forget($this->cookieName());
    }

    public function deviceName(Request $request): string
    {
        $ua = (string) $request->userAgent();

        if (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) {
            return 'Apple device';
        }
        if (str_contains($ua, 'Android')) {
            return 'Android device';
        }
        if (str_contains($ua, 'Windows')) {
            return 'Windows device';
        }
        if (str_contains($ua, 'Mac')) {
            return 'Mac device';
        }

        return 'Web browser';
    }
}
