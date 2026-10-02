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

    /** @deprecated Prefer ConsoleSecondFactorService::authenticatorRequiredRoles() */
    public function privilegedRequireAuthenticator(): bool
    {
        return $this->bool('privileged_require_authenticator', true);
    }

    public function securityQuestionsEnrollCount(): int
    {
        $stored = Setting::get('auth_portal.security_questions_enroll_count');
        if ($stored !== null && $stored !== '') {
            return max(2, min(5, (int) $stored));
        }

        return max(2, min(5, (int) config('pin_recovery.questions_to_ask', 3)));
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

    /** @return array<string, mixed> */
    public function forForm(): array
    {
        $second = app(ConsoleSecondFactorService::class);

        return [
            'require_2fa_admin'        => $this->require2faAdmin(),
            'require_2fa_staff'        => $this->require2faStaff(),
            'require_2fa_partner'      => $this->require2faPartner(),
            'staff_allow_authenticator' => $this->staffAllowAuthenticator(),
            'staff_allow_security_questions' => $this->staffAllowSecurityQuestions(),
            'authenticator_required_roles' => $second->authenticatorRequiredRoles(),
            'security_questions_enroll_count' => $this->securityQuestionsEnrollCount(),
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

        if (is_bool($stored)) {
            return $stored;
        }

        if (is_string($stored)) {
            $normalized = strtolower(trim($stored));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off', ''], true)) {
                return false;
            }
        }

        return (bool) $stored;
    }
}
