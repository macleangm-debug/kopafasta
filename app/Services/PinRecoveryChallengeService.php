<?php

namespace App\Services;

use App\Models\Customer;
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

    /**
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
     * Profile-based fallback for members who never enrolled security questions.
     *
     * @return list<array{key: string, prompt: string, input: string, digits: int|null, answer: string}>
     */
    public function profileEligibleQuestions(Customer $customer): array
    {
        $pool = [];

        if ($customer->date_of_birth) {
            $pool[] = [
                'key' => 'profile_dob',
                'prompt' => __('site.auth.pin_recovery.profile.q_dob'),
                'input' => 'text',
                'digits' => null,
                'answer' => $customer->date_of_birth->format('Y-m-d'),
            ];
        }

        if (filled($customer->national_id)) {
            $digits = preg_replace('/\D/', '', (string) $customer->national_id) ?? '';
            if (strlen($digits) >= 11) {
                $middle = substr($digits, 7, 4);
                $pool[] = [
                    'key' => 'profile_nida_middle4',
                    'prompt' => __('site.auth.pin_recovery.profile.q_nida_middle4'),
                    'input' => 'digits',
                    'digits' => 4,
                    'answer' => $middle,
                ];
            }
        }

        if (filled($customer->member_no)) {
            $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $customer->member_no) ?? '');
            if (strlen($clean) >= 4) {
                $pool[] = [
                    'key' => 'profile_member_last4',
                    'prompt' => __('site.auth.pin_recovery.profile.q_member_last4'),
                    'input' => 'text',
                    'digits' => null,
                    'answer' => substr($clean, -4),
                ];
            }
        }

        if (filled($customer->middle_name)) {
            $pool[] = [
                'key' => 'profile_middle_name',
                'prompt' => __('site.auth.pin_recovery.profile.q_middle_name'),
                'input' => 'text',
                'digits' => null,
                'answer' => $this->normalizeText((string) $customer->middle_name),
            ];
        }

        if (filled($customer->district)) {
            $pool[] = [
                'key' => 'profile_district',
                'prompt' => __('site.auth.pin_recovery.profile.q_district'),
                'input' => 'text',
                'digits' => null,
                'answer' => $this->normalizeText((string) $customer->district),
            ];
        }

        if (filled($customer->ward)) {
            $pool[] = [
                'key' => 'profile_ward',
                'prompt' => __('site.auth.pin_recovery.profile.q_ward'),
                'input' => 'text',
                'digits' => null,
                'answer' => $this->normalizeText((string) $customer->ward),
            ];
        }

        if (filled($customer->nok_first_name) || filled($customer->nok_name)) {
            $first = filled($customer->nok_first_name)
                ? (string) $customer->nok_first_name
                : (string) strtok(trim((string) $customer->nok_name), ' ');
            if ($first !== '') {
                $pool[] = [
                    'key' => 'profile_nok_first_name',
                    'prompt' => __('site.auth.pin_recovery.profile.q_nok_first_name'),
                    'input' => 'text',
                    'digits' => null,
                    'answer' => $this->normalizeText($first),
                ];
            }
        }

        if (filled($customer->gender)) {
            $pool[] = [
                'key' => 'profile_gender',
                'prompt' => __('site.auth.pin_recovery.profile.q_gender'),
                'input' => 'text',
                'digits' => null,
                'answer' => $this->normalizeText((string) $customer->gender),
            ];
        }

        return $pool;
    }

    /**
     * @param  array<string, string>  $answers
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
     * Prefer enrolled answers; otherwise profile fields so legacy members can still reset.
     *
     * @return array{token: string, questions: list<array{key: string, prompt: string, input: string, digits: int|null}>, required_correct: int, mode: string}|null
     */
    public function startForUser(User $user): ?array
    {
        if ($this->hasEnrolledAnswers($user)) {
            return $this->startEnrolled($user);
        }

        $customer = $user->customer;
        if ($customer) {
            return $this->startProfile($user, $customer);
        }

        return null;
    }

    /**
     * @return array{token: string, questions: list<array{key: string, prompt: string, input: string, digits: int|null}>, required_correct: int, mode: string}
     */
    private function startEnrolled(User $user): array
    {
        $questions = $this->enrolledQuestions($user);
        $token = Str::random(40);

        Cache::put(self::CACHE_PREFIX.$token, [
            'user_id' => $user->id,
            'mode' => 'enrolled',
            'attempts' => 0,
            'verified' => false,
            'question_keys' => collect($questions)->pluck('key')->all(),
        ], now()->addMinutes((int) config('pin_recovery.session_minutes', 15)));

        return [
            'token' => $token,
            'mode' => 'enrolled',
            'questions' => $questions,
            'required_correct' => (int) config('pin_recovery.required_correct', 2),
        ];
    }

    /**
     * @return array{token: string, questions: list<array{key: string, prompt: string, input: string, digits: int|null}>, required_correct: int, mode: string}|null
     */
    private function startProfile(User $user, Customer $customer): ?array
    {
        $eligible = $this->profileEligibleQuestions($customer);
        if (count($eligible) < 2) {
            return null;
        }

        $priority = ['profile_dob', 'profile_nida_middle4', 'profile_member_last4'];
        usort($eligible, function (array $a, array $b) use ($priority): int {
            $ai = array_search($a['key'], $priority, true);
            $bi = array_search($b['key'], $priority, true);

            return ($ai === false ? 99 : $ai) <=> ($bi === false ? 99 : $bi);
        });

        $selected = array_slice($eligible, 0, 3);
        shuffle($selected);

        $questions = [];
        $expected = [];
        foreach ($selected as $item) {
            $questions[] = [
                'key' => $item['key'],
                'prompt' => $item['prompt'],
                'input' => $item['input'],
                'digits' => $item['digits'],
            ];
            $expected[$item['key']] = Hash::make($item['answer']);
        }

        $token = Str::random(40);
        Cache::put(self::CACHE_PREFIX.$token, [
            'user_id' => $user->id,
            'mode' => 'profile',
            'attempts' => 0,
            'verified' => false,
            'expected' => $expected,
        ], now()->addMinutes((int) config('pin_recovery.session_minutes', 15)));

        return [
            'token' => $token,
            'mode' => 'profile',
            'questions' => $questions,
            'required_correct' => (int) config('pin_recovery.required_correct', 2),
        ];
    }

    /**
     * @param  array<string, string>  $submitted
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

        $correct = 0;
        $mode = (string) ($payload['mode'] ?? 'enrolled');

        if ($mode === 'profile') {
            foreach (($payload['expected'] ?? []) as $key => $hash) {
                $given = $this->normalizeProfileKey((string) $key, (string) ($submitted[$key] ?? ''));
                if ($given !== '' && Hash::check($given, $hash)) {
                    $correct++;
                }
            }
        } else {
            $rows = PinRecoveryAnswer::query()
                ->where('user_id', $payload['user_id'])
                ->get()
                ->keyBy('question_key');

            foreach ($rows as $key => $row) {
                $given = $this->normalize($key, (string) ($submitted[$key] ?? ''));
                if ($given !== '' && Hash::check($given, $row->answer_hash)) {
                    $correct++;
                }
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

        return $this->normalizeText($raw);
    }

    public function normalizeProfileKey(string $key, string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        return match ($key) {
            'profile_dob' => $this->normalizeDob($raw) ?? '',
            'profile_nida_middle4' => substr(preg_replace('/\D/', '', $raw) ?? '', 0, 4),
            'profile_member_last4' => strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $raw) ?? '', -4)),
            'profile_gender' => $this->normalizeText($raw),
            default => $this->normalizeText($raw),
        };
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return $value;
    }

    private function normalizeDob(string $value): ?string
    {
        $value = trim(str_replace(['\\', '.'], ['/', '/'], $value));
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd/m/y', 'd-m-y'] as $format) {
            try {
                $dt = \Carbon\Carbon::createFromFormat($format, $value);
                if ($dt !== false) {
                    return $dt->format('Y-m-d');
                }
            } catch (\Throwable) {
            }
        }

        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
