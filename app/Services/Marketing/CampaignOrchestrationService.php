<?php

namespace App\Services\Marketing;

use App\Models\PlusSubscription;
use App\Models\Promotion;
use App\Services\Messaging\TransactionalMessagingService;
use App\Services\PromotionService;
use App\Support\MoneyFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CampaignOrchestrationService
{
    public function __construct(
        private readonly MarketingAudienceService $audiences,
        private readonly TransactionalMessagingService $messaging,
    ) {}

    /** @return array<string, string> */
    public function enabledChannels(): array
    {
        $this->messaging->ensureDefaults();
        $labels = [
            'in_app' => 'In-app',
            'sms' => 'SMS',
            'email' => 'Email',
            'whatsapp' => 'WhatsApp',
        ];
        $enabled = [];
        foreach ($labels as $key => $label) {
            if ($this->messaging->channelEnabled($key)) {
                $enabled[$key] = $label;
            }
        }

        return $enabled;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function transformPayload(array $validated, ?Promotion $existing = null): array
    {
        $intent = (string) ($validated['intent'] ?? 'fee_promotion');
        $intentOther = trim((string) ($validated['intent_other'] ?? ''));
        unset($validated['intent'], $validated['intent_other']);

        $channels = array_values(array_filter((array) ($validated['channels'] ?? ['in_app'])));
        $allowed = array_keys($this->enabledChannels());
        $channels = array_values(array_intersect($channels, $allowed !== [] ? $allowed : ['in_app']));
        if ($channels === []) {
            $channels = ['in_app'];
        }

        $filters = [
            'country_code' => strtoupper((string) ($validated['country_code'] ?? '')),
            'status' => (string) ($validated['audience_status'] ?? ''),
            'grades' => array_values(array_filter((array) ($validated['grades'] ?? []))),
            'plus' => (string) ($validated['plus'] ?? 'any'),
            'borrowing' => (string) ($validated['borrowing'] ?? 'any'),
            'affiliate' => (string) ($validated['affiliate'] ?? 'any'),
        ];

        $audienceMode = (string) ($validated['audience_mode'] ?? 'everyone');
        if (! in_array($audienceMode, ['everyone', 'saved', 'custom'], true)) {
            $audienceMode = 'everyone';
        }
        $audienceId = $audienceMode === 'saved' ? ($validated['audience_id'] ?? null) : null;
        if ($audienceMode === 'everyone') {
            $filters = [
                'country_code' => '',
                'status' => 'active',
                'grades' => [],
                'plus' => 'any',
                'borrowing' => 'any',
                'affiliate' => 'any',
            ];
        }
        if ($audienceId) {
            $saved = \App\Models\MarketingAudience::query()->find($audienceId);
            if ($saved) {
                $filters = array_merge($filters, $saved->filters ?? []);
            }
        } elseif (($filters['status'] ?? '') === '') {
            $filters['status'] = 'active';
        }

        $sendMode = (string) ($validated['send_mode'] ?? 'now');
        $quietHonoured = $this->shouldDeferForQuietHours($channels, $sendMode);
        if ($quietHonoured) {
            $sendMode = 'schedule';
        }

        $type = $this->typeForIntent($intent, $validated['type'] ?? null);
        $code = strtoupper(trim((string) ($validated['code'] ?? '')));
        if ($code === '') {
            $code = 'KF-'.strtoupper(Str::random(6));
        }

        $meta = $existing?->metadata ?? [];
        $meta = array_merge($meta, [
            'intent' => $intent,
            'intent_other' => $intent === 'other' ? $intentOther : null,
            'audience_mode' => $audienceMode,
            'audience_id' => $audienceId ? (int) $audienceId : null,
            'audience_filters' => $filters,
            'estimated_reach' => $this->audiences->estimate($filters),
            'channels' => $channels,
            'send_mode' => $sendMode,
            'quiet_hours_honoured' => $quietHonoured,
            'payload_type' => (string) ($validated['payload_type'] ?? 'message'),
            'offer_id' => $validated['offer_id'] ?? null,
            'cta_url' => $validated['cta_url'] ?? null,
            'article_hint' => $validated['article_hint'] ?? null,
        ]);

        foreach ([
            'audience_mode', 'audience_id', 'audience_status', 'country_code', 'grades', 'plus',
            'borrowing', 'affiliate', 'channels', 'send_mode', 'payload_type', 'offer_id',
            'cta_url', 'article_hint',
        ] as $key) {
            unset($validated[$key]);
        }

        $validated['code'] = $code;
        $validated['type'] = $type;
        if ($intent !== 'fee_promotion') {
            $validated['applies_to'] = $validated['applies_to'] ?? null;
            if (! PromotionService::isAllowedAppliesTo($validated['applies_to'] ?? null)) {
                $validated['applies_to'] = null;
            }
        }
        $validated['status'] = $sendMode === 'now' ? 'active' : 'draft';
        $validated['metadata'] = $meta;

        return $validated;
    }

    public function launch(Promotion $promotion): Promotion
    {
        $meta = $promotion->metadata ?? [];
        $filters = $meta['audience_filters'] ?? [];
        $reach = $this->audiences->estimate(is_array($filters) ? $filters : []);
        $channels = $meta['channels'] ?? ['in_app'];
        $inApp = in_array('in_app', $channels, true);
        $smsQueued = in_array('sms', $channels, true) || in_array('email', $channels, true) || in_array('whatsapp', $channels, true);

        $meta['estimated_reach'] = $reach;
        $meta['launched_at'] = now()->toIso8601String();
        $meta['results'] = [
            'reach' => $reach,
            'delivered' => $inApp ? $reach : 0,
            'opened' => 0,
            'clicked' => 0,
            'converted' => 0,
            'offers_claimed' => 0,
            'plus_joined' => 0,
            'sms_queued' => $smsQueued,
            'in_app_published' => $inApp,
            'note' => $smsQueued
                ? 'SMS/email/WhatsApp is queued against Settings Hub rules. This wizard does not blast live messages.'
                : 'In-app campaign is live. Fee discounts still use the existing PromotionService.',
        ];
        $promotion->metadata = $meta;
        $promotion->save();

        return $this->refreshResults($promotion->fresh());
    }

    public function refreshResults(Promotion $promotion): Promotion
    {
        $meta = $promotion->metadata ?? [];
        $results = $meta['results'] ?? [];
        $launched = $meta['launched_at'] ?? null;
        $since = $launched ? \Illuminate\Support\Carbon::parse($launched) : $promotion->created_at;

        if (($meta['intent'] ?? '') === 'encourage_plus' && class_exists(PlusSubscription::class)) {
            $results['plus_joined'] = PlusSubscription::query()
                ->where('created_at', '>=', $since)
                ->count();
            $results['converted'] = $results['plus_joined'];
        }

        $results['reach'] = (int) ($meta['estimated_reach'] ?? $results['reach'] ?? 0);
        $meta['results'] = $results;
        $promotion->metadata = $meta;
        $promotion->save();

        return $promotion->fresh();
    }

    public function compactReach(int $count): string
    {
        return MoneyFormat::compact($count);
    }

    private function typeForIntent(string $intent, ?string $requested): string
    {
        $allowed = [
            'birthday', 'registration_fee_discount', 'application_fee_discount', 'referral',
            'promo_code', 'seasonal', 'fee_discount', 'referral_bonus', 'membership_campaign',
        ];
        if ($requested && in_array($requested, $allowed, true)) {
            if ($intent === 'fee_promotion') {
                return $requested;
            }
        }

        return match ($intent) {
            'fee_promotion' => 'fee_discount',
            'referral' => 'referral',
            'affiliate' => 'seasonal',
            default => 'seasonal',
        };
    }

    /** @param  list<string>  $channels */
    private function shouldDeferForQuietHours(array $channels, string $sendMode): bool
    {
        if ($sendMode !== 'now') {
            return false;
        }
        $external = array_intersect($channels, ['sms', 'email', 'whatsapp']);
        if ($external === []) {
            return false;
        }
        $hour = (int) now('Africa/Dar_es_Salaam')->format('G');

        return $hour >= 21 || $hour < 7;
    }
}
