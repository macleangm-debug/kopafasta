<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class WebTwoFactorAuthService
{
    public function isRequired(string $context): bool
    {
        return app(AuthPortalSettingsService::class)->isRequired($context);
    }

    public function isEnabled(User $user): bool
    {
        return filled($user->two_factor_secret) && filled($user->two_factor_confirmed_at);
    }

    public function mustEnroll(User $user, string $context): bool
    {
        return $this->isRequired($context) && ! $this->isEnabled($user);
    }

    public function sessionVerified(Request $request): bool
    {
        $verifiedAt = $request->session()->get('two_factor_verified_at');

        return filled($verifiedAt) && (now()->timestamp - (int) $verifiedAt) < $this->sessionTtlSeconds();
    }

    public function markSessionVerified(Request $request): void
    {
        $request->session()->put('two_factor_verified_at', now()->timestamp);
    }

    public function clearSessionVerification(Request $request): void
    {
        $request->session()->forget('two_factor_verified_at');
    }

    public function storePendingLogin(Request $request, User $user, string $guard, string $context, string $redirectTo, bool $remember = false): void
    {
        $request->session()->put('two_factor.pending', [
            'user_id' => $user->id,
            'guard' => $guard,
            'context' => $context,
            'redirect_to' => $redirectTo,
            'remember' => $remember,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function pendingLogin(Request $request): ?array
    {
        $pending = $request->session()->get('two_factor.pending');

        return is_array($pending) ? $pending : null;
    }

    public function clearPendingLogin(Request $request): void
    {
        $request->session()->forget('two_factor.pending');
    }

    public function trustedDeviceBypassesChallenge(User $user, Request $request): bool
    {
        $token = app(TrustedDeviceService::class)->extractToken($request);

        return $token && app(TrustedDeviceService::class)->find($user, $token) !== null;
    }

    public function needsChallenge(User $user, Request $request, string $context): bool
    {
        if (! $this->isRequired($context) || ! $this->isEnabled($user)) {
            return false;
        }

        // Every new login must pass 2FA. Trusted-device cookies must not skip the challenge.
        // Within an already-verified browser session, do not re-prompt until logout / TTL.
        if ($this->sessionVerified($request)) {
            return false;
        }

        return true;
    }

    public function verifyCode(User $user, string $code, Request $request): bool
    {
        $totp = app(TotpService::class);
        $normalized = strtolower(preg_replace('/\s+/', '', $code) ?? '');

        if ($totp->verify((string) $user->two_factor_secret, $code)) {
            return true;
        }

        $codes = $user->two_factor_recovery_codes ?? [];
        $remaining = array_values(array_filter($codes, fn ($stored) => strtolower((string) $stored) !== $normalized));

        if (count($remaining) !== count($codes)) {
            $user->forceFill(['two_factor_recovery_codes' => $remaining])->save();
            $this->audit($request, 'auth.2fa_recovery_used', $user, ['remaining_codes' => count($remaining)]);

            return true;
        }

        $this->audit($request, 'auth.2fa_failed', $user);

        return false;
    }

    public function beginEnrollment(User $user, Request $request): array
    {
        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();
        $codes = collect(range(1, 8))->map(fn () => Str::lower(Str::random(10)))->all();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->audit($request, 'auth.2fa_enabled', $user);

        return [
            'secret' => $secret,
            'provisioning_uri' => $totp->provisioningUri($secret, (string) $user->email, config('app.name', 'Kopafasta')),
            'recovery_codes' => $codes,
        ];
    }

    public function confirmEnrollment(User $user, string $code, Request $request): bool
    {
        if (! $user->two_factor_secret || ! app(TotpService::class)->verify((string) $user->two_factor_secret, $code)) {
            $this->audit($request, 'auth.2fa_confirm_failed', $user);

            return false;
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->audit($request, 'auth.2fa_confirmed', $user);

        return true;
    }

    /**
     * Replace recovery codes after verifying a current authenticator code.
     *
     * @return list<string>|null
     */
    public function regenerateRecoveryCodes(User $user, string $code, Request $request): ?array
    {
        if (! $this->isEnabled($user)) {
            return null;
        }

        if (! app(TotpService::class)->verify((string) $user->two_factor_secret, $code)) {
            $this->audit($request, 'auth.2fa_recovery_regen_failed', $user);

            return null;
        }

        $codes = collect(range(1, 8))->map(fn () => Str::lower(Str::random(10)))->all();
        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();
        $this->audit($request, 'auth.2fa_recovery_regenerated', $user, ['code_count' => count($codes)]);

        return $codes;
    }

    public function remainingRecoveryCodeCount(User $user): int
    {
        return count($user->two_factor_recovery_codes ?? []);
    }

    public function completePendingLogin(Request $request, bool $trustDevice = false): ?RedirectResponse
    {
        $pending = $this->pendingLogin($request);
        if (! $pending) {
            return null;
        }

        $user = User::find($pending['user_id'] ?? null);
        if (! $user) {
            $this->clearPendingLogin($request);

            return null;
        }

        $guard = (string) ($pending['guard'] ?? 'admin');
        Auth::guard($guard)->login($user, (bool) ($pending['remember'] ?? false));
        $request->session()->regenerate();
        $this->markSessionVerified($request);
        $this->clearPendingLogin($request);

        app(KopafastaLaunchService::class)->arm($request);

        // Intentionally ignore $trustDevice for 2FA — every login must use a code.

        if ($user->role === 'vendor') {
            app(PartnerWelcomeService::class)->sendIfFirstLogin($user);
        }

        return redirect()->to((string) ($pending['redirect_to'] ?? '/'));
    }

    /** @param  array<string, mixed>  $meta */
    protected function audit(Request $request, string $event, User $user, array $meta = []): void
    {
        try {
            AuditLog::create([
                'user_id' => $user->id,
                'event' => $event,
                'auditable_type' => User::class,
                'auditable_id' => $user->id,
                'old_values' => null,
                'new_values' => $meta === [] ? null : json_encode($meta),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            ]);
        } catch (\Throwable) {
        }
    }

    protected function sessionTtlSeconds(): int
    {
        return app(AuthPortalSettingsService::class)->twoFactorSessionHours() * 3600;
    }
}
