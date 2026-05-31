<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class UserAccountService
{
    public function lock(User $actor, User $target, int $minutes = 60, ?string $reason = null, ?Request $request = null): User
    {
        $minutes = max(1, min($minutes, 43200));
        $target->forceFill(['locked_until' => now()->addMinutes($minutes)])->save();

        AuditLog::create([
            'user_id'        => $actor->id,
            'event'          => 'admin.user_locked',
            'auditable_type' => User::class,
            'auditable_id'   => $target->id,
            'old_values'     => null,
            'new_values'     => json_encode([
                'locked_until' => $target->locked_until?->toIso8601String(),
                'reason'       => $reason,
            ]),
            'ip_address'     => $request?->ip(),
            'user_agent'     => substr((string) ($request?->userAgent() ?? ''), 0, 1000),
        ]);

        return $target->fresh();
    }

    public function unlock(User $actor, User $target, ?Request $request = null): User
    {
        $target->forceFill(['locked_until' => null])->save();

        AuditLog::create([
            'user_id'        => $actor->id,
            'event'          => 'admin.user_unlocked',
            'auditable_type' => User::class,
            'auditable_id'   => $target->id,
            'old_values'     => null,
            'new_values'     => json_encode(['user_id' => $target->id]),
            'ip_address'     => $request?->ip(),
            'user_agent'     => substr((string) ($request?->userAgent() ?? ''), 0, 1000),
        ]);

        return $target->fresh();
    }

    public function setActive(User $actor, User $target, bool $active, ?Request $request = null): User
    {
        $target->forceFill(['is_active' => $active])->save();

        AuditLog::create([
            'user_id'        => $actor->id,
            'event'          => $active ? 'admin.user_activated' : 'admin.user_deactivated',
            'auditable_type' => User::class,
            'auditable_id'   => $target->id,
            'old_values'     => null,
            'new_values'     => json_encode(['is_active' => $active]),
            'ip_address'     => $request?->ip(),
            'user_agent'     => substr((string) ($request?->userAgent() ?? ''), 0, 1000),
        ]);

        return $target->fresh();
    }

    public function isLocked(User $user): bool
    {
        return $user->locked_until && $user->locked_until->isFuture();
    }
}
