<?php

namespace App\Services\Support;

use App\Models\Customer;
use App\Models\Setting;
use App\Models\SupportConversation;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
/**
 * Deterministic first-line support driven by SupportHelpLibraryService.
 * No external generative AI — answers come only from published Help content.
 */
class SupportAutomationService
{
    public const STATE_AUTOMATED = 'automated';

    public const STATE_WAITING_CUSTOMER = 'waiting_customer';

    public const STATE_ESCALATED = 'escalated';

    public const STATE_HUMAN = 'human_support';

    public const STATE_RESOLVED_AUTOMATED = 'resolved_automated';

    public const STATE_RESOLVED_SUPPORT = 'resolved_support';

    /** Fallback named digital assistants — Settings Hub overrides (configurable max). */
    public const PERSONAS = [
        ['key' => 'amani', 'name' => 'Amani'],
        ['key' => 'neema', 'name' => 'Neema'],
        ['key' => 'baraka', 'name' => 'Baraka'],
        ['key' => 'rehema', 'name' => 'Rehema'],
        ['key' => 'daniel', 'name' => 'Daniel'],
    ];

    public const PERSONAS_SETTING_KEY = 'support.msaidizi.personas';

    public const PERSONAS_MAX_SETTING_KEY = 'support.msaidizi.personas_max';

    public const PERSONAS_MAX_DEFAULT = 5;

    public const GUEST_CONVERSION_ENABLED_KEY = 'support.msaidizi.guest_conversion_enabled';

    public const GUEST_REPEAT_THRESHOLD_KEY = 'support.msaidizi.guest_repeat_threshold';

    public const GUEST_CONVERSION_COOLDOWN_HOURS_KEY = 'support.msaidizi.guest_conversion_cooldown_hours';

    public function __construct(
        private readonly SupportHelpLibraryService $help,
        private readonly SupportConversationService $conversations,
        private readonly SupportTicketService $tickets,
    ) {}

    public function personasMax(): int
    {
        $max = (int) Setting::get(self::PERSONAS_MAX_SETTING_KEY, self::PERSONAS_MAX_DEFAULT);

        return max(1, min(20, $max > 0 ? $max : self::PERSONAS_MAX_DEFAULT));
    }

    /**
     * Active automated persona names. Settings Hub is source of truth.
     *
     * @return list<array{key: string, name: string}>
     */
    public function personas(): array
    {
        $stored = Setting::get(self::PERSONAS_SETTING_KEY);
        $max = $this->personasMax();
        if (! is_array($stored) || $stored === []) {
            return array_slice(self::PERSONAS, 0, $max);
        }

        $out = [];
        foreach (array_slice(array_values($stored), 0, $max) as $i => $row) {
            if (is_string($row)) {
                $name = trim($row);
                $key = Str::slug($name) ?: ('persona_'.($i + 1));
            } elseif (is_array($row)) {
                $name = trim((string) ($row['name'] ?? ''));
                $key = trim((string) ($row['key'] ?? '')) ?: (Str::slug($name) ?: ('persona_'.($i + 1)));
            } else {
                continue;
            }
            if ($name === '') {
                continue;
            }
            $out[] = ['key' => $key, 'name' => $name];
        }

        return $out !== [] ? $out : array_slice(self::PERSONAS, 0, $max);
    }

