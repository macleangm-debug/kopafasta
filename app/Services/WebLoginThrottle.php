<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class WebLoginThrottle
{
    public function maxAttempts(): int
    {
        return (int) config('auth_portal.max_login_attempts', 5);
    }

    public function decaySeconds(): int
    {
        return (int) config('auth_portal.lockout_minutes', 15) * 60;
    }

    public function key(string $identifier, ?string $ip): string
    {
        $normalized = strtolower(trim($identifier));

        return 'web_login:'.$normalized.'|'.($ip ?? 'unknown');
    }

    public function tooManyAttempts(string $identifier, Request $request): bool
    {
        return RateLimiter::tooManyAttempts(
            $this->key($identifier, $request->ip()),
            $this->maxAttempts()
        );
    }

    public function availableIn(string $identifier, Request $request): int
    {
        return RateLimiter::availableIn($this->key($identifier, $request->ip()));
    }

    public function remainingAttempts(string $identifier, Request $request): int
    {
        return max(0, $this->maxAttempts() - RateLimiter::attempts($this->key($identifier, $request->ip())));
    }

    public function hit(string $identifier, Request $request): void
    {
        RateLimiter::hit($this->key($identifier, $request->ip()), $this->decaySeconds());
    }

    public function clear(string $identifier, Request $request): void
    {
        RateLimiter::clear($this->key($identifier, $request->ip()));
    }

    public function lockUserIfNeeded(User $user, Request $request, string $identifier): void
    {
        if (RateLimiter::attempts($this->key($identifier, $request->ip())) < $this->maxAttempts()) {
            return;
        }

        if ($user->locked_until && $user->locked_until->isFuture()) {
            return;
        }

        $lockUntil = now()->addMinutes((int) config('auth_portal.lockout_minutes', 15));
        $user->forceFill(['locked_until' => $lockUntil])->save();

        $this->log($request, 'auth.web_locked', $identifier, [
            'locked_until' => $lockUntil->toIso8601String(),
        ], $user->id);
    }

    public function log(Request $request, string $event, string $identifier, array $extra = [], ?int $userId = null): void
    {
        try {
            AuditLog::create([
                'user_id'        => $userId,
                'event'          => $event,
                'auditable_type' => null,
                'auditable_id'   => null,
                'old_values'     => null,
                'new_values'     => json_encode(array_merge(['identifier' => $identifier], $extra)),
                'ip_address'     => $request->ip(),
                'user_agent'     => substr((string) $request->userAgent(), 0, 1000),
            ]);
        } catch (\Throwable) {
            // Never block auth on audit failure.
        }
    }
}
