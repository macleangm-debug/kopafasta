<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\IpRuleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AnomalyGuard
{
    public const IP_BLOCK_THRESHOLD = 20;
    public const IP_BLOCK_WINDOW_MINUTES = 60;
    public const IP_BLOCK_DURATION_MINUTES = 60;

    public const USER_LOCK_THRESHOLD = 10;
    public const USER_LOCK_WINDOW_MINUTES = 60;
    public const USER_LOCK_DURATION_MINUTES = 60;

    public function isIpBlocked(?string $ip): bool
    {
        if (! $ip) {
            return false;
        }

        return Cache::has($this->ipKey($ip));
    }

    public function ipBlockSecondsRemaining(?string $ip): int
    {
        if (! $ip) {
            return 0;
        }

        $expires = Cache::get($this->ipKey($ip).':expires');
        if (! $expires) {
            return 0;
        }

        return max(0, (int) ($expires - time()));
    }

    /**
     * Inspect recent failed logins from this IP; if threshold crossed, block the IP.
     * Returns true if a new block was just installed.
     */
    public function evaluateIp(Request $request): bool
    {
        $ip = $request->ip();
        if (! $ip || $this->isIpBlocked($ip)) {
            return false;
        }

        if (app(IpRuleService::class)->isAllowed($ip)) {
            return false;
        }

        $count = AuditLog::where('event', 'auth.failed_login')
            ->where('ip_address', $ip)
            ->where('created_at', '>=', now()->subMinutes(self::IP_BLOCK_WINDOW_MINUTES))
            ->count();

        if ($count < self::IP_BLOCK_THRESHOLD) {
            return false;
        }

        $expiresAt = now()->addMinutes(self::IP_BLOCK_DURATION_MINUTES);
        Cache::put($this->ipKey($ip), 1, $expiresAt);
        Cache::put($this->ipKey($ip).':expires', $expiresAt->timestamp, $expiresAt);

        AuditLog::create([
            'user_id' => null,
            'event' => 'auth.ip_blocked',
            'auditable_type' => null,
            'auditable_id' => null,
            'old_values' => null,
            'new_values' => json_encode([
                'ip_address' => $ip,
                'failed_count' => $count,
                'blocked_until' => $expiresAt->toIso8601String(),
            ]),
            'ip_address' => $ip,
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        return true;
    }

    /**
     * Inspect recent failed logins for this user; if threshold crossed, lock the account.
     * Returns true if a new lock was installed.
     */
    public function evaluateUser(User $user, Request $request): bool
    {
        if ($user->locked_until && $user->locked_until->isFuture()) {
            return false;
        }

        $count = AuditLog::where('user_id', $user->id)
            ->where('event', 'auth.failed_login')
            ->where('created_at', '>=', now()->subMinutes(self::USER_LOCK_WINDOW_MINUTES))
            ->count();

        if ($count < self::USER_LOCK_THRESHOLD) {
            return false;
        }

        $lockUntil = now()->addMinutes(self::USER_LOCK_DURATION_MINUTES);
        $user->forceFill(['locked_until' => $lockUntil])->save();

        AuditLog::create([
            'user_id' => $user->id,
            'event' => 'auth.auto_locked',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'old_values' => null,
            'new_values' => json_encode([
                'email' => $user->email,
                'failed_count' => $count,
                'locked_until' => $lockUntil->toIso8601String(),
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        return true;
    }

    private function ipKey(string $ip): string
    {
        return 'sec:ip_blocked:'.$ip;
    }
}
