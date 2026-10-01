<?php

namespace App\Services\Support;

use App\Models\Customer;
use App\Models\SupportConversation;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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

    /** Named digital assistants — never presented as human employees. */
    public const PERSONAS = [
        ['key' => 'amani', 'name' => 'Amani'],
        ['key' => 'neema', 'name' => 'Neema'],
        ['key' => 'baraka', 'name' => 'Baraka'],
        ['key' => 'rehema', 'name' => 'Rehema'],
        ['key' => 'daniel', 'name' => 'Daniel'],
    ];

    public function __construct(
        private readonly SupportHelpLibraryService $help,
        private readonly SupportConversationService $conversations,
        private readonly SupportTicketService $tickets,
    ) {}

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

    /**
     * @return array{key: string, name: string}
     */
    public function personas(): array
    {
        return self::PERSONAS;
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
                ? "Je, {$name}, tatizo limetatuliwa?"
                : 'Je, tatizo limetatuliwa?';
        }

        return $name !== ''
            ? "{$name}, was the issue resolved?"
            : 'Was the issue resolved?';
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
        $conversation = $this->openAutomated($customer, $user, $guestName, $guestPhone);

        $meta = $this->meta($conversation);
        $meta['audience'] = $audience;
        $meta['workspace'] = $workspace;
        $meta['phase'] = 'category';
        $meta['tried_slugs'] = [];
        $meta['category_key'] = null;
        $meta['issue_slug'] = null;
        $meta['steps_attempted'] = $meta['steps_attempted'] ?? [];
        $persona = $this->ensurePersona($meta);
        $firstName = $this->resolveFirstName($customer, $guestFirstName, $guestName, $conversation);
        $meta['customer_first_name'] = $firstName;

        $hasBot = $conversation->messages()->where('is_automated', true)->exists()
            || $conversation->messages()->where('sender_type', 'bot')->exists();

        if (! $hasBot && $conversation->messages()->count() === 0) {
            $this->conversations->appendMessage(
                $conversation,
                'bot',
                $this->greeting($locale, $firstName, $persona['name']),
                null,
                true,
                false,
            );
            $meta['greeting_sent'] = true;
        }

        $this->persistState($conversation, self::STATE_WAITING_CUSTOMER, $meta);

        return $this->payload($conversation->fresh(['messages', 'assignedTo', 'tickets']) ?? $conversation, $audience, $locale, $workspace);
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
            'category' => $this->selectCategory($conversation, (string) ($input['key'] ?? ''), $audience, $workspace, $locale),
            'issue' => $this->selectIssue($conversation, (string) ($input['slug'] ?? ''), $customer, $user, $audience, $workspace, $locale),
            'resolved_yes' => $this->resolveAutomated($conversation, $locale),
            'no_other_issue' => $this->resolveAutomated($conversation, $locale, doneLabel: true),
            'resolved_no' => $this->continueOrEscalate($conversation, $customer, $user, $audience, $workspace, $locale),
            'escalate' => $this->escalateToHuman($conversation, $customer, $user, $input, $locale),
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
            if ($phase === 'category') {
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
                $choices = [
                    ['action' => 'resolved_yes', 'key' => 'yes', 'label' => $this->isSw($locale) ? 'Ndiyo' : 'Yes'],
                    ['action' => 'resolved_no', 'key' => 'no', 'label' => $this->isSw($locale) ? 'Hapana' : 'No'],
                ];
            } elseif ($phase === 'escalate_offer') {
                $choices = [
                    ['action' => 'escalate', 'key' => 'human', 'label' => $this->humanOfferLabel($locale)],
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
                ], true),
            'needs_human' => (bool) $conversation->needs_human,
            'ticket_number' => $ticket?->publicNumber(),
            'messages' => $this->conversations->serializeMessages($conversation),
            'automation' => true,
        ], $this->conversations->memberChatPresence(
            in_array($handling, [self::STATE_HUMAN, self::STATE_ESCALATED], true) ? $conversation : null
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
        $body = $this->formatArticleAnswer($article, $locale, $customer);

        $this->conversations->appendMessage($conversation, 'customer', $label, null, false, false);
        $this->conversations->appendMessage($conversation, 'bot', $body, null, true, false);

        $tried = array_values(array_unique(array_merge($meta['tried_slugs'] ?? [], [$slug])));
        $meta['tried_slugs'] = $tried;
        $meta['issue_slug'] = $slug;
        $meta['steps_attempted'][] = [
            'type' => 'issue',
            'category' => $categoryKey,
            'slug' => $slug,
            'creates_ticket' => (bool) ($article['creates_ticket'] ?? false),
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

        $this->conversations->appendMessage($conversation, 'bot', $this->resolvedPrompt($locale, (string) ($meta['customer_first_name'] ?? '') ?: null), null, true, false);
        $meta['phase'] = 'confirm';
        $this->persistState($conversation, self::STATE_WAITING_CUSTOMER, $meta);

        return $this->payload($conversation->fresh(['messages', 'tickets']) ?? $conversation, $audience, $locale, is_string($workspace) ? $workspace : null);
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
        $thanks = $this->closeResolvedCopy($locale, $firstName !== '' ? $firstName : null);
        $this->conversations->appendMessage($conversation, 'bot', $thanks, null, true, false);

        $conversation->update([
            'handling_state' => self::STATE_RESOLVED_AUTOMATED,
            'resolution_kind' => 'msaidizi',
            'automation_meta' => $meta,
            'status' => SupportConversationService::STATUS_CLOSED,
            'needs_human' => false,
            'resolved_at' => now(),
            'closed_at' => now(),
            'resolution_category' => 'msaidizi',
            'resolution_note' => 'Resolved by Msaidizi',
        ]);

        return $this->payload($conversation->fresh(['messages', 'tickets']) ?? $conversation, (string) ($meta['audience'] ?? 'member'), $locale, $meta['workspace'] ?? null);
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
        $guestName = trim((string) ($input['guest_name'] ?? $conversation->guest_name ?? ''));
        $guestPhone = trim((string) ($input['guest_phone'] ?? $conversation->guest_phone ?? ''));

        if (! $customer && ! $user) {
            if ($guestName === '' || $guestPhone === '') {
                throw new \InvalidArgumentException('Guest identity required to talk to support.');
            }
            $conversation->update([
                'guest_name' => $guestName,
                'guest_phone' => $guestPhone,
            ]);
        }

        $firstName = trim((string) ($input['guest_first_name'] ?? $meta['customer_first_name'] ?? ''));
        if ($firstName === '') {
            $firstName = $this->resolveFirstName($customer, null, $guestName, $conversation) ?? '';
        }
        $meta['customer_first_name'] = $firstName;

        $label = $this->humanOfferLabel($locale);
        $this->conversations->appendMessage($conversation, 'customer', $label, $user?->id, false, false);

        $handover = $this->handoverConfirmedCopy($locale, $firstName !== '' ? $firstName : null);
        $this->conversations->appendMessage($conversation, 'bot', $handover, null, true, false);

        $meta['phase'] = 'human';
        $meta['steps_attempted'][] = ['type' => 'escalate', 'at' => now()->toIso8601String()];
        $this->persistState($conversation, self::STATE_ESCALATED, $meta);

        $body = $this->isSw($locale)
            ? 'Nahitaji Ongea na mtoa huduma.'
            : 'I need to talk to a support agent.';

        $conversation = $this->conversations->requestHuman(
            $customer,
            $user,
            $body,
            $meta['issue_slug'] ?? $meta['category_key'] ?? 'Support escalation',
            $guestName !== '' ? $guestName : null,
            $guestPhone !== '' ? $guestPhone : null,
            'web_chat',
        );

        $conversation->update([
            'handling_state' => self::STATE_ESCALATED,
            'automation_meta' => $meta,
            'needs_human' => true,
        ]);

        return array_merge(
            $this->payload($conversation->fresh(['messages', 'tickets', 'assignedTo']) ?? $conversation, (string) ($meta['audience'] ?? 'member'), $locale, $meta['workspace'] ?? null),
            ['mode' => 'human', 'automation' => false]
        );
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
                self::STATE_RESOLVED_AUTOMATED, self::STATE_RESOLVED_SUPPORT => SupportConversationService::STATUS_CLOSED,
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
    private function formatArticleAnswer(array $article, ?string $locale, ?Customer $customer): string
    {
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
        if ($customer) {
            $ctx = [];
            $openApps = \App\Models\LoanApplication::query()
                ->where('customer_id', $customer->id)
                ->whereNotIn('status', ['withdrawn', 'rejected', 'closed', 'disbursed_closed'])
                ->latest('id')
                ->limit(2)
                ->get(['application_number', 'status', 'current_stage']);
            foreach ($openApps as $app) {
                $ctx[] = trim(($app->application_number ?: 'APP').' · '.$app->status.($app->current_stage ? ' / '.$app->current_stage : ''));
            }
            if ($ctx !== []) {
                $parts[] = $isSw
                    ? 'Akaunti yako (kwa marejeo tu, si uamuzi): '.implode('; ', $ctx)
                    : 'Your account (reference only, not a decision): '.implode('; ', $ctx);
            }
        }

        return trim(implode("\n\n", array_filter($parts)));
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

        $persona = self::PERSONAS[array_rand(self::PERSONAS)];
        $meta['persona_key'] = $persona['key'];
        $meta['persona_name'] = $persona['name'];

        return $persona;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{key: string, name: string}
     */
    private function personaFromMeta(array $meta): array
    {
        $key = (string) ($meta['persona_key'] ?? '');
        foreach (self::PERSONAS as $persona) {
            if ($persona['key'] === $key) {
                return $persona;
            }
        }

        return self::PERSONAS[0];
    }

    private function resolveFirstName(
        ?Customer $customer,
        ?string $guestFirstName,
        ?string $guestName,
        ?SupportConversation $conversation = null,
    ): ?string {
        $first = trim((string) $guestFirstName);
        if ($first !== '') {
            return $first;
        }

        if ($customer) {
            $fromCustomer = trim((string) ($customer->first_name ?? ''));
            if ($fromCustomer !== '') {
                return $fromCustomer;
            }
        }

        $full = trim((string) ($guestName ?: $conversation?->guest_name));
        if ($full !== '') {
            $parts = preg_split('/\s+/', $full) ?: [];

            return trim((string) ($parts[0] ?? '')) ?: null;
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
        $name = trim((string) $firstName);
        if ($this->isSw($locale)) {
            return $name !== ''
                ? "Sawa {$name}. Hili linahitaji msaada zaidi. Nitakupitisha kwa mmoja wa watoa huduma wetu ili akusaidie."
                : 'Sawa. Hili linahitaji msaada zaidi. Nitakupitisha kwa mmoja wa watoa huduma wetu ili akusaidie.';
        }

        return $name !== ''
            ? "Alright {$name}. This needs more help. I’ll connect you with one of our support agents."
            : 'Alright. This needs more help. I’ll connect you with one of our support agents.';
    }

    private function closeResolvedCopy(?string $locale, ?string $firstName): string
    {
        $name = trim((string) $firstName);
        if ($this->isSw($locale)) {
            return $name !== ''
                ? "Sawa {$name}, nimefurahi kusaidia."
                : 'Sawa, nimefurahi kusaidia.';
        }

        return $name !== ''
            ? "Alright {$name}, glad I could help."
            : 'Alright, glad I could help.';
    }
}
