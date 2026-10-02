<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\PinRecoveryAnswer;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Single authoritative resolver for Staff/Admin second-step verification.
 * Reuses WebTwoFactorAuthService + PinRecoveryChallengeService — no new KBA engine.
 */
class ConsoleSecondFactorService
{
    public const METHOD_AUTHENTICATOR = 'authenticator';

    public const METHOD_SECURITY_QUESTIONS = 'security_questions';

    public const STAFF_KBA_CACHE = 'staff_kba_login:';

    public const AUTHENTICATOR_ROLES_KEY = 'auth_portal.authenticator_required_roles';

    /** @var list<string> */
    public const DEFAULT_AUTHENTICATOR_ROLES = ['admin', 'super_admin'];

    public function __construct(
        private WebTwoFactorAuthService $totp,
        private PinRecoveryChallengeService $kba,
        private AuthPortalSettingsService $settings,
        private RoleService $roles,
    ) {}

    public function isSecondFactorRequired(string $context): bool
    {
        return $this->totp->isRequired($context);
    }

    /**
     * Roles that must use authenticator MFA (Settings Hub — not hard-coded forever).
     *
     * @return list<string>
     */
    public function authenticatorRequiredRoles(): array
    {
        $stored = Setting::get(self::AUTHENTICATOR_ROLES_KEY);
        if (is_array($stored) && $stored !== []) {
            $allowed = array_merge($this->roles->staffRoles(), $this->roles->consoleRoles());
            $out = [];
            foreach ($stored as $code) {
                $code = strtolower(trim((string) $code));
                if ($code !== '' && in_array($code, $allowed, true)) {
                    $out[] = $code;
                }
            }
            if ($out !== []) {
                return array_values(array_unique($out));
            }
        }

        return self::DEFAULT_AUTHENTICATOR_ROLES;
    }

    public function roleRequiresAuthenticator(User $user): bool
    {
        $required = $this->authenticatorRequiredRoles();

        return collect($user->roleCodes())->intersect($required)->isNotEmpty();
    }

    /** @deprecated Prefer roleRequiresAuthenticator() */
    public function isPrivilegedUser(User $user): bool
    {
        return $this->roleRequiresAuthenticator($user);
    }

    /**
     * Effective methods for this user on this portal context.
     *
     * @return list<string>
     */
    public function allowedMethodsFor(User $user, string $context): array
    {
        if (! $this->isSecondFactorRequired($context)) {
            return [];
        }

        if ($context === 'partner') {
            return [self::METHOD_AUTHENTICATOR];
        }

        // Authenticator-required roles never fall back to weaker verification.
        if ($this->roleRequiresAuthenticator($user)) {
            return [self::METHOD_AUTHENTICATOR];
        }

        // Ordinary Staff (including console desks) use Staff method Settings.
        if ($this->roles->isStaffUser($user)) {
            $methods = [];
            if ($this->settings->staffAllowAuthenticator()) {
                $methods[] = self::METHOD_AUTHENTICATOR;
            }
            if ($this->settings->staffAllowSecurityQuestions()) {
                $methods[] = self::METHOD_SECURITY_QUESTIONS;
            }

            return array_values(array_unique($methods));
        }

        // Non-staff Admin console identities: authenticator only.
        return [self::METHOD_AUTHENTICATOR];
    }

    /**
     * Full policy snapshot every auth entry point should consume.
     *
     * @return array{
     *   context: string,
     *   required: bool,
     *   role_requires_authenticator: bool,
     *   methods: list<string>,
     *   enrolled: string|null,
     *   must_enroll: bool,
     *   needs_challenge: bool,
     *   setup_url: string|null,
     *   challenge_url: string|null,
     *   next: string
     * }
     */
    public function effectivePolicy(User $user, string $context, ?Request $request = null): array
    {
        $required = $this->isSecondFactorRequired($context);
        $methods = $this->allowedMethodsFor($user, $context);
        $enrolled = $this->enrolledMethod($user, $context);
        $mustEnroll = $required && $enrolled === null && $methods !== [];
        $needsChallenge = false;
        if ($required && $enrolled !== null && $request) {
            $needsChallenge = ! $this->totp->sessionVerified($request);
        }

        $next = 'continue';
        $setupUrl = null;
        $challengeUrl = null;
        if ($mustEnroll) {
            $next = 'enroll';
            $setupUrl = $this->setupRedirect($user, $context);
        } elseif ($needsChallenge) {
            $next = 'challenge';
            $challengeUrl = $this->challengeRedirect($user, $context);
        } elseif (! $required) {
            $next = 'skip';
        }

        return [
            'context' => $context,
            'required' => $required,
            'role_requires_authenticator' => $this->roleRequiresAuthenticator($user),
            'methods' => $methods,
            'enrolled' => $enrolled,
            'must_enroll' => $mustEnroll,
            'needs_challenge' => $needsChallenge,
            'setup_url' => $setupUrl,
            'challenge_url' => $challengeUrl,
            'next' => $next,
        ];
    }

    public function hasAuthenticator(User $user): bool
    {
        return $this->totp->isEnabled($user);
    }

    public function hasSecurityQuestions(User $user): bool
    {
        return $this->kba->hasEnrolledAnswers($user);
    }

    public function enrolledMethod(User $user, string $context): ?string
    {
        $allowed = $this->allowedMethodsFor($user, $context);

        if (in_array(self::METHOD_AUTHENTICATOR, $allowed, true) && $this->hasAuthenticator($user)) {
            return self::METHOD_AUTHENTICATOR;
        }

        if (in_array(self::METHOD_SECURITY_QUESTIONS, $allowed, true) && $this->hasSecurityQuestions($user)) {
            return self::METHOD_SECURITY_QUESTIONS;
        }

        return null;
    }

