<?php

namespace App\Services;

use App\Models\PinRecoveryAnswer;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PinRecoveryChallengeService
{
    public const CACHE_PREFIX = 'pin_kba:';

    /** @return array<string, array{prompt_key: string, input: string, digits?: int}> */
    public function bank(): array
    {
        return config('pin_recovery.bank', []);
    }

    public function promptFor(string $key): string
    {
        $item = $this->bank()[$key] ?? null;
        if (! $item) {
            return $key;
        }

        return __($item['prompt_key']);
    }

    /**
     * Pick N random question keys for enrollment (persisted in session by the controller).
     *
     * @return list<string>
     */
    public function pickRandomKeys(?int $count = null): array
    {
        $keys = array_keys($this->bank());
        shuffle($keys);
        $count = $count ?? (int) config('pin_recovery.questions_to_ask', 3);

        return array_values(array_slice($keys, 0, max(1, min($count, count($keys)))));
    }

    /**
     * @param  list<string>  $keys
     * @return list<array{key: string, prompt: string, input: string, digits: int|null}>
     */
    public function questionsForKeys(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $meta = $this->bank()[$key] ?? null;
            if (! $meta) {
                continue;
            }
            $out[] = [
                'key' => $key,
                'prompt' => __($meta['prompt_key']),
                'input' => $meta['input'] ?? 'text',
                'digits' => $meta['digits'] ?? null,
            ];
        }

        return $out;
    }

    public function hasEnrolledAnswers(User $user): bool
    {
        return PinRecoveryAnswer::query()
            ->where('user_id', $user->id)
            ->count() >= (int) config('pin_recovery.questions_to_ask', 3);
    }

    /**
     * @return list<array{key: string, prompt: string, input: string, digits: int|null}>
     */
    public function enrolledQuestions(User $user): array
    {
        $rows = PinRecoveryAnswer::query()
            ->where('user_id', $user->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['question_key']);

        return $this->questionsForKeys($rows->pluck('question_key')->all());
    }

    /**
     * Save the member's own answers (hashed). Replaces any previous set.
     *
     * @param  array<string, string>  $answers  question_key => raw answer
     */
    public function enroll(User $user, array $answers): void
    {
        $normalized = [];
        foreach ($answers as $key => $raw) {
            if (! isset($this->bank()[$key])) {
                continue;
            }
            $value = $this->normalize($key, (string) $raw);
            if ($value === '') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "answers.$key" => __('site.auth.pin_recovery.answer_required'),
                ]);
            }
            $normalized[$key] = $value;
        }

        $needed = (int) config('pin_recovery.questions_to_ask', 3);
        if (count($normalized) < $needed) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'answers' => __('site.auth.pin_recovery.answer_all_required'),
            ]);
        }

        DB::transaction(function () use ($user, $normalized) {
            PinRecoveryAnswer::query()->where('user_id', $user->id)->delete();
            $order = 0;
            foreach ($normalized as $key => $value) {
                PinRecoveryAnswer::query()->create([
                    'user_id' => $user->id,
                    'question_key' => $key,
                    'answer_hash' => Hash::make($value),
                    'sort_order' => $order++,
                ]);
            }
        });
    }

    /**
     * Start a forgot-PIN session for an enrolled user.
     *
     * @return array{token: string, questions: list<array{key: string, prompt: string, input: string, digits: int|null}>, required_correct: int}|null
     */
    public function startForUser(User $user): ?array
    {
        if (! $this->hasEnrolledAnswers($user)) {
            return null;
        }

        $questions = $this->enrolledQuestions($user);
        $token = Str::random(40);
        $minutes = (int) config('pin_recovery.session_minutes', 15);

        Cache::put(self::CACHE_PREFIX.$token, [
            'user_id' => $user->id,
            'attempts' => 0,
            'verified' => false,
            'question_keys' => collect($questions)->pluck('key')->all(),
        ], now()->addMinutes($minutes));

        return [
            'token' => $token,
            'questions' => $questions,
            'required_correct' => (int) config('pin_recovery.required_correct', 2),
        ];
    }

    /**
     * @param  array<string, string>  $submitted  question_key => answer
     * @return array{ok: bool, remaining_attempts?: int, reason?: string}
     */
    public function verify(string $token, array $submitted): array
    {
        $payload = Cache::get(self::CACHE_PREFIX.$token);
        if (! is_array($payload) || empty($payload['user_id'])) {
            return ['ok' => false, 'reason' => 'expired'];
        }

        $attempts = (int) ($payload['attempts'] ?? 0) + 1;
        $payload['attempts'] = $attempts;
        $max = (int) config('pin_recovery.max_attempts', 5);
        Cache::put(self::CACHE_PREFIX.$token, $payload, now()->addMinutes((int) config('pin_recovery.session_minutes', 15)));

        if ($attempts > $max) {
            Cache::forget(self::CACHE_PREFIX.$token);

            return ['ok' => false, 'reason' => 'locked'];
        }

        $rows = PinRecoveryAnswer::query()
            ->where('user_id', $payload['user_id'])
            ->get()
            ->keyBy('question_key');

        $correct = 0;
        foreach ($rows as $key => $row) {
            $given = $this->normalize($key, (string) ($submitted[$key] ?? ''));
            if ($given !== '' && Hash::check($given, $row->answer_hash)) {
                $correct++;
            }
        }

        $required = (int) config('pin_recovery.required_correct', 2);
        if ($correct < $required) {
            return [
                'ok' => false,
                'reason' => 'mismatch',
                'remaining_attempts' => max(0, $max - $attempts),
            ];
        }

        $payload['verified'] = true;
        Cache::put(self::CACHE_PREFIX.$token, $payload, now()->addMinutes((int) config('pin_recovery.session_minutes', 15)));

        return ['ok' => true];
    }

    public function consumeVerified(string $token): ?array
    {
        $payload = Cache::get(self::CACHE_PREFIX.$token);
        if (! is_array($payload) || empty($payload['verified'])) {
            return null;
        }

        Cache::forget(self::CACHE_PREFIX.$token);

        return $payload;
    }

    public function forget(string $token): void
    {
        Cache::forget(self::CACHE_PREFIX.$token);
    }

    public function normalize(string $questionKey, string $raw): string
    {
        $meta = $this->bank()[$questionKey] ?? ['input' => 'text'];
        $raw = trim($raw);

        if (($meta['input'] ?? 'text') === 'digits') {
            $digits = preg_replace('/\D/', '', $raw) ?? '';
            $len = (int) ($meta['digits'] ?? 4);

            return substr($digits, 0, $len);
        }

        $value = mb_strtolower($raw);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }
}
