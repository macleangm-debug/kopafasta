<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;

class AuthPortalSettingsService
{
    public function require2faAdmin(): bool
    {
        return $this->bool('require_2fa_admin');
    }

    public function require2faStaff(): bool
    {
        return $this->bool('require_2fa_staff');
    }

    public function require2faPartner(): bool
    {
        return $this->bool('require_2fa_partner');
    }

    public function staffAllowAuthenticator(): bool
    {
        return $this->bool('staff_allow_authenticator', true);
    }

    public function staffAllowSecurityQuestions(): bool
    {
        return $this->bool('staff_allow_security_questions', true);
    }

    public function privilegedRequireAuthenticator(): bool
    {
        return $this->bool('privileged_require_authenticator', true);
    }

    public function isRequired(string $context): bool
    {
        return match ($context) {
            'admin'   => $this->require2faAdmin(),
            'staff'   => $this->require2faStaff(),
            'partner' => $this->require2faPartner(),
            default   => false,
        };
    }

    public function twoFactorContextForUser(User $user): ?string
    {
        $roles = app(RoleService::class);

        if ($user->role === 'vendor') {
            return 'partner';
        }

        if ($roles->isStaff($user->role)) {
            return $roles->hasConsoleAccess($user) ? 'admin' : 'staff';
        }

        if ($roles->hasConsoleAccess($user)) {
            return 'admin';
        }

        return null;
    }

    public function isRequiredForUser(User $user): bool
    {
        $context = $this->twoFactorContextForUser($user);

        return $context !== null && $this->isRequired($context);
    }

    public function twoFactorSessionHours(): int
    {
        $stored = Setting::get('auth_portal.two_factor_session_hours');

        if ($stored !== null && $stored !== '') {
            return max(1, (int) $stored);
        }

        return max(1, (int) config('auth_portal.two_factor_session_hours', 12));
    }

    public function pinRecoverySessionSeconds(): int
    {
        return app(PinRecoveryChallengeService::class)->sessionTtlSeconds();
    }

    /** @return array<string, bool|int> */
    public function forForm(): array
    {
        return [
            'require_2fa_admin'        => $this->require2faAdmin(),
            'require_2fa_staff'        => $this->require2faStaff(),
            'require_2fa_partner'      => $this->require2faPartner(),
            'staff_allow_authenticator' => $this->staffAllowAuthenticator(),
            'staff_allow_security_questions' => $this->staffAllowSecurityQuestions(),
            'privileged_require_authenticator' => $this->privilegedRequireAuthenticator(),
            'two_factor_session_hours' => $this->twoFactorSessionHours(),
            'pin_recovery_session_seconds' => $this->pinRecoverySessionSeconds(),
        ];
    }

    protected function bool(string $key, bool $default = false): bool
    {
        $stored = Setting::get('auth_portal.'.$key);

        if ($stored === null) {
            return (bool) config('auth_portal.'.$key, $default);
        }

        return (bool) $stored;
    }
}