    /**
     * @return list<array{key:string,label:string,icon:string}>
     */
    public function categoryChoices(string $audience = 'member', ?string $locale = null, ?string $workspace = null): array
    {
        return collect($this->help->categories($audience, $locale, $workspace))
            ->map(fn (array $c) => [
                'key' => (string) $c['key'],
                'label' => (string) $c['label'],
                'icon' => (string) ($c['icon'] ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{slug:string,label:string,creates_ticket:bool}>
     */
    public function issueChoices(string $categoryKey, string $audience = 'member', ?string $locale = null, ?string $workspace = null): array
    {
        $isSw = $this->isSw($locale);
        $group = $this->help->category($categoryKey, $audience, $workspace);
        if (! $group) {
            return [];
        }

        return collect($group['articles'] ?? [])
            ->map(function (array $a) use ($isSw) {
                $label = $isSw
                    ? (string) ($a['q_sw'] ?? $a['q_en'] ?? $a['title_sw'] ?? $a['title_en'] ?? '')
                    : (string) ($a['q_en'] ?? $a['q_sw'] ?? $a['title_en'] ?? $a['title_sw'] ?? '');

                return [
                    'slug' => (string) ($a['slug'] ?? ''),
                    'label' => $label,
                    'creates_ticket' => (bool) ($a['creates_ticket'] ?? false),
                ];
            })
            ->filter(fn (array $i) => $i['slug'] !== '' && $i['label'] !== '')
            ->values()
            ->all();
    }

    public function personaDisplayName(string $personaName, ?string $locale = null): string
    {
        return $this->isSw($locale)
            ? $personaName.' · Msaidizi wa Kopafasta'
            : $personaName.' · Kopafasta Assistant';
    }

    public function greeting(?string $locale = null, ?string $firstName = null, ?string $personaName = null): string
    {
        $personaName = $personaName ?: 'Amani';
        $name = trim((string) $firstName);
        $sw = $this->isSw($locale);

        if ($sw) {
            $variants = $name !== ''
                ? [
                    "Habari {$name}. Mimi ni {$personaName}, Msaidizi wa Kopafasta. Niambie unahitaji msaada gani leo.",
                    "Mambo {$name}. Mimi ni {$personaName}, Msaidizi wa Kopafasta. Niambie unahitaji msaada gani leo.",
                    "Habari {$name}, mimi ni {$personaName}, Msaidizi wa Kopafasta. Nipo hapa kukusaidia. Unahitaji msaada kuhusu nini?",
                ]
                : [
                    "Habari. Mimi ni {$personaName}, Msaidizi wa Kopafasta. Niambie unahitaji msaada gani leo.",
                    "Mambo. Mimi ni {$personaName}, Msaidizi wa Kopafasta. Niambie unahitaji msaada gani leo.",
                    "Habari, mimi ni {$personaName}, Msaidizi wa Kopafasta. Nipo hapa kukusaidia. Unahitaji msaada kuhusu nini?",
                ];
        } else {
            $variants = $name !== ''
                ? [
                    "Hello {$name}, I’m {$personaName}, Kopafasta Assistant. I’m here to help. What do you need help with?",
                    "Hi {$name}. I’m {$personaName}, Kopafasta Assistant. Tell me what you need help with today.",
                    "Hello {$name}. I’m {$personaName} · Kopafasta Assistant. How can I help you?",
                ]
                : [
                    "Hello, I’m {$personaName}, Kopafasta Assistant. I’m here to help. What do you need help with?",
                    "Hi. I’m {$personaName}, Kopafasta Assistant. Tell me what you need help with.",
                    "Hello. I’m {$personaName} · Kopafasta Assistant. How can I help you?",
                ];
        }

        return $variants[array_rand($variants)];
    }

    public function resolvedPrompt(?string $locale = null, ?string $firstName = null): string
    {
        $name = trim((string) $firstName);
        if ($this->isSw($locale)) {
            return $name !== ''
                ? "Je, {$name}, tatizo lako limetatuliwa?"
                : 'Je, tatizo lako limetatuliwa?';
        }

        return $name !== ''
            ? "{$name}, has your issue been resolved?"
            : 'Has your issue been resolved?';
    }

    public function humanOfferLabel(?string $locale = null): string
    {
        return $this->isSw($locale)
            ? 'Ongea na mtoa huduma'
            : 'Talk to a support agent';
    }

    /**
     * Start or resume an automated conversation. Does not queue for human yet.
     *
     * @return array<string, mixed>
     */
    public function start(
        ?Customer $customer,
        ?User $user,
        string $audience = 'member',
        ?string $guestName = null,
        ?string $guestPhone = null,
        ?string $workspace = null,
        ?string $locale = null,
        ?string $guestFirstName = null,
    ): array {
        // Member/Partner: always resume the single open CNV (including waiting/human) — never spawn a second bot CNV.
        if ($customer || $user) {
            $open = $this->conversations->reconcileOpenConversationsFor($customer, $user);
            $conversation = $open ?: $this->openAutomated($customer, $user, $guestName, $guestPhone);
        } else {
            $conversation = $this->openAutomated($customer, $user, $guestName, $guestPhone);
        }

        // Never restart Digital category flow on a human/waiting/accepted CNV.
        if ((bool) $conversation->needs_human
            || in_array((string) $conversation->handling_state, [
                self::STATE_ESCALATED,
                self::STATE_HUMAN,
                self::STATE_RESOLVED_SUPPORT,
            ], true)
            || filled($conversation->assigned_to)
            || in_array((string) $conversation->status, [
                SupportConversationService::STATUS_WAITING,
                SupportConversationService::STATUS_ASSIGNED,
            ], true)
        ) {
            $payload = $this->payload($conversation->fresh(['messages', 'assignedTo', 'tickets']) ?? $conversation, $audience, $locale, $workspace);
            $payload['mode'] = 'human';
            $payload['needs_human'] = true;
            $payload['choices'] = [];

            return $payload;
        }

        $meta = $this->meta($conversation);
        $meta['audience'] = $audience;
        $meta['workspace'] = $workspace;
        $isGuest = ! $customer && ! $user;
        $hasBot = $conversation->messages()->where('is_automated', true)->exists()
            || $conversation->messages()->where('sender_type', 'bot')->exists();
        $hasMessages = $conversation->messages()->count() > 0;

        // Fresh Digital CNV: greeting + category/audience choices. Resume mid-flow without wiping phase.
        if (! $hasMessages || empty($meta['phase'])) {
            $meta['phase'] = $isGuest ? 'audience_route' : 'category';
            $meta['tried_slugs'] = [];
            $meta['category_key'] = null;
            $meta['issue_slug'] = null;
        }
        $meta['steps_attempted'] = $meta['steps_attempted'] ?? [];
        $persona = $this->ensurePersona($meta);
        $firstName = $this->resolveFirstName($customer, $guestFirstName, $guestName, $conversation);
        $meta['customer_first_name'] = $this->conversations->safePersonFirstName($firstName);

        if (! $hasBot && ! $hasMessages) {
            $this->conversations->appendMessage(
                $conversation,
                'bot',
                $this->greeting($locale, $firstName, $persona['name']),
                null,
                true,
                false,
            );
            $meta['greeting_sent'] = true;
            if ($isGuest) {
                $this->conversations->appendMessage(
                    $conversation,
                    'bot',
                    $this->audienceRoutePrompt($locale, $firstName),
                    null,
                    true,
                    false,
                );
                $nudge = $this->maybeGuestRepeatConversionNudge($conversation, $guestPhone, $locale, $firstName, $meta);
                if ($nudge !== null) {
                    $this->conversations->appendMessage($conversation, 'bot', $nudge, null, true, false);
                    $meta['guest_repeat'] = true;
                    $meta['guest_cta_shown'] = true;
                    $meta['guest_conversion_nudged_at'] = now()->toIso8601String();
                }
            }
        }

        $this->persistState($conversation, self::STATE_WAITING_CUSTOMER, $meta);

        $payload = $this->payload($conversation->fresh(['messages', 'assignedTo', 'tickets']) ?? $conversation, $audience, $locale, $workspace);
        if ($isGuest && (bool) ($meta['guest_cta_shown'] ?? false)) {
            $payload = array_merge($payload, $this->guestJoinCtaPayload($locale, true));
        }

        return $payload;
    }

    /**
     * Compact Guest registration CTA payload for the chat surface.
     *
     * @return array{join_cta: array<string, mixed>, show_join_cta: bool}
     */
    public function guestJoinCtaPayload(?string $locale, bool $repeatNudge = false): array
    {
        $sw = $this->isSw($locale);

        return [
            'show_join_cta' => true,
            'join_cta' => [
                'title' => $sw ? 'Jiunge na familia ya Kopafasta' : 'Join the Kopafasta family',
                'body' => $repeatNudge
                    ? ($sw
                        ? 'Umekuwa nasi mara kadhaa. Fungua akaunti ili upate huduma zaidi na msaada unaohusiana na akaunti yako.'
                        : 'You’ve been with us a few times. Open an account for more services and account-specific help.')
                    : ($sw
                        ? 'Ili tukusaidie zaidi kuhusu hali yako binafsi, jiunge na Kopafasta au ingia kama tayari una akaunti.'
                        : 'To help further with your personal situation, join Kopafasta or sign in if you already have an account.'),
                'label' => $sw ? 'Anza Sasa' : 'Get started',
                'url' => route('site.register.borrower'),
                'key' => 'register',
                'secondary_label' => $sw ? 'Tayari nina akaunti — Ingia' : 'I already have an account — Sign in',
                'secondary_url' => route('site.login'),
                'secondary_key' => 'login',
                'prompt' => $sw
                    ? 'Jiunge na Kopafasta ili upate huduma zote kwenye akaunti yako.'
                    : 'Join Kopafasta to access every member service in one place.',
            ],
        ];
    }

    /**
     * Record Registration CTA click for Digital Assistant reporting (no invented registration attribution).
     *
     * @return array<string, mixed>
     */
    private function recordGuestCtaClick(SupportConversation $conversation, string $key): array
    {
        $meta = $this->meta($conversation);
        $meta['guest_cta_clicked'] = true;
        $meta['guest_cta_clicked_key'] = $key !== '' ? $key : 'register';
        $meta['guest_cta_clicked_at'] = now()->toIso8601String();
        $conversation->update(['automation_meta' => $meta]);

        return [
            'ok' => true,
            'conversation_id' => $conversation->id,
            'guest_cta_clicked' => true,
        ];
    }

    /**
     * Frequency-capped repeat-Guest conversion nudge (canonical phone identity).
     *
     * @param  array<string, mixed>  $meta
     */
    private function maybeGuestRepeatConversionNudge(
        SupportConversation $conversation,
        ?string $guestPhone,
        ?string $locale,
        ?string $firstName,
        array &$meta,
    ): ?string {
        if (! (bool) Setting::get(self::GUEST_CONVERSION_ENABLED_KEY, true)) {
            return null;
        }
        $phone = trim((string) $guestPhone);
        if ($phone === '') {
            return null;
        }

        $threshold = max(2, (int) Setting::get(self::GUEST_REPEAT_THRESHOLD_KEY, 3));
        $cooldownHours = max(1, (int) Setting::get(self::GUEST_CONVERSION_COOLDOWN_HOURS_KEY, 72));

        $visitCount = SupportConversation::query()
            ->whereNull('customer_id')
            ->whereNull('user_id')
            ->where('guest_phone', $phone)
            ->count();

        if ($visitCount < $threshold) {
            return null;
        }

        $meta['guest_visit_count'] = $visitCount;

        $recentNudge = SupportConversation::query()
            ->whereNull('customer_id')
            ->whereNull('user_id')
            ->where('guest_phone', $phone)
            ->where('automation_meta->guest_conversion_nudged_at', '!=', null)
            ->where('updated_at', '>=', now()->subHours($cooldownHours))
            ->exists();

        if ($recentNudge) {
            return null;
        }

        $sw = $this->isSw($locale);
        if ($sw) {
            return 'Umekuwa nasi mara kadhaa 😊 Jiunge na familia ya Kopafasta ili upate huduma zaidi na msaada unaohusiana moja kwa moja na akaunti yako.';
        }

        return 'You’ve visited us a few times 😊 Join the Kopafasta family for more help that connects directly to your account.';
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function step(
        SupportConversation $conversation,
        string $action,
        array $input = [],
        ?Customer $customer = null,
        ?User $user = null,
        ?string $locale = null,
    ): array {
        $meta = $this->meta($conversation);
        $audience = (string) ($meta['audience'] ?? 'member');
        $workspace = $meta['workspace'] ?? null;
        $locale = $locale ?? app()->getLocale();

        return match ($action) {
            'audience' => $this->selectAudience($conversation, (string) ($input['key'] ?? ''), $locale),
            'workspace' => $this->selectWorkspace($conversation, (string) ($input['key'] ?? ''), $locale),
            'category' => $this->selectCategory($conversation, (string) ($input['key'] ?? ''), $audience, $workspace, $locale),
            'issue' => $this->selectIssue($conversation, (string) ($input['slug'] ?? ''), $customer, $user, $audience, $workspace, $locale),
            'pick_record' => $this->selectRecord($conversation, (string) ($input['key'] ?? ''), $customer, $user, $audience, $workspace, $locale),
            'resolved_yes' => $this->resolveAutomated($conversation, $locale),
            'no_other_issue' => $this->resolveAutomated($conversation, $locale, doneLabel: true),
            'another_issue' => $this->restartIssuePath($conversation, $locale),
            'resolved_no' => $this->continueOrEscalate($conversation, $customer, $user, $audience, $workspace, $locale),
            'escalate' => $this->escalateToHuman($conversation, $customer, $user, $input, $locale),
            'cta_click' => $this->recordGuestCtaClick($conversation, (string) ($input['key'] ?? 'register')),
            'start' => $this->start(
                $customer,
                $user,
                $audience,
                $conversation->guest_name,
                $conversation->guest_phone,
                is_string($workspace) ? $workspace : null,
                $locale,
                isset($input['guest_first_name']) ? (string) $input['guest_first_name'] : null,
            ),
            default => throw new \InvalidArgumentException('Unknown automation action.'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(
        SupportConversation $conversation,
        ?string $audience = null,
        ?string $locale = null,
        ?string $workspace = null,
    ): array {
        $meta = $this->meta($conversation);
        $audience = $audience ?: (string) ($meta['audience'] ?? 'member');
        $workspace = $workspace ?? ($meta['workspace'] ?? null);
        $locale = $locale ?? app()->getLocale();
        $phase = (string) ($meta['phase'] ?? 'category');
        $handling = (string) ($conversation->handling_state ?: self::STATE_AUTOMATED);

        $choices = [];
        if ($handling === self::STATE_WAITING_CUSTOMER || $handling === self::STATE_AUTOMATED) {
            if ($phase === 'audience_route') {
                $choices = $this->audienceRouteChoices($locale);
            } elseif ($phase === 'workspace_route') {
                $choices = $this->workspaceRouteChoices($locale, $meta['workspace_options'] ?? null);
            } elseif ($phase === 'category') {
                $choices = collect($this->categoryChoices($audience, $locale, is_string($workspace) ? $workspace : null))
                    ->map(fn (array $c) => [
                        'action' => 'category',
                        'key' => $c['key'],
                        'label' => trim(($c['icon'] ? $c['icon'].' ' : '').$c['label']),
                    ])
                    ->all();
            } elseif ($phase === 'issue' && filled($meta['category_key'] ?? null)) {
                $choices = collect($this->issueChoices((string) $meta['category_key'], $audience, $locale, is_string($workspace) ? $workspace : null))
                    ->reject(fn (array $i) => in_array($i['slug'], $meta['tried_slugs'] ?? [], true))
                    ->map(fn (array $i) => [
                        'action' => 'issue',
                        'key' => $i['slug'],
                        'label' => $i['label'],
                    ])
                    ->values()
                    ->all();
            } elseif ($phase === 'confirm') {
                // Resolution template: Yes/No only — same engine for Member/Partner/Guest.
                $choices = [
                    ['action' => 'resolved_yes', 'key' => 'yes', 'label' => $this->isSw($locale) ? 'Ndiyo' : 'Yes'],
                    ['action' => 'resolved_no', 'key' => 'no', 'label' => $this->isSw($locale) ? 'Hapana' : 'No'],
                ];
            } elseif ($phase === 'escalate_offer') {
                $choices = [
                    ['action' => 'escalate', 'key' => 'human', 'label' => $this->humanOfferLabel($locale)],
                    ['action' => 'another_issue', 'key' => 'another', 'label' => $this->isSw($locale) ? 'Nina tatizo jingine' : 'I have another issue'],
                    ['action' => 'no_other_issue', 'key' => 'done', 'label' => $this->isSw($locale) ? 'Hakuna tatizo lingine' : 'No other issue'],
                    ['action' => 'category', 'key' => '__restart__', 'label' => $this->isSw($locale) ? 'Anza upya' : 'Start over'],
                ];
            }
        }

        $ticket = $conversation->tickets()->latest('id')->first();
        $persona = $this->personaFromMeta($meta);
        $firstName = (string) ($meta['customer_first_name'] ?? '');

        return array_merge([
            'ok' => true,
            'mode' => 'automation',
            'conversation_id' => $conversation->id,
            'conversation_number' => $conversation->publicNumber(),
            'handling_state' => $handling,
            'handling_label' => $this->customerFacingHandlingLabel($handling, $persona['name'], $locale),
            'persona_key' => $persona['key'],
            'persona_name' => $persona['name'],
            'persona_display' => $this->personaDisplayName($persona['name'], $locale),
            'customer_first_name' => $firstName,
            'phase' => $phase,
            'choices' => $choices,
            'composer_locked' => in_array($handling, [self::STATE_RESOLVED_AUTOMATED, self::STATE_RESOLVED_SUPPORT], true)
                || in_array((string) $conversation->status, [
                    SupportConversationService::STATUS_CLOSED,
                    SupportConversationService::STATUS_RESOLVED,
                ], true)
                || (
                    $handling === self::STATE_ESCALATED
                    && (bool) ($meta['waiting_followup_used'] ?? false)
                ),
            'needs_human' => (bool) $conversation->needs_human,
            'ticket_number' => $ticket?->publicNumber(),
            'messages' => $this->conversations->serializeMessages($conversation),
            'automation' => true,
            'show_rating' => $conversation->awaitsRating(),
            'rating_prompt' => $conversation->awaitsRating()
                ? ($this->isSw($locale)
                    ? "Uzoefu wako na {$persona['name']} ulikuwaje?"
                    : "How was your experience with {$persona['name']}?")
                : null,
        ], $this->conversations->memberChatPresence(
            in_array($handling, [self::STATE_HUMAN, self::STATE_ESCALATED], true) ? $conversation : null,
            $locale,
        ));
    }

    /**
     * Customer-facing desk label — never exposes “automated/bot” wording.
     */
    public function customerFacingHandlingLabel(string $state, string $personaName, ?string $locale = null): string
    {
        $sw = $this->isSw($locale);

        return match ($state) {
            self::STATE_AUTOMATED, self::STATE_WAITING_CUSTOMER => $this->personaDisplayName($personaName, $locale),
            self::STATE_ESCALATED => $sw ? 'Inasubiri mtoa huduma' : 'Waiting for support',
            self::STATE_HUMAN => $sw ? 'Mtoa huduma' : 'Human support',
            self::STATE_RESOLVED_AUTOMATED, self::STATE_RESOLVED_SUPPORT => $sw ? 'Imetatuliwa' : 'Resolved',
            default => $this->handlingLabel($state, $locale),
        };
    }

    public function handlingLabel(string $state, ?string $locale = null): string
    {
        $sw = $this->isSw($locale);

        // Internal/staff metadata labels (may mention automated).
        return match ($state) {
            self::STATE_AUTOMATED => $sw ? 'Otomatiki' : 'Automated',
            self::STATE_WAITING_CUSTOMER => $sw ? 'Inasubiri mteja' : 'Waiting for customer',
            self::STATE_ESCALATED => $sw ? 'Imepelekwa kwa mtoa huduma' : 'Escalated',
            self::STATE_HUMAN => $sw ? 'Mtoa huduma' : 'Human support',
            self::STATE_RESOLVED_AUTOMATED => $sw ? 'Imetatuliwa — Otomatiki' : 'Resolved — Automated',
            self::STATE_RESOLVED_SUPPORT => $sw ? 'Imetatuliwa — Usaidizi' : 'Resolved — Support',
            default => $state,
        };
    }

    /**
     * Guest conversation routing — not identity proof or account role assignment.
     *
     * @return array<string, mixed>
     */
    private function selectAudience(SupportConversation $conversation, string $key, ?string $locale): array
    {
        $meta = $this->meta($conversation);
        $key = strtolower(trim($key));
        if (! in_array($key, ['member', 'partner'], true)) {
            throw new \InvalidArgumentException('Unknown audience.');
        }

        $label = $key === 'partner'
            ? ($this->isSw($locale) ? 'Mimi ni Mshirika' : 'I am a Partner')
            : ($this->isSw($locale) ? 'Mimi ni Mkopaji / Mwanachama' : 'I am a Borrower / Member');
        $this->conversations->appendMessage($conversation, 'customer', $label, null, false, false);

        $meta['audience'] = $key;
        $meta['audience_routed'] = true;

        if ($key === 'partner') {
            // Guest Partner: pick service branch before categories (Affiliate / Supplier / …).
            $meta['workspace'] = null;
            $meta['workspace_options'] = $this->guestPartnerWorkspaceOptions($locale);
            $meta['phase'] = 'workspace_route';
            $firstName = (string) ($meta['customer_first_name'] ?? '');
            $this->conversations->appendMessage(
                $conversation,
                'bot',
                $this->workspaceRoutePrompt($locale, $firstName !== '' ? $firstName : null),
                null,
                true,
                false,
            );
            $this->persistState($conversation, self::STATE_WAITING_CUSTOMER, $meta);

            return $this->payload($conversation->fresh(['messages', 'tickets']) ?? $conversation, 'partner', $locale, null);
        }

        $meta['workspace'] = null;
        $meta['phase'] = 'category';
        $firstName = (string) ($meta['customer_first_name'] ?? '');
        $this->conversations->appendMessage(
            $conversation,
            'bot',
            $this->askCategoryPrompt($locale, $firstName !== '' ? $firstName : null),
            null,
            true,
            false,
        );
        $this->persistState($conversation, self::STATE_WAITING_CUSTOMER, $meta);

        return $this->payload($conversation->fresh(['messages', 'tickets']) ?? $conversation, 'member', $locale, null);
    }

    /**
     * @return array<string, mixed>
     */
    private function selectWorkspace(SupportConversation $conversation, string $key, ?string $locale): array
    {
        $meta = $this->meta($conversation);
        $key = strtolower(trim($key));
        $options = collect($meta['workspace_options'] ?? $this->guestPartnerWorkspaceOptions($locale));
        $match = $options->firstWhere('key', $key);
        if (! $match) {
            throw new \InvalidArgumentException('Unknown partner service.');
        }

        $this->conversations->appendMessage($conversation, 'customer', (string) $match['label'], null, false, false);
        $meta['audience'] = 'partner';
        $meta['workspace'] = $key;
        $meta['phase'] = 'category';
        $firstName = (string) ($meta['customer_first_name'] ?? '');
        $this->conversations->appendMessage(
            $conversation,
            'bot',
            $this->askCategoryPrompt($locale, $firstName !== '' ? $firstName : null),
            null,
            true,
            false,
        );
        $this->persistState($conversation, self::STATE_WAITING_CUSTOMER, $meta);

        return $this->payload($conversation->fresh(['messages', 'tickets']) ?? $conversation, 'partner', $locale, $key);
    }

    /**
     * @return array<string, mixed>
     */
    private function selectCategory(
        SupportConversation $conversation,
        string $key,
        string $audience,
        mixed $workspace,
        ?string $locale,
    ): array {
        $meta = $this->meta($conversation);

        if ($key === '__restart__') {
            $meta['phase'] = 'category';
            $meta['category_key'] = null;
            $meta['issue_slug'] = null;
            $meta['tried_slugs'] = [];
            $persona = $this->ensurePersona($meta);
            $firstName = (string) ($meta['customer_first_name'] ?? '');
            $this->conversations->appendMessage(
                $conversation,
                'bot',
                $this->greeting($locale, $firstName !== '' ? $firstName : null, $persona['name']),
                null,
                true,
                false,
            );
            $this->persistState($conversation, self::STATE_WAITING_CUSTOMER, $meta);

            return $this->payload($conversation->fresh(['messages', 'tickets']) ?? $conversation, $audience, $locale, is_string($workspace) ? $workspace : null);
        }

        $choices = $this->categoryChoices($audience, $locale, is_string($workspace) ? $workspace : null);
        $match = collect($choices)->firstWhere('key', $key);
        if (! $match) {
            throw new \InvalidArgumentException('Unknown category.');
        }

        $this->conversations->appendMessage($conversation, 'customer', (string) $match['label'], null, false, false);
        $meta['category_key'] = $key;
        $meta['issue_slug'] = null;
        $meta['phase'] = 'issue';
        $meta['steps_attempted'][] = ['type' => 'category', 'key' => $key, 'at' => now()->toIso8601String()];

        $firstName = (string) ($meta['customer_first_name'] ?? '');
        $prompt = $this->askIssuePrompt($locale, $firstName !== '' ? $firstName : null);
        $this->conversations->appendMessage($conversation, 'bot', $prompt, null, true, false);
        $this->persistState($conversation, self::STATE_WAITING_CUSTOMER, $meta);

        return $this->payload($conversation->fresh(['messages', 'tickets']) ?? $conversation, $audience, $locale, is_string($workspace) ? $workspace : null);
    }

    /**
     * @return array<string, mixed>
     */
    private function selectIssue(
        SupportConversation $conversation,
        string $slug,
        ?Customer $customer,
        ?User $user,
        string $audience,
        mixed $workspace,
        ?string $locale,
    ): array {
        $meta = $this->meta($conversation);
        $categoryKey = (string) ($meta['category_key'] ?? '');
        $article = $this->help->article($categoryKey, $slug, $audience, is_string($workspace) ? $workspace : null);
        if (! $article) {
            throw new \InvalidArgumentException('Unknown issue.');
        }

        $isSw = $this->isSw($locale);
        $label = $isSw
            ? (string) ($article['q_sw'] ?? $article['q_en'] ?? '')
            : (string) ($article['q_en'] ?? $article['q_sw'] ?? '');

        $diagnostic = null;
        if ($customer || ($audience === 'partner' && $user)) {
            $diagnostic = app(SupportAccountDiagnosticService::class)->diagnose(
                $customer,
                $user,
                $audience,
                $categoryKey,
                $slug,
                $locale,
                is_string($workspace) ? $workspace : null,
                isset($meta['selected_application_id']) ? (int) $meta['selected_application_id'] : null,
            );
        }

        if (is_array($diagnostic) && ! empty($diagnostic['choices']) && ($diagnostic['context']['kind'] ?? '') === 'member_pick_loan') {
            $this->conversations->appendMessage($conversation, 'customer', $label, null, false, false);
            $this->conversations->appendMessage(
                $conversation,
                'bot',
                $this->conversations->safeChatText($diagnostic['body'] ?? '', $locale),
                null,
                true,
                false,
            );
            $meta['phase'] = 'pick_record';
            $meta['pending_issue_slug'] = $slug;
            $meta['diagnostic_context'] = $diagnostic['context'] ?? null;
            $meta['steps_attempted'][] = [
                'type' => 'diagnostic_pick',
                'category' => $categoryKey,
                'slug' => $slug,
                'at' => now()->toIso8601String(),
            ];
            $this->persistState($conversation, self::STATE_WAITING_CUSTOMER, $meta);
            $payload = $this->payload($conversation->fresh(['messages', 'tickets']) ?? $conversation, $audience, $locale, is_string($workspace) ? $workspace : null);
            $payload['choices'] = $diagnostic['choices'];

            return $payload;
        }

        $body = is_array($diagnostic) && filled($diagnostic['body'] ?? null)
            ? $this->conversations->safeChatText($diagnostic['body'], $locale)
            : $this->formatArticleAnswer($article, $locale, $customer, $user, $audience, is_string($workspace) ? $workspace : null, $categoryKey, $slug);

        $this->conversations->appendMessage($conversation, 'customer', $label, null, false, false);
        $this->conversations->appendMessage($conversation, 'bot', $body, null, true, false);

        $tried = array_values(array_unique(array_merge($meta['tried_slugs'] ?? [], [$slug])));
        $meta['tried_slugs'] = $tried;
        $meta['issue_slug'] = $slug;
        if (is_array($diagnostic)) {
            $meta['diagnostic_context'] = $diagnostic['context'] ?? null;
        }
        $meta['steps_attempted'][] = [
            'type' => 'issue',
            'category' => $categoryKey,
            'slug' => $slug,
            'creates_ticket' => (bool) ($article['creates_ticket'] ?? false),
            'diagnostic' => (bool) $diagnostic,
            'at' => now()->toIso8601String(),
        ];

        if (! empty($article['creates_ticket'])) {
            if (! $customer && blank($conversation->guest_phone)) {
                $need = $this->isSw($locale)
                    ? 'Kabla ya kufungua tiketi ya uchunguzi, andika jina la kwanza, jina la mwisho na namba ya simu.'
                    : 'Before opening an investigation ticket, enter your first name, last name and phone.';
                $this->conversations->appendMessage($conversation, 'bot', $need, null, true, false);
                $meta['phase'] = 'confirm';
                $meta['pending_ticket'] = ['category' => $categoryKey, 'slug' => $slug, 'label' => $label];
                $this->persistState($conversation, self::STATE_WAITING_CUSTOMER, $meta);
                $payload = $this->payload($conversation->fresh(['messages', 'tickets']) ?? $conversation, $audience, $locale, is_string($workspace) ? $workspace : null);
                $payload['needs_guest'] = true;

                return $payload;
            }
            $ticket = $this->createInvestigationTicket($conversation, $customer, $user, $categoryKey, $slug, $label, $meta);
            $ack = $this->isSw($locale)
                ? "Tumepokea suala lako\nNamba ya kumbukumbu: {$ticket->publicNumber()}\nTimu yetu itaendelea na uchunguzi. Huhitaji kuanza mazungumzo mengine."
                : "We received your issue\nReference: {$ticket->publicNumber()}\nOur team will investigate. You do not need to start another chat.";
            $this->conversations->appendMessage($conversation, 'bot', $ack, null, true, false);
            $meta['phase'] = 'done';
            $meta['ticket_id'] = $ticket->id;
            $this->persistState($conversation, self::STATE_ESCALATED, $meta);
            $conversation->update([
                'needs_human' => true,
                'status' => SupportConversationService::STATUS_WAITING,
                'waiting_since' => $conversation->waiting_since ?: now(),
                'topic' => $label,
            ]);

            return $this->payload($conversation->fresh(['messages', 'tickets', 'assignedTo']) ?? $conversation, $audience, $locale, is_string($workspace) ? $workspace : null);
        }

        if (is_array($diagnostic) && ! empty($diagnostic['handover'])) {
            if ($audience === 'guest' || (! $customer && ! $user)) {
                return $this->guestHumanBoundary(
                    $conversation,
                    (string) ($conversation->guest_name ?? ''),
                    (string) ($conversation->guest_phone ?? ''),
                    $locale,
                    $meta,
                );
            }
            $offer = $this->isSw($locale)
                ? 'Ikiwa hali haiko wazi, Ongea na Usaidizi — tutaendelea na muktadha huu.'
                : 'If this state is unclear, Talk to Support — we will continue with this context.';
            $this->conversations->appendMessage($conversation, 'bot', $offer, null, true, false);
            $meta['phase'] = 'escalate_offer';
            $this->persistState($conversation, self::STATE_WAITING_CUSTOMER, $meta);
            $payload = $this->payload($conversation->fresh(['messages', 'tickets']) ?? $conversation, $audience, $locale, is_string($workspace) ? $workspace : null);
            if (! empty($diagnostic['cta_url'])) {
                $payload['cta'] = [
                    'url' => $diagnostic['cta_url'],
                    'label' => $diagnostic['cta_label'] ?? ($this->isSw($locale) ? 'Endelea' : 'Continue'),
                ];
            }

            return $payload;
        }

        $this->conversations->appendMessage($conversation, 'bot', $this->resolvedPrompt($locale, (string) ($meta['customer_first_name'] ?? '') ?: null), null, true, false);
        $meta['phase'] = 'confirm';
        $this->persistState($conversation, self::STATE_WAITING_CUSTOMER, $meta);

        $payload = $this->payload($conversation->fresh(['messages', 'tickets']) ?? $conversation, $audience, $locale, is_string($workspace) ? $workspace : null);
        if (is_array($diagnostic) && ! empty($diagnostic['cta_url'])) {
            $payload['cta'] = [
                'url' => $diagnostic['cta_url'],
                'label' => $diagnostic['cta_label'] ?? ($this->isSw($locale) ? 'Endelea' : 'Continue'),
            ];
        }

        return $payload;
    }

    /**
     * Authenticated Member picks a specific application/loan for diagnosis.
     *
     * @return array<string, mixed>
     */
    private function selectRecord(
        SupportConversation $conversation,
        string $key,
        ?Customer $customer,
        ?User $user,
        string $audience,
        mixed $workspace,
        ?string $locale,
    ): array {
        if (! $customer || ! str_starts_with($key, 'application:')) {
            throw new \InvalidArgumentException('Unknown record.');
        }

        $applicationId = (int) substr($key, strlen('application:'));
        $owns = \App\Models\LoanApplication::query()
            ->where('customer_id', $customer->id)
            ->whereKey($applicationId)
            ->exists();
        if (! $owns) {
            throw new \InvalidArgumentException('Unknown record.');
        }

        $meta = $this->meta($conversation);
        $meta['selected_application_id'] = $applicationId;
        $slug = (string) ($meta['pending_issue_slug'] ?? $meta['issue_slug'] ?? 'application-stage');
        $this->persistState($conversation, self::STATE_WAITING_CUSTOMER, $meta);

        $label = $this->isSw($locale) ? 'Ombi lililochaguliwa' : 'Selected application';
        $this->conversations->appendMessage($conversation, 'customer', $label, null, false, false);

        return $this->selectIssue(
            $conversation->fresh() ?? $conversation,
            $slug,
            $customer,
            $user,
            $audience,
            $workspace,
            $locale,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveAutomated(SupportConversation $conversation, ?string $locale, bool $doneLabel = false): array
    {
        $meta = $this->meta($conversation);
        $meta['phase'] = 'done';
        $customerLabel = $doneLabel
            ? ($this->isSw($locale) ? 'Hakuna tatizo lingine' : 'No other issue')
            : ($this->isSw($locale) ? 'Ndiyo' : 'Yes');
        $this->conversations->appendMessage($conversation, 'customer', $customerLabel, null, false, false);
        $firstName = (string) ($meta['customer_first_name'] ?? '');
        $isGuest = blank($conversation->customer_id) && blank($conversation->user_id);
        $thanks = $this->closeResolvedCopy($locale, $firstName !== '' ? $firstName : null, false);
        $this->conversations->appendMessage($conversation, 'bot', $thanks, null, true, false);

        if ($isGuest && filled($conversation->guest_phone)) {
            $parts = preg_split('/\s+/', trim((string) $conversation->guest_name), 2) ?: [];
            app(SupportGuestService::class)->touchGuest(
                (string) ($meta['customer_first_name'] ?? $parts[0] ?? 'Guest'),
                (string) ($parts[1] ?? ''),
                (string) $conversation->guest_phone,
                SupportGuestService::SOURCE_GUEST_CHAT,
            );
        }

        $conversation->update([
            'handling_state' => self::STATE_RESOLVED_AUTOMATED,
            'resolution_kind' => 'msaidizi',
            'automation_meta' => $meta,
            'status' => SupportConversationService::STATUS_RESOLVED,
            'needs_human' => false,
            'resolved_at' => now(),
            'closed_at' => now(),
            'resolution_category' => 'msaidizi',
            'resolution_note' => 'Resolved by Msaidizi',
            // Guest CSAT counts toward persona performance (same conversation + persona_key).
            'rating_requested_at' => now(),
        ]);

        $fresh = $conversation->fresh(['messages', 'tickets']) ?? $conversation;
        $payload = $this->payload($fresh, (string) ($meta['audience'] ?? 'member'), $locale, $meta['workspace'] ?? null);
        if ($fresh->awaitsRating()) {
            $persona = $this->personaFromMeta($meta);
            $sw = $this->isSw($locale);
            $payload['show_rating'] = true;
            $payload['rating_prompt'] = $sw
                ? "Uzoefu wako na {$persona['name']} ulikuwaje?"
                : "How was your experience with {$persona['name']}?";
            if ($isGuest && \Illuminate\Support\Facades\Route::has('site.support.chat.rate')) {
                $payload['rating_url'] = route('site.support.chat.rate', $fresh);
            } elseif ($fresh->customer_id && \Illuminate\Support\Facades\Route::has('site.borrower.support.conversation.rate')) {
                $payload['rating_url'] = route('site.borrower.support.conversation.rate', $fresh);
            } elseif ($fresh->user_id && \Illuminate\Support\Facades\Route::has('site.partner.support.conversation.rate')) {
                $payload['rating_url'] = route('site.partner.support.conversation.rate', $fresh);
            }
            $payload['composer_locked'] = true;
        }

        return $payload;
    }

    /**
     * Authenticated Member/Partner: start a new Category → Issue path inside the same open conversation.
     *
     * @return array<string, mixed>
     */
    private function restartIssuePath(SupportConversation $conversation, ?string $locale): array
    {
        $meta = $this->meta($conversation);
        $audience = (string) ($meta['audience'] ?? 'member');
        $workspace = $meta['workspace'] ?? null;
        $label = $this->isSw($locale) ? 'Nina tatizo jingine' : 'I have another issue';
        $this->conversations->appendMessage($conversation, 'customer', $label, null, false, false);

        $meta['phase'] = 'category';
        $meta['category_key'] = null;
        $meta['issue_slug'] = null;
        $meta['tried_slugs'] = [];
        $firstName = (string) ($meta['customer_first_name'] ?? '');
        $prompt = $this->isSw($locale)
            ? ($firstName !== ''
                ? "Sawa {$firstName}. Chagua mada mpya ya msaada."
                : 'Sawa. Chagua mada mpya ya msaada.')
            : ($firstName !== ''
                ? "Alright {$firstName}. Choose a new help topic."
                : 'Alright. Choose a new help topic.');
        $this->conversations->appendMessage($conversation, 'bot', $prompt, null, true, false);
        $this->persistState($conversation, self::STATE_WAITING_CUSTOMER, $meta);

        return $this->payload($conversation->fresh(['messages', 'tickets']) ?? $conversation, $audience, $locale, is_string($workspace) ? $workspace : null);
    }

    /**
     * @return array<string, mixed>
     */
    private function continueOrEscalate(
        SupportConversation $conversation,
        ?Customer $customer,
        ?User $user,
        string $audience,
        mixed $workspace,
        ?string $locale,
    ): array {
        $meta = $this->meta($conversation);
        $no = $this->isSw($locale) ? 'Hapana' : 'No';
        $this->conversations->appendMessage($conversation, 'customer', $no, null, false, false);

        $categoryKey = (string) ($meta['category_key'] ?? '');
        $remaining = collect($this->issueChoices($categoryKey, $audience, $locale, is_string($workspace) ? $workspace : null))
            ->reject(fn (array $i) => in_array($i['slug'], $meta['tried_slugs'] ?? [], true))
            ->values();

        $firstName = (string) ($meta['customer_first_name'] ?? '');

        if ($remaining->isNotEmpty()) {
            $prompt = $this->continuePathCopy($locale, $firstName !== '' ? $firstName : null);
            $this->conversations->appendMessage($conversation, 'bot', $prompt, null, true, false);
            $meta['phase'] = 'issue';
            $meta['issue_slug'] = null;
            $this->persistState($conversation, self::STATE_WAITING_CUSTOMER, $meta);

            return $this->payload($conversation->fresh(['messages', 'tickets']) ?? $conversation, $audience, $locale, is_string($workspace) ? $workspace : null);
        }

        // Guests never get a human escalate offer.
        if ($audience === 'guest' || (! $customer && ! $user)) {
            return $this->guestHumanBoundary(
                $conversation,
                (string) ($conversation->guest_name ?? ''),
                (string) ($conversation->guest_phone ?? ''),
                $locale,
                $meta,
            );
        }

        $offer = $this->handoverOfferCopy($locale, $firstName !== '' ? $firstName : null);
        $this->conversations->appendMessage($conversation, 'bot', $offer, null, true, false);
        $meta['phase'] = 'escalate_offer';
        $this->persistState($conversation, self::STATE_WAITING_CUSTOMER, $meta);

        return $this->payload($conversation->fresh(['messages', 'tickets']) ?? $conversation, $audience, $locale, is_string($workspace) ? $workspace : null);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function escalateToHuman(
        SupportConversation $conversation,
        ?Customer $customer,
        ?User $user,
        array $input,
        ?string $locale,
    ): array {
        $meta = $this->meta($conversation);
        $audience = (string) ($meta['audience'] ?? 'member');
        $guestName = trim((string) ($input['guest_name'] ?? $conversation->guest_name ?? ''));
        $guestPhone = trim((string) ($input['guest_phone'] ?? $conversation->guest_phone ?? ''));

        // Guests never enter the human Waiting queue — Digital Assistant + join/login only.
        if (! $customer && ! $user) {
            return $this->guestHumanBoundary($conversation, $guestName, $guestPhone, $locale, $meta);
        }

        $firstName = trim((string) ($input['guest_first_name'] ?? $meta['customer_first_name'] ?? ''));
        if ($firstName === '' || $this->conversations->looksLikeSerializedDump($firstName)) {
            $firstName = $this->resolveFirstName(
                $customer,
                isset($input['guest_first_name']) ? (string) $input['guest_first_name'] : null,
                $guestName,
                $conversation,
            ) ?? '';
        }
        $firstName = $this->conversations->safePersonFirstName($firstName);
        $meta['customer_first_name'] = $firstName;

        $label = $this->humanOfferLabel($locale);
        $this->conversations->appendMessage($conversation, 'customer', $label, $user?->id, false, false);

        $handover = $this->handoverConfirmedCopy($locale, $firstName !== '' ? $firstName : null);
        $this->conversations->appendMessage($conversation, 'bot', $handover, null, true, false);

        $meta['phase'] = 'human';
        $meta['steps_attempted'][] = [
            'type' => 'escalate',
            'diagnostic' => $meta['diagnostic_context'] ?? null,
            'at' => now()->toIso8601String(),
        ];
        // One optional customer follow-up allowed after handover, then composer locks.
        $meta['waiting_followup_allowed'] = true;
        $meta['waiting_followup_used'] = false;

        $diag = is_array($meta['diagnostic_context'] ?? null) ? $meta['diagnostic_context'] : [];
        $statusLabel = null;
        if (filled($diag['status_code'] ?? null)) {
            $code = (string) $diag['status_code'];
            $statusLabel = __('borrower.applications_list.statuses.'.$code, [], $this->isSw($locale) ? 'sw' : 'en');
            if ($statusLabel === 'borrower.applications_list.statuses.'.$code) {
                $statusLabel = $this->conversations->customerFacingStatusLabel($code, $locale);
            }
        }
        $diagBits = collect([
            isset($diag['application_number']) ? (string) $diag['application_number'] : null,
            $statusLabel,
        ])->filter()->implode(' · ');

        $topicLabel = $this->customerFacingTopicLabel(
            (string) ($meta['issue_slug'] ?? $meta['category_key'] ?? ''),
            $audience,
            $locale,
            is_string($meta['workspace'] ?? null) ? (string) $meta['workspace'] : null,
        );

        $body = $this->isSw($locale)
            ? 'Nahitaji Ongea na mtoa huduma.'.($diagBits !== '' ? "\nMuktadha: {$diagBits}" : '')
            : 'I need to talk to a support agent.'.($diagBits !== '' ? "\nContext: {$diagBits}" : '');

        // Persist escalated meta on THIS conversation first, then place it in Waiting in place.
        $conversation->update([
            'handling_state' => self::STATE_ESCALATED,
            'automation_meta' => $meta,
            'needs_human' => true,
            'topic' => $topicLabel !== '' ? $topicLabel : $conversation->topic,
        ]);

        $conversation = $this->conversations->placeInWaitingQueue(
            $conversation->fresh() ?? $conversation,
            $body,
            $topicLabel !== '' ? $topicLabel : ($this->isSw($locale) ? 'Usaidizi' : 'Support'),
            false,
        );

        // Re-stamp escalated handling + follow-up flags after placeInWaitingQueue (keeps same CNV).
        $meta = $this->meta($conversation);
        $meta['waiting_followup_allowed'] = true;
        $meta['waiting_followup_used'] = false;
        $meta['phase'] = 'human';
        $conversation->update([
            'handling_state' => self::STATE_ESCALATED,
            'automation_meta' => $meta,
            'needs_human' => true,
            'status' => SupportConversationService::STATUS_WAITING,
            'assigned_to' => null,
            'waiting_since' => now(),
        ]);

        $payload = $this->payload(
            $conversation->fresh(['messages', 'tickets', 'assignedTo']) ?? $conversation,
            $audience,
            $locale,
            $meta['workspace'] ?? null
        );

        return array_merge($payload, [
            'mode' => 'human',
            'automation' => false,
            'composer_locked' => false,
            'desk_label' => $this->isSw($locale) ? 'Inasubiri mtoa huduma' : 'Waiting for support',
        ]);
    }

    /**
     * Guest unresolved / Talk to Support: never enter human Waiting. Invite join/login.
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function guestHumanBoundary(
        SupportConversation $conversation,
        string $guestName,
        string $guestPhone,
        ?string $locale,
        array $meta,
    ): array {
        if ($guestName !== '' || $guestPhone !== '') {
            $conversation->update([
                'guest_name' => $guestName !== '' ? $guestName : $conversation->guest_name,
                'guest_phone' => $guestPhone !== '' ? $guestPhone : $conversation->guest_phone,
            ]);
        }

        $firstName = trim((string) ($meta['customer_first_name'] ?? ''));
        if ($firstName === '' && $guestName !== '') {
            $firstName = $this->resolveFirstName(null, null, $guestName, $conversation) ?? '';
        }

        $label = $this->humanOfferLabel($locale);
        $this->conversations->appendMessage($conversation, 'guest', $label, null, false, false);

        $sw = $this->isSw($locale);
        $msg = $sw
            ? 'Ili tukusaidie zaidi kuhusu hali yako binafsi, jiunge na Kopafasta au ingia kama tayari una akaunti.'
            : 'To help further with your personal situation, join Kopafasta or sign in if you already have an account.';
        $this->conversations->appendMessage($conversation, 'bot', $msg, null, true, false);

        $meta['phase'] = 'guest_join';
        $meta['guest_human_boundary_at'] = now()->toIso8601String();
        $meta['guest_cta_shown'] = true;
        // Stay automated — never needs_human / waiting for Guests.
        $this->persistState($conversation, self::STATE_WAITING_CUSTOMER, $meta);
        $conversation->update(['needs_human' => false, 'status' => SupportConversationService::STATUS_ACTIVE]);

        $payload = $this->payload(
            $conversation->fresh(['messages', 'tickets']) ?? $conversation,
            'guest',
            $locale,
            null
        );

        return array_merge($payload, [
            'mode' => 'automation',
            'automation' => true,
            'composer_locked' => false,
        ], $this->guestJoinCtaPayload($locale, false));
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function createInvestigationTicket(
        SupportConversation $conversation,
        ?Customer $customer,
        ?User $user,
        string $categoryKey,
        string $slug,
        string $label,
        array $meta,
    ): SupportTicket {
        $steps = collect($meta['steps_attempted'] ?? [])
            ->map(function ($s) {
                if (! is_array($s)) {
                    return null;
                }
                $type = (string) ($s['type'] ?? '');
                $key = (string) ($s['key'] ?? $s['slug'] ?? '');

                return trim($type.($key !== '' ? ':'.$key : ''));
            })
            ->filter()
            ->implode('; ');

        $description = "Automated investigation request\n"
            ."Category: {$categoryKey}\n"
            ."Issue: {$slug} — {$label}\n"
            .'Conversation: '.$conversation->publicNumber()."\n"
            .'Automated steps: '.($steps !== '' ? $steps : 'n/a')."\n"
            .'Do not invent payment status — investigate from payment records.';

        return $this->tickets->create([
            'customer_id' => $customer?->id,
            'contact_kind' => $customer ? 'customer' : 'guest',
            'guest_name' => $customer ? null : $conversation->guest_name,
            'guest_phone' => $customer ? null : $conversation->guest_phone,
            'source' => 'chatbot',
            'channel' => 'web_chat',
            'category' => $categoryKey === 'fees-payments' ? 'payments' : 'other',
            'subject' => $label,
            'description' => $description,
            'priority' => 'normal',
            'status' => 'open',
            'support_conversation_id' => $conversation->id,
            'created_by' => $user?->id,
        ]);
    }

    private function openAutomated(
        ?Customer $customer,
        ?User $user,
        ?string $guestName,
        ?string $guestPhone,
    ): SupportConversation {
        return DB::transaction(function () use ($customer, $user, $guestName, $guestPhone) {
            // Prefer resuming an open automated / waiting-customer thread; otherwise open new.
            $query = SupportConversation::query()
                ->whereNotIn('status', [
                    SupportConversationService::STATUS_CLOSED,
                    SupportConversationService::STATUS_RESOLVED,
                ])
                ->where(function ($q) {
                    $q->whereIn('handling_state', [
                        self::STATE_AUTOMATED,
                        self::STATE_WAITING_CUSTOMER,
                    ])->orWhereNull('handling_state');
                })
                ->where(function ($q) {
                    $q->where('needs_human', false)->orWhereNull('needs_human');
                })
                ->latest('id');

            if ($customer) {
                $query->where('customer_id', $customer->id);
            } elseif ($user) {
                $query->where('user_id', $user->id)->whereNull('customer_id');
            } elseif ($guestPhone) {
                $query->whereNull('customer_id')->whereNull('user_id')->where('guest_phone', $guestPhone);
            } else {
                // Anonymous public start — new conversation each session until identity known.
                $created = SupportConversation::query()->create([
                    'conversation_number' => $this->conversations->nextConversationNumber(),
                    'channel' => 'web_chat',
                    'status' => SupportConversationService::STATUS_ACTIVE,
                    'needs_human' => false,
                    'handling_state' => self::STATE_AUTOMATED,
                    'guest_name' => $guestName,
                    'guest_phone' => $guestPhone,
                    'last_message_at' => now(),
                ]);

                return $created;
            }

            $existing = $query->first();
            if ($existing && ! $existing->needs_human && ! in_array((string) $existing->handling_state, [self::STATE_ESCALATED, self::STATE_HUMAN], true)) {
                return $existing;
            }

            return SupportConversation::query()->create([
                'conversation_number' => $this->conversations->nextConversationNumber(),
                'customer_id' => $customer?->id,
                'user_id' => $user?->id,
                'channel' => 'web_chat',
                'status' => SupportConversationService::STATUS_ACTIVE,
                'needs_human' => false,
                'handling_state' => self::STATE_AUTOMATED,
                'guest_name' => $customer ? null : $guestName,
                'guest_phone' => $customer ? null : $guestPhone,
                'last_message_at' => now(),
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(SupportConversation $conversation): array
    {
        $raw = $conversation->automation_meta;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($raw) ? $raw : [];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function persistState(SupportConversation $conversation, string $state, array $meta): void
    {
        $conversation->update([
            'handling_state' => $state,
            'automation_meta' => $meta,
            'needs_human' => in_array($state, [self::STATE_ESCALATED, self::STATE_HUMAN], true),
            'status' => match ($state) {
                self::STATE_RESOLVED_AUTOMATED, self::STATE_RESOLVED_SUPPORT => SupportConversationService::STATUS_RESOLVED,
                self::STATE_ESCALATED => SupportConversationService::STATUS_WAITING,
                self::STATE_HUMAN => $conversation->assigned_to
                    ? SupportConversationService::STATUS_ASSIGNED
                    : SupportConversationService::STATUS_WAITING,
                default => SupportConversationService::STATUS_ACTIVE,
            },
            'last_message_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $article
     */
    private function formatArticleAnswer(
        array $article,
        ?string $locale,
        ?Customer $customer,
        ?User $user = null,
        string $audience = 'member',
        ?string $workspace = null,
        string $categoryKey = '',
        string $slug = '',
    ): string {
        $isSw = $this->isSw($locale);
        $body = $isSw
            ? (string) ($article['a_sw'] ?? $article['a_en'] ?? '')
            : (string) ($article['a_en'] ?? $article['a_sw'] ?? '');
        $steps = $isSw
            ? ($article['steps_sw'] ?? $article['steps_en'] ?? [])
            : ($article['steps_en'] ?? $article['steps_sw'] ?? []);

        $parts = [$body];
        if (is_array($steps) && $steps !== []) {
            $n = 1;
            foreach ($steps as $step) {
                $parts[] = $n.'. '.$step;
                $n++;
            }
        }

        // Safe account context — never invent eligibility/fees/payment status.
        // Never dump raw internal status codes into customer-visible text.
        if ($customer) {
            $ctx = [];
            $openApps = \App\Models\LoanApplication::query()
                ->where('customer_id', $customer->id)
                ->whereNotIn('status', ['withdrawn', 'rejected', 'closed', 'disbursed_closed'])
                ->latest('id')
                ->limit(2)
                ->get(['application_number', 'status', 'current_stage']);
            foreach ($openApps as $app) {
                $raw = (string) ($app->status ?: $app->current_stage ?: '');
                $statusLabel = $this->conversations->customerFacingStatusLabel($raw, $locale);
                try {
                    $borrower = app(\App\Services\ApplicationBorrowerStatusService::class)->forApplication($app);
                    $candidate = trim((string) ($borrower['label'] ?? ''));
                    // Reject raw snake_case / slash-joined internal codes leaking into chat.
                    if ($candidate !== ''
                        && ! preg_match('/^[a-z0-9_]+(\s*\/\s*[a-z0-9_]+)?$/i', $candidate)
                        && ! str_contains($candidate, '_')
                    ) {
                        $statusLabel = $candidate;
                    }
                } catch (\Throwable) {
                }
                $ctx[] = trim(($app->application_number ?: 'APP').' · '.$statusLabel);
            }
            if ($ctx !== []) {
                $parts[] = $isSw
                    ? 'Akaunti yako (kwa marejeo tu, si uamuzi): '.implode('; ', $ctx)
                    : 'Your account (reference only, not a decision): '.implode('; ', $ctx);
            }
        }

        $personal = $this->personalCommercialContext($user, $audience, $workspace, $categoryKey, $slug, $locale);
        if ($personal !== null && $personal !== '') {
            $parts[] = $personal;
        }

        return trim(implode("\n\n", array_filter($parts)));
    }

    /**
     * Partner-specific commercial line from authoritative services — never invent rates.
     */
    private function personalCommercialContext(
        ?User $user,
        string $audience,
        ?string $workspace,
        string $categoryKey,
        string $slug,
        ?string $locale,
    ): ?string {
        if ($audience !== 'partner' || ! $user) {
            return null;
        }

        $partner = $user->partner ?? null;
        if (! $partner) {
            return null;
        }

        $isSw = $this->isSw($locale);
        $commercialSlugs = [
            'affiliate-commission', 'affiliate-earnings', 'affiliate-referrals',
            'supplier-earnings', 'supplier-markup', 'supplier-commission', 'supplier-deposit',
        ];
        $looksCommercial = in_array($slug, $commercialSlugs, true)
            || str_contains($slug, 'commission')
            || str_contains($slug, 'earnings')
            || str_contains($slug, 'markup')
            || str_contains($categoryKey, 'affiliate')
            || str_contains($categoryKey, 'supplier');

        if (! $looksCommercial) {
            return null;
        }

        try {
            if (($workspace === 'affiliate' || $categoryKey === 'affiliate') && method_exists($partner, 'isAffiliate') && $partner->isAffiliate()) {
                $pct = app(\App\Services\AffiliateService::class)->commissionPercent($partner);
                $tier = method_exists($partner, 'isPremiumAffiliate') && $partner->isPremiumAffiliate()
                    ? ($isSw ? 'Premium' : 'Premium')
                    : ($isSw ? 'Standard' : 'Standard');
                if ($pct > 0) {
                    return $isSw
                        ? "Akaunti yako ({$tier}): kiwango chako cha sasa cha kamisheni ni {$pct}% kulingana na usanidi/makubaliano yako."
                        : "Your account ({$tier}): your current commission rate is {$pct}% per your configuration/agreement.";
                }

                return $isSw
                    ? 'Kamisheni yako inafuata usanidi wa Affiliate kwenye akaunti yako. Fungua nafasi ya Affiliate kuona maelezo, au Ongea na Usaidizi ikiwa haionekani.'
                    : 'Your commission follows the Affiliate configuration on your account. Open your Affiliate workspace for details, or Talk to Support if it is missing.';
            }

            if (($workspace === 'supplier' || $categoryKey === 'supplier') && method_exists($partner, 'isSupplier') && $partner->isSupplier()) {
                $lending = app(\App\Services\AssetLendingService::class);
                $markup = $lending->defaultDepositMarkupPercent();
                if ($markup > 0) {
                    return $isSw
                        ? "Kulingana na usanidi wa soko wa sasa, asilimia ya markup ya amana ni {$markup}%. Fungua nafasi ya Msambazaji kwa oda na malipo yako."
                        : "Per current marketplace configuration, the default deposit markup percent is {$markup}%. Open your Supplier workspace for your orders and payments.";
                }
            }
        } catch (\Throwable) {
            return $isSw
                ? 'Hatuwezi kuthibitisha kiwango cha kibiashara sasa. Fuata maelezo yaliyo kwenye nafasi yako ya Mshirika, au Ongea na Usaidizi.'
                : 'We cannot confirm the commercial rate right now. Follow the details in your Partner workspace, or Talk to Support.';
        }

        return null;
    }

    private function audienceRoutePrompt(?string $locale, ?string $firstName): string
    {
        $name = trim((string) $firstName);
        if ($this->isSw($locale)) {
            return $name !== ''
                ? "{$name}, tunawezaje kukusaidia? Chagua chini."
                : 'Tunawezaje kukusaidia? Chagua chini.';
        }

        return $name !== ''
            ? "{$name}, how can we help you? Choose below."
            : 'How can we help you? Choose below.';
    }

    /**
     * @return list<array{action: string, key: string, label: string}>
     */
    private function audienceRouteChoices(?string $locale): array
    {
        if ($this->isSw($locale)) {
            return [
                ['action' => 'audience', 'key' => 'member', 'label' => 'Mimi ni Mkopaji / Mwanachama'],
                ['action' => 'audience', 'key' => 'partner', 'label' => 'Mimi ni Mshirika'],
            ];
        }

        return [
            ['action' => 'audience', 'key' => 'member', 'label' => 'I am a Borrower / Member'],
            ['action' => 'audience', 'key' => 'partner', 'label' => 'I am a Partner'],
        ];
    }

    private function workspaceRoutePrompt(?string $locale, ?string $firstName): string
    {
        $name = trim((string) $firstName);
        if ($this->isSw($locale)) {
            return $name !== ''
                ? "Sawa {$name}. Unahitaji msaada wa huduma gani ya Ushirika?"
                : 'Sawa. Unahitaji msaada wa huduma gani ya Ushirika?';
        }

        return $name !== ''
            ? "Alright {$name}. Which Partner service do you need help with?"
            : 'Alright. Which Partner service do you need help with?';
    }

    /**
     * @param  list<array{key: string, label: string}>|null  $options
     * @return list<array{action: string, key: string, label: string}>
     */
    private function workspaceRouteChoices(?string $locale, ?array $options): array
    {
        $rows = $options ?: $this->guestPartnerWorkspaceOptions($locale);

        return collect($rows)
            ->map(fn (array $r) => [
                'action' => 'workspace',
                'key' => (string) $r['key'],
                'label' => (string) $r['label'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function guestPartnerWorkspaceOptions(?string $locale): array
    {
        if ($this->isSw($locale)) {
            return [
                ['key' => 'affiliate', 'label' => 'Affiliate'],
                ['key' => 'supplier', 'label' => 'Msambazaji wa mali'],
                ['key' => 'insurance', 'label' => 'Bima'],
                ['key' => 'recovery', 'label' => 'Urejesho'],
                ['key' => 'valuer', 'label' => 'Mthamini'],
                ['key' => 'capital', 'label' => 'Mtaji'],
                ['key' => 'service', 'label' => 'Huduma nyingine ya Mshirika'],
            ];
        }

        return [
            ['key' => 'affiliate', 'label' => 'Affiliate'],
            ['key' => 'supplier', 'label' => 'Asset Supplier'],
            ['key' => 'insurance', 'label' => 'Insurance Partner'],
            ['key' => 'recovery', 'label' => 'Recovery Partner'],
            ['key' => 'valuer', 'label' => 'Valuer'],
            ['key' => 'capital', 'label' => 'Capital Partner'],
            ['key' => 'service', 'label' => 'Other Partner service'],
        ];
    }

    private function askCategoryPrompt(?string $locale, ?string $firstName): string
    {
        $name = trim((string) $firstName);
        if ($this->isSw($locale)) {
            return $name !== ''
                ? "Sawa {$name}. Chagua mada ya msaada."
                : 'Sawa. Chagua mada ya msaada.';
        }

        return $name !== ''
            ? "Alright {$name}. Choose a help topic."
            : 'Alright. Choose a help topic.';
    }

    private function isSw(?string $locale): bool
    {
        $locale = $locale ?? app()->getLocale();

        return str_starts_with(strtolower((string) $locale), 'sw');
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{key: string, name: string}
     */
    private function ensurePersona(array &$meta): array
    {
        $existing = $this->personaFromMeta($meta);
        if (($meta['persona_key'] ?? null) === $existing['key'] && filled($meta['persona_name'] ?? null)) {
            return $existing;
        }

        $pool = $this->personas();
        $rrKey = 'support.msaidizi.persona_rr';
        $idx = (int) Setting::get($rrKey, 0);
        if ($idx < 0) {
            $idx = 0;
        }
        $persona = $pool[$idx % count($pool)];
        Setting::set($rrKey, ($idx + 1) % max(1, count($pool)));
        $meta['persona_key'] = $persona['key'];
        $meta['persona_name'] = $persona['name'];

        return $persona;
    }

    /**
     * Localized display topic for lists/headers — never raw slugs like cannot-login.
     */
    public function customerFacingTopicLabel(
        string $keyOrSlug,
        string $audience = 'member',
        ?string $locale = null,
        ?string $workspace = null,
    ): string {
        $keyOrSlug = trim($keyOrSlug);
        if ($keyOrSlug === '') {
            return $this->isSw($locale) ? 'Suala la msaada' : 'Support issue';
        }

        $sw = $this->isSw($locale);
        foreach ($this->help->groups($audience, $workspace) as $cat) {
            if (($cat['key'] ?? '') === $keyOrSlug) {
                return (string) ($sw
                    ? ($cat['label_sw'] ?? $cat['label_en'] ?? $keyOrSlug)
                    : ($cat['label_en'] ?? $cat['label_sw'] ?? $keyOrSlug));
            }
            foreach ($cat['articles'] ?? [] as $article) {
                if (($article['slug'] ?? '') === $keyOrSlug) {
                    return (string) ($sw
                        ? ($article['q_sw'] ?? $article['title_sw'] ?? $article['q_en'] ?? $article['title_en'] ?? $keyOrSlug)
                        : ($article['q_en'] ?? $article['title_en'] ?? $article['q_sw'] ?? $article['title_sw'] ?? $keyOrSlug));
                }
            }
        }

        // Already a human sentence / product name — keep; never echo snake_case keys.
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)+$/', $keyOrSlug) && ! preg_match('/^[a-z0-9_]+$/', $keyOrSlug)) {
            return $keyOrSlug;
        }

        return $sw ? 'Suala la msaada' : 'Support issue';
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{key: string, name: string}
     */
    private function personaFromMeta(array $meta): array
    {
        $key = (string) ($meta['persona_key'] ?? '');
        $pool = $this->personas();
        foreach ($pool as $persona) {
            if ($persona['key'] === $key) {
                return $persona;
            }
        }

        return $pool[0];
    }

    private function resolveFirstName(
        ?Customer $customer,
        ?string $guestFirstName,
        ?string $guestName,
        ?SupportConversation $conversation = null,
    ): ?string {
        // Never accept Eloquent/JSON dumps coerced through Stringable into a "name".
        $first = $this->conversations->safePersonFirstName($guestFirstName);
        if ($first !== '') {
            return $first;
        }

        if ($customer) {
            $fromCustomer = $this->conversations->safePersonFirstName($customer->first_name ?? '');
            if ($fromCustomer !== '') {
                return $fromCustomer;
            }
        }

        $full = trim((string) ($guestName ?: $conversation?->guest_name));
        if ($full !== '' && ! $this->conversations->looksLikeSerializedDump($full)) {
            $parts = preg_split('/\s+/', $full) ?: [];

            return $this->conversations->safePersonFirstName($parts[0] ?? '') ?: null;
        }

        return null;
    }

    private function askIssuePrompt(?string $locale, ?string $firstName): string
    {
        $name = trim((string) $firstName);
        if ($this->isSw($locale)) {
            $variants = $name !== ''
                ? [
                    "Sawa {$name}. Tatizo lako ni lipi hasa?",
                    "Asante {$name}. Chagua suala unalotaka msaada nalo:",
                    "Nimeelewa {$name}. Unahitaji msaada kuhusu nini kati ya hivi?",
                ]
                : [
                    'Sawa. Tatizo lako ni lipi hasa?',
                    'Asante. Chagua suala unalotaka msaada nalo:',
                    'Nimeelewa. Unahitaji msaada kuhusu nini kati ya hivi?',
                ];
        } else {
            $variants = $name !== ''
                ? [
                    "Got it {$name}. What is your issue?",
                    "Thanks {$name}. Choose the issue you need help with:",
                    "Understood {$name}. Which of these do you need help with?",
                ]
                : [
                    'Got it. What is your issue?',
                    'Thanks. Choose the issue you need help with:',
                    'Understood. Which of these do you need help with?',
                ];
        }

        return $variants[array_rand($variants)];
    }

    private function continuePathCopy(?string $locale, ?string $firstName): string
    {
        $name = trim((string) $firstName);
        if ($this->isSw($locale)) {
            return $name !== ''
                ? "Sawa {$name}. Hebu tujaribu njia nyingine inayohusiana. Chagua tatizo linalofuata:"
                : 'Sawa. Hebu tujaribu njia nyingine inayohusiana. Chagua tatizo linalofuata:';
        }

        return $name !== ''
            ? "Understood {$name}. Let’s try another related path. Choose the next issue:"
            : 'Understood. Let’s try another related path. Choose the next issue:';
    }

    private function handoverOfferCopy(?string $locale, ?string $firstName): string
    {
        $name = trim((string) $firstName);
        if ($this->isSw($locale)) {
            return $name !== ''
                ? "Sawa {$name}. Hili linahitaji msaada zaidi. Unaweza Ongea na mtoa huduma — ataona historia yote."
                : 'Sawa. Hili linahitaji msaada zaidi. Unaweza Ongea na mtoa huduma — ataona historia yote.';
        }

        return $name !== ''
            ? "Alright {$name}. This needs a bit more help. You can talk to a support agent — they will see the full history."
            : 'Alright. This needs a bit more help. You can talk to a support agent — they will see the full history.';
    }

    private function handoverConfirmedCopy(?string $locale, ?string $firstName): string
    {
        if ($this->isSw($locale)) {
            return 'Tunatafuta mtoa huduma anayefaa kukusaidia. Tafadhali subiri kidogo.';
        }

        return "We're finding the right support agent for your case. Please wait a moment.";
    }

    private function closeResolvedCopy(?string $locale, ?string $firstName, bool $guestConversion = false): string
    {
        $name = trim((string) $firstName);
        $sw = $this->isSw($locale);

        if ($guestConversion) {
            return $this->guestConversionClosing($locale, $name !== '' ? $name : null);
        }

        if ($sw) {
            return $name !== ''
                ? "Sawa {$name}, nimefurahi kusaidia."
                : 'Sawa, nimefurahi kusaidia.';
        }

        return $name !== ''
            ? "Alright {$name}, glad I could help."
            : 'Alright, glad I could help.';
    }

    /**
     * Guest-only post-resolve invitation. Settings Hub is source of truth (up to 5 SW/EN variants).
     */
    public function guestConversionClosing(?string $locale, ?string $firstName): string
    {
        $name = trim((string) $firstName);
        $sw = $this->isSw($locale);
        $variants = $this->guestConversionVariants($locale);
        $template = $variants[array_rand($variants)];

        return str_replace(['{name}', '{Name}'], [$name !== '' ? $name : ($sw ? 'rafiki' : 'friend'), $name], $template);
    }

    /**
     * @return list<string>
     */
    public function guestConversionVariants(?string $locale = null): array
    {
        $sw = $this->isSw($locale);
        $stored = Setting::get('support.msaidizi.guest_conversion_closings');
        $key = $sw ? 'sw' : 'en';
        if (is_array($stored) && ! empty($stored[$key]) && is_array($stored[$key])) {
            $lines = array_values(array_filter(array_map(
                fn ($line) => trim((string) $line),
                array_slice($stored[$key], 0, 5)
            )));
            if ($lines !== []) {
                return $lines;
            }
        }

        return $this->defaultGuestConversionVariants($sw);
    }

    /**
     * @return list<string>
     */
    public function defaultGuestConversionVariants(bool $sw): array
    {
        if ($sw) {
            return [
                'Nimefurahi kukusaidia, {name}. Ukiwa tayari, jiunge na Kopafasta ili upate huduma zote moja kwa moja kwenye akaunti yako.',
                'Asante {name}. Unaweza pia kufungua akaunti ya Kopafasta na kupata huduma zote za mwanachama sehemu moja.',
                'Sawa {name}, nimefurahi kusaidia. Jiunge na Kopafasta ukiwa tayari — utapata mikopo, malipo na msaada kwenye akaunti yako.',
                'Asante kwa kuwasiliana nasi, {name}. Fungua akaunti ya Kopafasta ili uendelee kwa urahisi kila unapohitaji.',
                'Nimefurahi kukusaidia. {name}, Anza Sasa ujisajili na upate huduma zote za Kopafasta katika akaunti moja.',
            ];
        }

        return [
            'Glad I could help, {name}. When you are ready, join Kopafasta to access every service directly in your account.',
            'Thank you, {name}. You can also open a Kopafasta account and find every member service in one place.',
            'Alright {name}, glad I could help. Join Kopafasta when you are ready — loans, repayments and support live in your account.',
            'Thanks for reaching out, {name}. Open a Kopafasta account so help stays easy whenever you need it.',
            'Glad I could help. {name}, Get started and register to access every Kopafasta service in one account.',
        ];
    }
}
