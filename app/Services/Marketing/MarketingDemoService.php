<?php

namespace App\Services\Marketing;

use App\Models\MarketingDemoEvent;
use App\Models\MarketingDemoSession;
use App\Models\MarketingPersona;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MarketingDemoService
{
    public function ensureSystemPersonas(): void
    {
        foreach (config('marketing.personas', []) as $key => $persona) {
            MarketingPersona::query()->firstOrCreate(
                ['key' => $key],
                [
                    'name' => $persona['name'],
                    'role' => $persona['role'] ?? 'borrower',
                    'summary' => $persona['summary'] ?? null,
                    'traits' => $persona['traits'] ?? [],
                    'defaults' => $persona['defaults'] ?? [],
                    'restricted' => (bool) ($persona['restricted'] ?? false),
                    'is_system' => true,
                ]
            );
        }
    }

    public function expireOverdue(): int
    {
        $sessions = MarketingDemoSession::query()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($sessions as $session) {
            $this->end($session, 'expired');
        }

        return $sessions->count();
    }

    /**
     * @param  array{
     *   who: string,
     *   persona_key: string,
     *   scenario_key: string,
     *   display_name?: string,
     *   amount?: mixed,
     *   grade?: ?string,
     *   trust?: mixed,
     *   duration?: string,
     *   custom_expires_at?: ?string,
     * }  $data
     */
    public function create(array $data, User $actor, bool $unrestricted = false): MarketingDemoSession
    {
        $this->ensureSystemPersonas();
        $this->expireOverdue();

        $who = (string) $data['who'];
        $personaKey = (string) $data['persona_key'];
        $persona = MarketingPersona::query()->where('key', $personaKey)->first();
        if (! $persona) {
            throw ValidationException::withMessages(['persona_key' => 'Unknown persona.']);
        }

        if ($who === 'affiliate' && ! $unrestricted && ! $persona->restricted) {
            throw ValidationException::withMessages([
                'persona_key' => 'Affiliate demos are limited to approved templates.',
            ]);
        }

        $defaults = $persona->defaults ?? [];
        $grade = $unrestricted ? ($data['grade'] ?? $defaults['grade'] ?? null) : ($defaults['grade'] ?? $data['grade'] ?? null);
        $trust = $unrestricted ? ($data['trust'] ?? $defaults['trust'] ?? null) : ($defaults['trust'] ?? $data['trust'] ?? null);
        $amount = $unrestricted
            ? ($data['amount'] ?? $defaults['amount'] ?? null)
            : ($defaults['amount'] ?? $data['amount'] ?? null);

        $name = trim((string) ($data['display_name'] ?? ''));
        if ($name === '') {
            $name = $persona->name;
        }

        $expiresAt = $this->resolveExpiry((string) ($data['duration'] ?? '30'), $data['custom_expires_at'] ?? null);
        $payload = $this->decoratePayload([
            'who' => $who,
            'persona' => $persona->name,
            'persona_key' => $personaKey,
            'scenario' => $data['scenario_key'],
            'scenario_label' => config('marketing.scenarios.'.$data['scenario_key'].'.label', $data['scenario_key']),
            'name' => $name,
            'grade' => $grade,
            'trust' => $trust !== null ? (int) $trust : null,
            'amount' => $amount !== null ? (float) $amount : null,
            'plus' => (bool) ($defaults['plus'] ?? ($who === 'plus')),
            'country' => 'TZ',
            'currency' => 'TZS',
            'can_move_money' => false,
        ]);

        $session = MarketingDemoSession::query()->create([
            'token' => Str::lower(Str::random(48)),
            'status' => 'active',
            'who' => $who,
            'persona_key' => $personaKey,
            'scenario_key' => $data['scenario_key'],
            'display_name' => $name,
            'payload' => $payload,
            'starts_at' => now(),
            'expires_at' => $expiresAt,
            'created_by' => $actor->id,
        ]);

        $this->record($session, 'created', ['who' => $who, 'persona' => $personaKey], $actor);

        return $session;
    }

    public function end(MarketingDemoSession $session, string $reason = 'ended', ?User $actor = null): MarketingDemoSession
    {
        if ($session->status !== 'active' && $session->status !== 'expired') {
            return $session;
        }

        $session->update([
            'status' => $reason === 'expired' ? 'expired' : 'ended',
            'ended_at' => now(),
            'ended_reason' => $reason,
            'ended_by' => $actor?->id,
        ]);

        $this->record($session, $reason === 'expired' ? 'expired' : 'ended', [], $actor);

        return $session->fresh();
    }

    /**
     * @param  array{display_name?: string, amount?: mixed, grade?: ?string, trust?: mixed}  $data
     */
    public function customize(MarketingDemoSession $session, array $data, User $actor, bool $unrestricted = false): MarketingDemoSession
    {
        if (! $session->isLive()) {
            throw ValidationException::withMessages(['demo' => 'This demo has ended.']);
        }

        $payload = is_array($session->payload) ? $session->payload : [];
        $affiliateLocked = ($payload['who'] ?? $session->who) === 'affiliate' && ! $unrestricted;

        if (filled($data['display_name'] ?? null)) {
            $name = trim((string) $data['display_name']);
            $payload['name'] = $name;
            $session->display_name = $name;
        }

        if (! $affiliateLocked) {
            if (array_key_exists('amount', $data) && $data['amount'] !== null && $data['amount'] !== '') {
                $payload['amount'] = (float) $data['amount'];
            }
            if (filled($data['grade'] ?? null)) {
                $payload['grade'] = (string) $data['grade'];
            }
            if (array_key_exists('trust', $data) && $data['trust'] !== null && $data['trust'] !== '') {
                $payload['trust'] = (int) $data['trust'];
            }
        }

        $session->payload = $this->decoratePayload($payload);
        $session->save();
        $this->record($session, 'customized', [], $actor);

        return $session->fresh();
    }

    public function record(MarketingDemoSession $session, string $event, array $meta = [], ?User $actor = null): void
    {
        MarketingDemoEvent::query()->create([
            'marketing_demo_session_id' => $session->id,
            'event' => $event,
            'meta' => $meta,
            'actor_id' => $actor?->id,
        ]);
    }

    private function resolveExpiry(string $duration, ?string $custom): \Carbon\CarbonInterface
    {
        return match ($duration) {
            '5' => now()->addMinutes(5),
            '15' => now()->addMinutes(15),
            '30' => now()->addMinutes(30),
            '60' => now()->addHour(),
            'today' => now()->endOfDay(),
            'custom' => filled($custom) ? \Carbon\Carbon::parse($custom) : now()->addMinutes(30),
            default => now()->addMinutes(30),
        };
    }

    /** @param  array<string, mixed>  $payload */
    private function decoratePayload(array $payload): array
    {
        $amount = (float) ($payload['amount'] ?? 0);
        $trust = (int) ($payload['trust'] ?? 70);
        $paid = $amount > 0 ? round($amount * 0.35, 0) : 0;
        $balance = max(0, $amount - $paid);
        $payload['membership_no'] = $payload['membership_no'] ?? 'KF-DEMO-'.strtoupper(substr((string) ($payload['name'] ?? 'Asha'), 0, 3)).'42';
        $payload['loan_balance'] = $balance;
        $payload['amount_paid'] = $paid;
        $payload['next_payment'] = $amount > 0 ? round($amount / 6, 0) : 0;
        $payload['next_due'] = now()->addDays(12)->toFormattedDateString();
        $payload['goal_name'] = $payload['goal_name'] ?? 'Shop stock';
        $payload['goal_percent'] = min(100, max(18, $trust - 12));
        $payload['goal_saved'] = $amount > 0 ? round($amount * (($payload['goal_percent']) / 100), 0) : 180000;
        $payload['affiliate_earnings'] = $amount > 0 ? $amount : 350000;
        $payload['affiliate_available'] = round(((float) $payload['affiliate_earnings']) * 0.72, 0);
        $payload['report_month'] = now()->subMonth()->format('F Y');
        $payload['can_move_money'] = false;

        return $payload;
    }
}