    public function mustEnroll(User $user, string $context): bool
    {
        return $this->effectivePolicy($user, $context)['must_enroll'];
    }

    public function needsChallenge(User $user, Request $request, string $context): bool
    {
        return $this->effectivePolicy($user, $context, $request)['needs_challenge'];
    }

    public function challengeMethod(User $user, string $context): ?string
    {
        return $this->enrolledMethod($user, $context);
    }

    public function setupRedirect(User $user, string $context): string
    {
        $allowed = $this->allowedMethodsFor($user, $context);

        if ($allowed === []) {
            // No methods configured — should be blocked at Settings save; never invent TOTP.
            return route('auth.secure.questions.setup', ['context' => $context]);
        }

        if (count($allowed) > 1) {
            return route('auth.secure.choose', ['context' => $context]);
        }

        if ($allowed[0] === self::METHOD_SECURITY_QUESTIONS) {
            return route('auth.secure.questions.setup', ['context' => $context]);
        }

        return route('auth.two-factor.setup', ['context' => $context]);
    }

    public function challengeRedirect(User $user, string $context): string
    {
        $method = $this->challengeMethod($user, $context);

        if ($method === self::METHOD_SECURITY_QUESTIONS) {
            return route('auth.secure.questions.challenge', ['context' => $context]);
        }

        return route('auth.two-factor.challenge', ['context' => $context]);
    }

    /**
     * Start a single-question Staff login challenge (rotates across separate logins).
     *
     * @return array{token: string, question: array{key: string, prompt: string, input: string, digits: int|null}}
     */
    public function beginSecurityQuestionChallenge(User $user): array
    {
        $questions = $this->kba->enrolledQuestions($user);
        if ($questions === []) {
            throw new \RuntimeException('No security questions enrolled.');
        }

        $keys = collect($questions)->pluck('key')->all();
        $lastKey = Cache::get('staff_kba_last:'.$user->id);
        $pool = $keys;
        if (is_string($lastKey) && count($pool) > 1) {
            $pool = array_values(array_filter($pool, fn ($k) => $k !== $lastKey));
        }
        if ($pool === []) {
            $pool = $keys;
        }

        shuffle($pool);
        $chosenKey = $pool[0];
        $question = collect($questions)->firstWhere('key', $chosenKey) ?? $questions[0];

        $token = Str::random(40);
        Cache::put(self::STAFF_KBA_CACHE.$token, [
            'user_id' => $user->id,
            'question_key' => $question['key'],
            'attempts' => 0,
        ], now()->addMinutes(15));

        return [
            'token' => $token,
            'question' => $question,
        ];
    }

    /**
     * Verify one answer. Never rotates the question within the same attempt.
     *
     * @return array{ok: bool, reason?: string, remaining_attempts?: int}
     */
    public function verifySecurityQuestionChallenge(string $token, string $rawAnswer, Request $request): array
    {
        $payload = Cache::get(self::STAFF_KBA_CACHE.$token);
        if (! is_array($payload) || empty($payload['user_id']) || empty($payload['question_key'])) {
            return ['ok' => false, 'reason' => 'expired'];
        }

        $attempts = (int) ($payload['attempts'] ?? 0) + 1;
        $payload['attempts'] = $attempts;
        $max = (int) config('pin_recovery.max_attempts', 5);
        Cache::put(self::STAFF_KBA_CACHE.$token, $payload, now()->addMinutes(15));

        if ($attempts > $max) {
            Cache::forget(self::STAFF_KBA_CACHE.$token);
            $this->audit($request, 'auth.staff_kba_locked', (int) $payload['user_id']);

            return ['ok' => false, 'reason' => 'locked', 'remaining_attempts' => 0];
        }

        $key = (string) $payload['question_key'];
        $row = PinRecoveryAnswer::query()
            ->where('user_id', $payload['user_id'])
            ->where('question_key', $key)
            ->first();

        $normalized = $this->kba->normalize($key, $rawAnswer);
        $ok = $row && $normalized !== '' && Hash::check($normalized, (string) $row->answer_hash);

        if (! $ok) {
            $this->audit($request, 'auth.staff_kba_failed', (int) $payload['user_id'], [
                'attempts' => $attempts,
            ]);

            return [
                'ok' => false,
                'reason' => 'mismatch',
                'remaining_attempts' => max(0, $max - $attempts),
            ];
        }

        Cache::forget(self::STAFF_KBA_CACHE.$token);
        Cache::put('staff_kba_last:'.$payload['user_id'], $key, now()->addDays(30));
        $this->audit($request, 'auth.staff_kba_passed', (int) $payload['user_id']);

        return ['ok' => true];
    }

    public function clearSecurityQuestions(User $actor, User $target, Request $request, ?string $reason = null): void
    {
        PinRecoveryAnswer::query()->where('user_id', $target->id)->delete();
        Cache::forget('staff_kba_last:'.$target->id);

        AuditLog::create([
            'user_id' => $actor->id,
            'event' => 'admin.user_security_questions_reset',
            'auditable_type' => User::class,
            'auditable_id' => $target->id,
            'old_values' => null,
            'new_values' => json_encode([
                'reset_by' => $actor->id,
                'reason' => $reason,
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);
    }

    /** @param  array<string, mixed>  $meta */
    protected function audit(Request $request, string $event, int $userId, array $meta = []): void
    {
        try {
            AuditLog::create([
                'user_id' => $userId,
                'event' => $event,
                'auditable_type' => User::class,
                'auditable_id' => $userId,
                'old_values' => null,
                'new_values' => $meta === [] ? null : json_encode($meta),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            ]);
        } catch (\Throwable) {
        }
    }
}
