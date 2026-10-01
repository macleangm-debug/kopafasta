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

    /**
     * Admin-initiated password reset. Does not reveal the previous password.
     * Returns the plaintext temporary password for one-time display to the Admin.
     */
    public function resetPassword(User $actor, User $target, ?string $temporaryPassword = null, ?Request $request = null): array
    {
        $temporaryPassword = $temporaryPassword ?: \Illuminate\Support\Str::password(12);
        $target->forceFill([
            // Plaintext — User `password` cast hashes once (never pre-hash here).
            'password' => $temporaryPassword,
            'password_changed_at' => null,
        ])->save();

        AuditLog::create([
            'user_id'        => $actor->id,
            'event'          => 'admin.user_password_reset',
            'auditable_type' => User::class,
            'auditable_id'   => $target->id,
            'old_values'     => null,
            'new_values'     => json_encode(['reset_by' => $actor->id]),
            'ip_address'     => $request?->ip(),
            'user_agent'     => substr((string) ($request?->userAgent() ?? ''), 0, 1000),
        ]);

        return [
            'user' => $target->fresh(),
            'temporary_password' => $temporaryPassword,
        ];
    }

    /**
     * Issue a single-use expiring password setup/reset link (Laravel password broker).
     * When the user has no real email, returns a copyable URL for Admin to share out-of-band.
     *
     * @return array{url: string, expires_at: string, emailed: bool}
     */
    public function issuePasswordSetupLink(User $actor, User $target, ?Request $request = null): array
    {
        $brokerKey = $this->passwordBrokerKey($target);
        $plainToken = \Illuminate\Support\Str::random(64);
        $table = config('auth.passwords.users.table', 'password_reset_tokens');
        \Illuminate\Support\Facades\DB::table($table)->updateOrInsert(
            ['email' => $brokerKey],
            [
                'token' => \Illuminate\Support\Facades\Hash::make($plainToken),
                'created_at' => now(),
            ]
        );

        $expiresAt = now()->addMinutes((int) config('auth.passwords.users.expire', 60));
        $url = route('staff.password-setup', [
            'token' => $plainToken,
            'uid' => $target->id,
            'email' => $brokerKey,
        ]);

        $emailed = false;
        $email = trim((string) $target->email);
        if ($email !== ''
            && ! str_contains(strtolower($email), '@kopafasta.local')
            && ! str_contains(strtolower($email), '@partners.kopafasta.local')) {
            try {
                // Send OUR setup URL — do not call Password::broker()->sendResetLink
                // (that overwrites this token with Laravel's reset URL).
                \Illuminate\Support\Facades\Mail::raw(
                    "Set your Kopafasta staff password using this single-use link (expires soon):\n\n{$url}\n",
                    function ($message) use ($email, $target) {
                        $message->to($email, (string) $target->name)
                            ->subject('Kopafasta — set your password');
                    }
                );
                $emailed = true;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        AuditLog::create([
            'user_id'        => $actor->id,
            'event'          => 'admin.user_password_setup_link',
            'auditable_type' => User::class,
            'auditable_id'   => $target->id,
            'old_values'     => null,
            'new_values'     => json_encode([
                'issued_by' => $actor->id,
                'emailed' => $emailed,
                'expires_at' => $expiresAt->toIso8601String(),
            ]),
            'ip_address'     => $request?->ip(),
            'user_agent'     => substr((string) ($request?->userAgent() ?? ''), 0, 1000),
        ]);

        return [
            'url' => $url,
            'expires_at' => $expiresAt->toIso8601String(),
            'emailed' => $emailed,
        ];
    }

    /** Broker table key — real email when present, otherwise uid:{id} (not a mailbox). */
    public function passwordBrokerKey(User $user): string
    {
        $email = trim((string) $user->email);
        if ($email !== ''
            && ! str_contains(strtolower($email), '@kopafasta.local')
            && ! str_contains(strtolower($email), '@partners.kopafasta.local')) {
            return $email;
        }

        return 'uid:'.$user->id;
    }

    public function isLocked(User $user): bool
    {
        return $user->locked_until && $user->locked_until->isFuture();
    }
}
