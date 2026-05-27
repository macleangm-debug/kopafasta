<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Notifications\NewLoginNotification;
use App\Services\AnomalyGuard;
use App\Services\IpRuleService;
use App\Services\SecurityIntel;
use App\Services\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const DECAY_SECONDS = 900;
    private const RESET_TOKEN_TTL_MINUTES = 60;
    private const RESET_REQUEST_MAX = 5;
    private const RESET_REQUEST_DECAY = 900;
    private const TRUSTED_DEVICE_TTL_DAYS = 30;
    private const NEW_DEVICE_LOOKBACK_DAYS = 30;
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => 'customer',
            'password_changed_at' => now(),
        ]);

        $token = $this->issueToken($user, 'api-token', $request);

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $ipRules = app(IpRuleService::class);
        $ipRuleEval = $ipRules->evaluate($request->ip());
        if ($ipRuleEval['deny']) {
            $this->logAuditEvent($request, 'auth.ip_denied', $data['email'], [
                'matched_cidr' => $ipRuleEval['matched']['cidr'] ?? null,
                'reason' => $ipRuleEval['matched']['reason'] ?? null,
            ]);

            return response()->json(['message' => 'Access from this network is not permitted.'], 403);
        }

        $guard = app(AnomalyGuard::class);
        if (! $ipRuleEval['allow'] && $guard->isIpBlocked($request->ip())) {
            $retry = $guard->ipBlockSecondsRemaining($request->ip());
            $this->logAuditEvent($request, 'auth.ip_block_hit', $data['email'], [
                'retry_after' => $retry,
            ]);

            return response()->json([
                'message' => 'Too many failed attempts from this network. Try again later.',
                'retry_after' => $retry,
            ], 429);
        }

        $key = $this->throttleKey($data['email'], $request->ip());

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            $this->logAuditEvent($request, 'auth.login_locked', $data['email'], ['retry_after' => $seconds]);

            return response()->json([
                'message' => 'Too many login attempts. Try again later.',
                'retry_after' => $seconds,
            ], 429);
        }

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            $this->logAuditEvent($request, 'auth.failed_login', $data['email'], [
                'remaining_attempts' => max(0, self::MAX_ATTEMPTS - RateLimiter::attempts($key)),
            ], $user?->id);

            $guard->evaluateIp($request);
            if ($user) {
                $guard->evaluateUser($user, $request);
            }

            return response()->json(['message' => 'Invalid credentials'], 422);
        }

        if (! $user->is_active) {
            $this->logAuditEvent($request, 'auth.login_inactive', $data['email'], [], $user->id);

            return response()->json(['message' => 'Account is inactive'], 403);
        }

        if ($user->locked_until && $user->locked_until->isFuture()) {
            $this->logAuditEvent($request, 'auth.login_locked_account', $data['email'], [
                'locked_until' => $user->locked_until->toIso8601String(),
            ], $user->id);

            return response()->json([
                'message' => 'Account is locked',
                'locked_until' => $user->locked_until->toIso8601String(),
            ], 423);
        }

        if ($user->two_factor_confirmed_at && $user->two_factor_secret) {
            $trustedToken = $this->extractTrustedDeviceToken($request);
            $trustedRow = $trustedToken ? $this->findTrustedDevice($user, $trustedToken) : null;

            if ($trustedRow) {
                $trustedRow->forceFill(['last_used_at' => now()])->save();
                $this->logAuditEvent($request, 'auth.device_trust_used', $data['email'], [
                    'device_id' => $trustedRow->id,
                ], $user->id);
            } else {
                $code = (string) $request->input('two_factor_code', '');
                if ($code === '') {
                    $this->logAuditEvent($request, 'auth.2fa_required', $data['email'], [], $user->id);

                    return response()->json([
                        'message' => 'Two-factor authentication code required',
                        'requires_two_factor' => true,
                    ], 401);
                }

                if (! $this->verifyTwoFactor($user, $code, $request)) {
                    RateLimiter::hit($key, self::DECAY_SECONDS);
                    $this->logAuditEvent($request, 'auth.2fa_failed', $data['email'], [
                        'remaining_attempts' => max(0, self::MAX_ATTEMPTS - RateLimiter::attempts($key)),
                    ], $user->id);

                    return response()->json(['message' => 'Invalid two-factor code'], 422);
                }
            }
        }

        RateLimiter::clear($key);

        $token = $this->issueToken($user, 'api-token', $request);

        $this->logAuditEvent($request, 'auth.login_success', $data['email'], [], $user->id);

        $this->detectAndNotifyNewDevice($user, $request);

        $payload = [
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ];

        if ($request->boolean('trust_device') && $user->two_factor_confirmed_at) {
            $payload['trusted_device_token'] = $this->createTrustedDevice($user, $request);
        }

        return response()->json($payload);
    }

    private function throttleKey(string $email, ?string $ip): string
    {
        return 'login:'.strtolower($email).'|'.($ip ?? 'unknown');
    }

    private function issueToken(User $user, string $name, Request $request): string
    {
        $new = $user->createToken($name);
        $access = $new->accessToken;
        $access->forceFill([
            'created_ip' => $request->ip(),
            'created_user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ])->save();

        return $new->plainTextToken;
    }

    private function logAuditEvent(Request $request, string $event, string $email, array $extra = [], ?int $userId = null): void
    {
        try {
            $intel = app(SecurityIntel::class)->classify($request->ip());

            AuditLog::create([
                'user_id' => $userId,
                'event' => $event,
                'auditable_type' => null,
                'auditable_id' => null,
                'old_values' => null,
                'new_values' => json_encode(array_merge([
                    'email' => $email,
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'intel' => $intel,
                ], $extra)),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            ]);
        } catch (\Throwable $t) {
            // never block auth on audit failure
        }
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            $this->logAuditEvent($request, 'auth.change_password_failed', $user->email, [], $user->id);

            return response()->json(['message' => 'Current password is incorrect'], 422);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'password_changed_at' => now(),
        ])->save();

        // Revoke all tokens except the current one
        $current = $request->user()->currentAccessToken();
        $user->tokens()->where('id', '!=', $current?->id)->delete();

        $this->logAuditEvent($request, 'auth.password_changed', $user->email, [], $user->id);

        return response()->json(['message' => 'Password changed']);
    }

    public function tokens(Request $request)
    {
        $current = $request->user()->currentAccessToken();

        $tokens = $request->user()->tokens()->latest()->get()->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'last_used_at' => $t->last_used_at,
            'expires_at' => $t->expires_at,
            'created_at' => $t->created_at,
            'created_ip' => $t->created_ip ?? null,
            'created_user_agent' => $t->created_user_agent ?? null,
            'current' => $current && $t->id === $current->id,
        ]);

        return response()->json(['data' => $tokens]);
    }

    public function revokeToken(Request $request, int $id)
    {
        $user = $request->user();
        $token = $user->tokens()->where('id', $id)->first();

        if (! $token) {
            return response()->json(['message' => 'Token not found'], 404);
        }

        $current = $user->currentAccessToken();
        if ($current && $token->id === $current->id) {
            return response()->json(['message' => 'Cannot revoke the current token; use /auth/logout instead'], 422);
        }

        $token->delete();

        $this->logAuditEvent($request, 'auth.token_revoked', $user->email, [
            'token_id' => $id,
            'token_name' => $token->name,
        ], $user->id);

        return response()->json(['message' => 'Token revoked']);
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $key = 'pwreset:'.strtolower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, self::RESET_REQUEST_MAX)) {
            $this->logAuditEvent($request, 'auth.password_reset_throttled', $data['email']);

            return response()->json([
                'message' => 'If the email exists, a reset link was issued.',
            ]);
        }
        RateLimiter::hit($key, self::RESET_REQUEST_DECAY);

        $user = User::where('email', $data['email'])->first();

        $response = ['message' => 'If the email exists, a reset link was issued.'];

        if (! $user) {
            $this->logAuditEvent($request, 'auth.password_reset_requested_unknown', $data['email']);

            return response()->json($response);
        }

        $plainToken = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($plainToken), 'created_at' => now()]
        );

        $this->logAuditEvent($request, 'auth.password_reset_requested', $user->email, [], $user->id);

        // In production, dispatch a Notification; for now expose token only in non-production
        if (! app()->environment('production')) {
            $response['reset_token'] = $plainToken;
        }

        return response()->json($response);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()],
        ]);

        $row = DB::table('password_reset_tokens')->where('email', $data['email'])->first();

        if (! $row || ! Hash::check($data['token'], $row->token)) {
            $this->logAuditEvent($request, 'auth.password_reset_invalid', $data['email']);

            return response()->json(['message' => 'Invalid or expired reset token'], 422);
        }

        $createdAt = \Illuminate\Support\Carbon::parse($row->created_at);
        if ($createdAt->addMinutes(self::RESET_TOKEN_TTL_MINUTES)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $data['email'])->delete();
            $this->logAuditEvent($request, 'auth.password_reset_expired', $data['email']);

            return response()->json(['message' => 'Invalid or expired reset token'], 422);
        }

        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

            return response()->json(['message' => 'Invalid or expired reset token'], 422);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'password_changed_at' => now(),
            'locked_until' => null,
        ])->save();

        // Revoke all sessions
        $user->tokens()->delete();

        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();
        RateLimiter::clear('login:'.strtolower($user->email).'|'.$request->ip());
        RateLimiter::clear('pwreset:'.strtolower($user->email).'|'.$request->ip());

        $this->logAuditEvent($request, 'auth.password_reset', $user->email, [], $user->id);

        return response()->json(['message' => 'Password reset successful']);
    }

    public function enableTwoFactor(Request $request, TotpService $totp)
    {
        $user = $request->user();

        if ($user->two_factor_confirmed_at) {
            return response()->json(['message' => 'Two-factor is already enabled'], 422);
        }

        $secret = $totp->generateSecret();
        $codes = collect(range(1, 8))->map(fn () => Str::lower(Str::random(10)))->all();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->logAuditEvent($request, 'auth.2fa_enabled', $user->email, [], $user->id);

        return response()->json([
            'secret' => $secret,
            'provisioning_uri' => $totp->provisioningUri($secret, $user->email, config('app.name', 'Kopafasta')),
            'recovery_codes' => $codes,
        ]);
    }

    public function confirmTwoFactor(Request $request, TotpService $totp)
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! $user->two_factor_secret) {
            return response()->json(['message' => 'Two-factor setup not started'], 422);
        }

        if (! $totp->verify($user->two_factor_secret, $data['code'])) {
            $this->logAuditEvent($request, 'auth.2fa_confirm_failed', $user->email, [], $user->id);

            return response()->json(['message' => 'Invalid code'], 422);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->logAuditEvent($request, 'auth.2fa_confirmed', $user->email, [], $user->id);

        return response()->json(['message' => 'Two-factor enabled']);
    }

    public function disableTwoFactor(Request $request, TotpService $totp)
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid password'], 422);
        }

        if (! $user->two_factor_confirmed_at || ! $this->verifyTwoFactor($user, $data['code'], $request)) {
            return response()->json(['message' => 'Invalid two-factor code'], 422);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->logAuditEvent($request, 'auth.2fa_disabled', $user->email, [], $user->id);

        return response()->json(['message' => 'Two-factor disabled']);
    }

    private function verifyTwoFactor(User $user, string $code, Request $request): bool
    {
        $totp = app(TotpService::class);

        if ($totp->verify($user->two_factor_secret, $code)) {
            return true;
        }

        $normalized = strtolower(preg_replace('/\s+/', '', $code));
        $codes = $user->two_factor_recovery_codes ?? [];
        $remaining = array_values(array_filter($codes, fn ($c) => strtolower($c) !== $normalized));

        if (count($remaining) !== count($codes)) {
            $user->forceFill(['two_factor_recovery_codes' => $remaining])->save();
            $this->logAuditEvent($request, 'auth.2fa_recovery_used', $user->email, [
                'remaining_codes' => count($remaining),
            ], $user->id);

            return true;
        }

        return false;
    }

    public function trustedDevices(Request $request)
    {
        $devices = TrustedDevice::where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'ip_address' => $d->ip_address,
                'user_agent' => $d->user_agent,
                'last_used_at' => $d->last_used_at,
                'expires_at' => $d->expires_at,
                'created_at' => $d->created_at,
                'expired' => $d->expires_at->isPast(),
            ]);

        return response()->json(['data' => $devices]);
    }

    public function revokeTrustedDevice(Request $request, int $id)
    {
        $device = TrustedDevice::where('user_id', $request->user()->id)->find($id);

        if (! $device) {
            return response()->json(['message' => 'Trusted device not found'], 404);
        }

        $device->delete();

        $this->logAuditEvent($request, 'auth.device_trust_revoked', $request->user()->email, [
            'device_id' => $id,
        ], $request->user()->id);

        return response()->json(['message' => 'Trusted device revoked']);
    }

    private function extractTrustedDeviceToken(Request $request): ?string
    {
        $token = $request->header('X-Trusted-Device') ?? $request->input('trusted_device_token');

        return is_string($token) && $token !== '' ? $token : null;
    }

    private function findTrustedDevice(User $user, string $plainToken): ?TrustedDevice
    {
        $hash = hash('sha256', $plainToken);

        return TrustedDevice::where('user_id', $user->id)
            ->where('token_hash', $hash)
            ->where('expires_at', '>', now())
            ->first();
    }

    private function createTrustedDevice(User $user, Request $request): string
    {
        $plain = Str::random(64);

        TrustedDevice::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'name' => substr((string) $request->userAgent(), 0, 120) ?: null,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'expires_at' => now()->addDays(self::TRUSTED_DEVICE_TTL_DAYS),
        ]);

        $this->logAuditEvent($request, 'auth.device_trusted', $user->email, [], $user->id);

        return $plain;
    }

    public function loginHistory(Request $request)
    {
        $events = [
            'auth.login_success',
            'auth.new_device_login',
            'auth.failed_login',
            'auth.2fa_failed',
            'auth.login_locked',
            'auth.login_locked_account',
        ];

        $logs = AuditLog::where('user_id', $request->user()->id)
            ->whereIn('event', $events)
            ->latest('id')
            ->limit(20)
            ->get(['id', 'event', 'ip_address', 'user_agent', 'created_at']);

        return response()->json(['data' => $logs]);
    }

    private function detectAndNotifyNewDevice(User $user, Request $request): void
    {
        $ip = $request->ip();
        $ua = substr((string) $request->userAgent(), 0, 1000);

        $priorCount = AuditLog::where('user_id', $user->id)
            ->where('event', 'auth.login_success')
            ->count();

        // The current login_success has just been written, so >1 means there was prior history.
        if ($priorCount <= 1) {
            return;
        }

        $hasMatch = AuditLog::where('user_id', $user->id)
            ->where('event', 'auth.login_success')
            ->where('id', '!=', AuditLog::where('user_id', $user->id)->where('event', 'auth.login_success')->max('id'))
            ->where('created_at', '>=', now()->subDays(self::NEW_DEVICE_LOOKBACK_DAYS))
            ->where('ip_address', $ip)
            ->where('user_agent', $ua)
            ->exists();

        if ($hasMatch) {
            return;
        }

        $this->logAuditEvent($request, 'auth.new_device_login', $user->email, [
            'ip_address' => $ip,
        ], $user->id);

        try {
            $user->notify(new NewLoginNotification($ip ?? 'unknown', $ua ?: 'unknown', now()));
        } catch (\Throwable $t) {
            // never block login on notification failure
        }
    }
}
