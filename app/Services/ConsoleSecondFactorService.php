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
 * Orchestrates Staff/Admin second-step verification (TOTP and/or security questions).
 * Reuses WebTwoFactorAuthService + PinRecoveryChallengeService — no new KBA engine.
 */
class ConsoleSecondFactorService
{
    public const METHOD_AUTHENTICATOR = 'authenticator';

    public const METHOD_SECURITY_QUESTIONS = 'security_questions';

    public const STAFF_KBA_CACHE = 'staff_kba_login:';

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

    public function isPrivilegedUser(User $user): bool
    {
        $roles = collect($user->roles ?? [])->merge([$user->role])->filter()->unique()->values();

        return $roles->intersect(['admin', 'super_admin'])->isNotEmpty();
    }

    /** @return list<string> */
    public function allowedMethodsFor(User $user, string $context): array
    {
        if (! $this->isSecondFactorRequired($context)) {
            return [];
        }

        $methods = [];

        if ($this->settings->staffAllowAuthenticator() || $context === 'admin' || $this->isPrivilegedUser($user)) {
            $methods[] = self::METHOD_AUTHENTICATOR;
        }

        $questionsOk = $this->settings->staffAllowSecurityQuestions()
            && $context === 'staff'
            && ! ($this->settings->privilegedRequireAuthenticator() && $this->isPrivilegedUser($user));

        if ($questionsOk) {
            $methods[] = self::METHOD_SECURITY_QUESTIONS;
        }

        // Privileged admin minimum: authenticator must remain available and preferred.
        if ($this->settings->privilegedRequireAuthenticator() && $this->isPrivilegedUser($user)) {
            $methods = [self::METHOD_AUTHENTICATOR];
        }

        // Admin console context: keep authenticator as the stronger path by default.
        if ($context === 'admin' && $this->settings->privilegedRequireAuthenticator()) {
            $methods = [self::METHOD_AUTHENTICATOR];
        }

        return array_values(array_unique($methods));
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
        if (! $this->isSecondFactorRequired($context)) {
            return false;
        }

        return $this->enrolledMethod($user, $context) === null;
    }

    public function needsChallenge(User $user, Request $request, string $context): bool
    {
        if (! $this->isSecondFactorRequired($context)) {
            return false;
        }

        if ($this->mustEnroll($user, $context)) {
            return false;
        }

        if ($this->totp->sessionVerified($request)) {
            return false;
        }

        return true;
    }

    public function challengeMethod(User $user, string $context): ?string
    {
        return $this->enrolledMethod($user, $context);
    }

    public function setupRedirect(User $user, string $context): string
    {
        $allowed = $this->allowedMethodsFor($user, $context);

        if (count($allowed) > 1) {
            return route('auth.secure.choose', ['context' => $context]);
        }

        if (($allowed[0] ?? null) === self::METHOD_SECURITY_QUESTIONS) {
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
