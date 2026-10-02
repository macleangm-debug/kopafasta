<?php

namespace App\Services\Support;

use App\Models\Customer;
use App\Models\Setting;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Member/guest ↔ Support conversation engine (reuses support_conversations / support_messages).
 *
 * Operational states: Waiting → Assigned → Active → Resolved.
 */
class SupportConversationService
{
    public const STATUS_WAITING = 'waiting';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const CONVERSATION_PREFIX_KEY = 'support.conversation_number_prefix';

    public const CONVERSATION_PREFIX_DEFAULT = 'KPF-CNV';

    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /**
     * Settings-backed readable conversation number, e.g. KPF-CNV-A7K4Q2.
     * New references are alphanumeric; historical sequential numbers are unchanged.
     */
    public function nextConversationNumber(?string $explicit = null): string
    {
        $explicit = trim((string) $explicit);
        if ($explicit !== '') {
            return $explicit;
        }

        $prefix = strtoupper(trim((string) Setting::get(self::CONVERSATION_PREFIX_KEY, self::CONVERSATION_PREFIX_DEFAULT)));
        if ($prefix === '') {
            $prefix = self::CONVERSATION_PREFIX_DEFAULT;
        }

        return app(\App\Services\ReferenceNumberService::class)->prefixedReference(
            $prefix,
            6,
            fn (string $candidate) => SupportConversation::query()->where('conversation_number', $candidate)->exists(),
        );
    }

    /**
     * Speak to Support: create or resume open conversation and post the member message.
     * Does not invent an agent name until Accept/assign.
     */
    public function requestHuman(
        ?Customer $customer,
        ?User $user,
        string $body,
        ?string $topic = null,
        ?string $guestName = null,
        ?string $guestPhone = null,
        ?string $channel = 'web_chat',
    ): SupportConversation {
        $body = trim($body);
        if ($body === '') {
            throw new \InvalidArgumentException('Message body is required.');
        }

        return DB::transaction(function () use ($customer, $user, $body, $topic, $guestName, $guestPhone, $channel) {
            $conversation = $this->openConversationFor($customer, $user, $guestName, $guestPhone, $channel);
            $conversation = $this->ensureAlphanumericReference($conversation);

            $meta = is_array($conversation->automation_meta) ? $conversation->automation_meta : [];
            $isWaitingHuman = (bool) $conversation->needs_human
                && in_array((string) $conversation->status, [self::STATUS_WAITING], true)
                && ! $conversation->assigned_to;

            // After handover: at most ONE optional customer follow-up, then lock.
            if ($isWaitingHuman && (bool) ($meta['waiting_followup_used'] ?? false)) {
                throw new \InvalidArgumentException('composer_locked');
            }

            // Lightweight anti-spam: rapid duplicate / burst sends stay in the same
            // conversation but do not append another customer message.
            $throttleKey = 'support.wait.msg:'.$conversation->id;
            if (RateLimiter::tooManyAttempts($throttleKey, 8)) {
                return $conversation->fresh(['customer', 'user', 'messages', 'assignedTo']) ?? $conversation;
            }
            $lastCustomer = $conversation->messages()
                ->whereIn('sender_type', ['customer', 'guest'])
                ->latest('id')
                ->first();
            if ($lastCustomer
                && trim((string) $lastCustomer->body) === $body
                && $lastCustomer->created_at
                && $lastCustomer->created_at->gt(now()->subSeconds(8))
            ) {
                return $conversation->fresh(['customer', 'user', 'messages', 'assignedTo']) ?? $conversation;
            }
            RateLimiter::hit($throttleKey, 60);

            $enteringWaiting = ! ($conversation->assigned_to && in_array($conversation->status, [self::STATUS_ASSIGNED, self::STATUS_ACTIVE], true));
            $freshHandoverClock = $enteringWaiting && (
                ! $conversation->needs_human
                || ! in_array((string) $conversation->status, [self::STATUS_WAITING], true)
                || ! $conversation->waiting_since
            );

            $conversation->update([
                'needs_human' => true,
                // Fresh member contact never inherits a historical agent claim while waiting.
                'assigned_to' => $conversation->assigned_to && in_array($conversation->status, [self::STATUS_ASSIGNED, self::STATUS_ACTIVE], true)
                    ? $conversation->assigned_to
                    : null,
                'status' => ($conversation->assigned_to && in_array($conversation->status, [self::STATUS_ASSIGNED, self::STATUS_ACTIVE], true))
                    ? self::STATUS_ASSIGNED
                    : self::STATUS_WAITING,
                'topic' => $topic ?: $conversation->topic,
                'channel' => $channel ?: ($conversation->channel ?: 'web_chat'),
                'last_message_at' => now(),
                'waiting_since' => ($conversation->assigned_to && in_array($conversation->status, [self::STATUS_ASSIGNED, self::STATUS_ACTIVE], true))
                    ? $conversation->waiting_since
                    : ($freshHandoverClock ? now() : ($conversation->waiting_since ?: now())),
            ]);

            $sender = ($customer || $user) ? 'customer' : 'guest';
            $this->appendMessage($conversation, $sender, $body, $user?->id, false, true);

            // Consume the single optional follow-up only when this is truly a follow-up:
            // automation handover flag, or already waiting with a prior customer message.
            // (openConversationFor seeds status=waiting, so the first Speak must not lock.)
            $hadPriorCustomerWhileWaiting = $isWaitingHuman && $lastCustomer !== null;
            if ((bool) ($meta['waiting_followup_allowed'] ?? false) || $hadPriorCustomerWhileWaiting) {
                $meta['waiting_followup_used'] = true;
                $meta['waiting_followup_allowed'] = false;
                $conversation->update(['automation_meta' => $meta]);
            }

            $hasWaitingAck = $this->hasWaitingAcknowledgement($conversation);
            if (! $hasWaitingAck) {
                $this->appendMessage(
                    $conversation,
                    'staff',
                    $this->waitingAcknowledgement(),
                    null,
                    true,
                    false,
                );
                $conversation->update([
                    'needs_human' => true,
                    'status' => $conversation->assigned_to ? self::STATUS_ASSIGNED : self::STATUS_WAITING,
                    'waiting_nudge_level' => max(1, (int) ($conversation->waiting_nudge_level ?? 0)),
                    'waiting_since' => $conversation->waiting_since ?: now(),
                ]);
            }

            // Presence is operational metadata — never inject offline bubbles into customer chat.
            return $conversation->fresh(['customer', 'user', 'messages', 'assignedTo']) ?? $conversation;
        });
    }

    /**
     * Open (non-terminal) conversations for a Member or Partner, newest activity first.
     * Does not merge or delete history — used for resume / chooser UI.
     *
     * @return \Illuminate\Support\Collection<int, SupportConversation>
     */
    public function listOpenConversationsFor(?Customer $customer, ?User $user): \Illuminate\Support\Collection
    {
        $q = SupportConversation::query()
            ->whereNotIn('status', [self::STATUS_CLOSED, self::STATUS_RESOLVED])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        if ($customer) {
            $q->where('customer_id', $customer->id);
        } elseif ($user) {
            $q->where('user_id', $user->id)->whereNull('customer_id');
        } else {
            return collect();
        }

        return $q->get();
    }

    public function openConversationFor(
        ?Customer $customer,
        ?User $user,
        ?string $guestName = null,
        ?string $guestPhone = null,
        ?string $channel = 'web_chat',
    ): SupportConversation {
        // Guest: keep single active phone thread (retire siblings — guests stay contacts).
        if (! $customer && ! $user) {
            if ($guestPhone) {
                $existing = SupportConversation::query()
                    ->whereNull('customer_id')
                    ->whereNull('user_id')
                    ->where('guest_phone', $guestPhone)
                    ->whereNotIn('status', [self::STATUS_CLOSED, self::STATUS_RESOLVED])
                    ->latest('id')
                    ->first();
                if ($existing) {
                    $this->retireSiblingOpenConversations($existing, null, null, $guestPhone);

                    return $this->normalizeLegacyStatus($existing);
                }
            }

            $created = SupportConversation::query()->create([
                'conversation_number' => $this->nextConversationNumber(),
                'channel' => $channel ?: 'web_chat',
                'status' => self::STATUS_WAITING,
                'needs_human' => true,
                'guest_name' => $guestName,
                'guest_phone' => $guestPhone,
                'last_message_at' => now(),
            ]);
            if ($guestPhone) {
                $this->retireSiblingOpenConversations($created, null, null, $guestPhone);
            }

            return $created;
        }

        // Member / Partner: never create a second open CNV; reconcile any historical duplicates first.
        return DB::transaction(function () use ($customer, $user, $channel) {
            $canonical = $this->reconcileOpenConversationsFor($customer, $user);
            if ($canonical) {
                return $this->normalizeLegacyStatus($canonical);
            }

            return SupportConversation::query()->create([
                'conversation_number' => $this->nextConversationNumber(),
                'customer_id' => $customer?->id,
                'user_id' => $user?->id,
                'channel' => $channel ?: 'web_chat',
                'status' => self::STATUS_WAITING,
                'needs_human' => true,
                'last_message_at' => now(),
            ]);
        });
    }

    /**
     * Keep at most one unresolved Member/Partner conversation. Soft-close older duplicates
     * as resolved (history retained under Historia) — never delete.
     */
    public function reconcileOpenConversationsFor(?Customer $customer, ?User $user): ?SupportConversation
    {
        if (! $customer && ! $user) {
            return null;
        }

        $open = $this->listOpenConversationsFor($customer, $user);
        if ($open->isEmpty()) {
            return null;
        }

        $keep = $open
            ->sortByDesc(fn (SupportConversation $c) => $c->last_message_at?->timestamp
                ?? $c->updated_at?->timestamp
                ?? $c->id)
            ->first();

        if ($open->count() === 1) {
            return $keep;
        }

        foreach ($open as $cnv) {
            if ((int) $cnv->id === (int) $keep->id) {
                continue;
            }
            $meta = is_array($cnv->automation_meta) ? $cnv->automation_meta : [];
            $meta['reconciled_duplicate_of'] = $keep->id;
            $meta['reconciled_at'] = now()->toIso8601String();
            $cnv->update([
                'status' => self::STATUS_RESOLVED,
                'needs_human' => false,
                'assigned_to' => $cnv->assigned_to,
                'resolved_at' => $cnv->resolved_at ?: now(),
                'closed_at' => $cnv->closed_at ?: now(),
                'resolution_category' => $cnv->resolution_category ?: 'duplicate_reconcile',
                'resolution_note' => $cnv->resolution_note ?: 'Duplicate open conversation reconciled; history retained.',
                'automation_meta' => $meta,
                'rating_requested_at' => null,
            ]);
        }

        return $keep->fresh(['assignedTo', 'messages']) ?? $keep;
    }

    /**
     * Unread inbound for the Member/Partner side (staff/bot messages not yet read).
     */
    public function unreadForCustomer(SupportConversation $conversation): int
    {
        return $conversation->messages()
            ->whereNull('read_at')
            ->whereIn('sender_type', ['staff', 'bot'])
            ->count();
    }

    public function markReadForCustomer(SupportConversation $conversation): void
    {
        $conversation->messages()
            ->whereNull('read_at')
            ->whereIn('sender_type', ['staff', 'bot'])
            ->update(['read_at' => now()]);
    }

    /**
     * Guest-only: keep exactly one non-terminal conversation per phone.
     * Member/Partner open histories are preserved (chooser UI) — do not call for them.
     */
    public function retireSiblingOpenConversations(
        SupportConversation $keep,
        ?Customer $customer = null,
        ?User $user = null,
        ?string $guestPhone = null,
    ): void {
        // Only Guests — Member/Partner multi-open is handled by chooser, not retirement.
        if ($customer || $user || blank($guestPhone)) {
            return;
        }

        $q = SupportConversation::query()
            ->where('id', '!=', $keep->id)
            ->whereNotIn('status', [self::STATUS_CLOSED, self::STATUS_RESOLVED])
            ->whereNull('customer_id')
            ->whereNull('user_id')
            ->where('guest_phone', $guestPhone);

        $q->update([
            'status' => self::STATUS_RESOLVED,
            'needs_human' => false,
        ]);
    }

    /** Map legacy `open` (and similar) into Waiting/Assigned desk states without destroying history. */
    public function normalizeLegacyStatus(SupportConversation $conversation): SupportConversation
    {
        $status = (string) $conversation->status;
        if (in_array($status, [self::STATUS_WAITING, self::STATUS_ASSIGNED, self::STATUS_ACTIVE, self::STATUS_RESOLVED, self::STATUS_CLOSED], true)) {
            return $conversation;
        }

        $conversation->update([
            'status' => $conversation->assigned_to ? self::STATUS_ASSIGNED : self::STATUS_WAITING,
            'needs_human' => $conversation->needs_human || ! $conversation->assigned_to,
        ]);

        return $conversation->fresh() ?? $conversation;
    }

    /**
     * Accept / assign: agent introduction is sent only now.
     * Offline / away agents cannot accept — ownership is only claimed while online.
     */
    public function accept(SupportConversation $conversation, User $agent): SupportConversation
    {
        $availability = app(CustomerSupportWorkspaceService::class)->availability($agent);
        if ($availability !== 'online') {
            throw new \InvalidArgumentException(
                'Agent must be Online to accept or assign this conversation (current: '.ucfirst($availability).').'
            );
        }

        $firstAssign = ! $conversation->assigned_to || (int) $conversation->assigned_to !== (int) $agent->id;

        $conversation->update([
            'assigned_to' => $agent->id,
            'status' => self::STATUS_ASSIGNED,
            'needs_human' => true,
            'handling_state' => SupportAutomationService::STATE_HUMAN,
            'accepted_at' => now(),
            'waiting_since' => null,
        ]);

        if ($firstAssign) {
            $this->appendMessage(
                $conversation,
                'staff',
                $this->agentIntroduction($conversation, $agent),
                $agent->id,
                true,
                false,
            );
        }

        return $conversation->fresh(['customer', 'user', 'assignedTo', 'messages']);
    }

    public function appendMessage(
        SupportConversation $conversation,
        string $senderType,
        mixed $body,
        ?int $senderUserId = null,
        bool $automated = false,
        bool $advanceState = true,
    ): SupportMessage {
        $safeBody = $this->safeChatText($body);
        $message = $conversation->messages()->create([
            'sender_type' => $senderType,
            'sender_user_id' => $senderUserId,
            'body' => $safeBody,
            'is_automated' => $automated,
            'read_at' => in_array($senderType, ['staff', 'bot'], true) ? now() : null,
        ]);

        if ($advanceState) {
            $updates = ['last_message_at' => now()];
            if ($senderType === 'staff' && ! $automated) {
                $updates['needs_human'] = false;
                $updates['status'] = self::STATUS_ACTIVE;
                // Never auto-claim: agent identity appears only after Accept/Take.
            } elseif (in_array($senderType, ['customer', 'guest'], true)) {
                $updates['needs_human'] = true;
                // New inbound activity returns the thread to team queue unless already accepted.
                if (! $conversation->assigned_to) {
                    $updates['status'] = self::STATUS_WAITING;
                    $updates['assigned_to'] = null;
                } else {
                    $updates['status'] = self::STATUS_ASSIGNED;
                }
            } else {
                $updates['last_message_at'] = now();
            }
            $conversation->update($updates);
            // Presence is operational metadata — never inject offline bubbles into customer chat.
        } else {
            $conversation->update(['last_message_at' => now()]);
        }

        return $message;
    }

    public function resolve(
        SupportConversation $conversation,
        ?User $actor = null,
        ?string $note = null,
        ?string $category = null,
        bool $askRating = true,
    ): SupportConversation {
        if (in_array($conversation->status, [self::STATUS_RESOLVED, self::STATUS_CLOSED], true)) {
            return $conversation;
        }

        $first = $this->requesterFirstName($conversation) ?: 'mteja';
        // Resolution copy — interactive ★ card is keyed off rating_requested_at (not plain-text stars).
        $body = "Habari {$first}, suala lako limekamilishwa. Tunatumaini tumekusaidia. Tafadhali tathmini huduma yetu.";
        if ($note) {
            $body = trim($body)."\n\n".$note;
        }

        $this->appendMessage($conversation, 'staff', $body, $actor?->id, true, false);

        $now = now();
        $conversation->update([
            'status' => self::STATUS_CLOSED,
            'needs_human' => false,
            'handling_state' => SupportAutomationService::STATE_RESOLVED_SUPPORT,
            'resolution_kind' => 'support',
            'assigned_to' => $conversation->assigned_to,
            'resolution_category' => $category,
            'resolution_note' => $note,
            'resolved_at' => $now,
            'closed_at' => $now,
            'resolved_by' => $actor?->id,
            'rating_requested_at' => $askRating ? $now : $conversation->rating_requested_at,
            'last_message_at' => $now,
        ]);

        $fresh = $conversation->fresh();
        if ($askRating && $fresh) {
            $this->notifyRatingRequest($fresh);
        }

        return $fresh;
    }

    /** In-app notice only — never a CTA into a conversation (avoids opening the wrong CNV). */
    public function notifyRatingRequest(SupportConversation $conversation): void
    {
        if (! $conversation->customer_id || ! $conversation->awaitsRating()) {
            return;
        }

        $customer = Customer::query()->find($conversation->customer_id);
        if (! $customer) {
            return;
        }

        $ref = $conversation->publicNumber();
        $topic = trim((string) ($conversation->topic ?? ''));
        $sw = str_starts_with(app()->getLocale(), 'sw');
        $body = $sw
            ? 'Suala lako la msaada limekamilishwa.'.($ref !== '' ? " ({$ref})" : '')
                .($topic !== '' ? " · {$topic}" : '')
                .' Tafadhali tathmini huduma yetu ukifungua Kituo cha Usaidizi.'
            : 'Your support issue has been resolved.'.($ref !== '' ? " ({$ref})" : '')
                .($topic !== '' ? " · {$topic}" : '')
                .' Please rate your experience when you open the Support Centre.';

        app(\App\Services\NotificationService::class)->notifyInApp(
            $customer,
            $body,
            'support',
            'support_resolved',
            $sw ? 'Suala lako la msaada limekamilishwa' : 'Your support issue has been resolved',
            null,
            null,
            [
                'title_key' => 'borrower.notifications.support_resolved_title',
                'body_key' => 'borrower.notifications.support_resolved_body',
                'params' => [
                    'reference' => $ref,
                    'topic' => $topic,
                ],
                'conversation_id' => $conversation->id,
                'conversation_number' => $ref,
                'informational_only' => true,
            ],
        );
    }

    /**
     * Member/partner chat surface: open thread, else latest closed thread awaiting rating.
     */
    public function memberFacingConversation(?int $customerId = null, ?int $userId = null): ?SupportConversation
    {
        $open = SupportConversation::query()
            ->whereNotIn('status', [self::STATUS_CLOSED, self::STATUS_RESOLVED])
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->when(! $customerId && $userId, fn ($q) => $q->where('user_id', $userId)->whereNull('customer_id'))
            ->with(['messages' => fn ($q) => $q->orderBy('id'), 'assignedTo'])
            ->latest('id')
            ->first();

        if ($open) {
            return $open;
        }

        return SupportConversation::query()
            ->whereIn('status', [self::STATUS_CLOSED, self::STATUS_RESOLVED])
            ->whereNotNull('rating_requested_at')
            ->whereNull('rating')
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->when(! $customerId && $userId, fn ($q) => $q->where('user_id', $userId)->whereNull('customer_id'))
            ->with(['messages' => fn ($q) => $q->orderBy('id'), 'assignedTo'])
            ->latest('id')
            ->first();
    }

    /** @return array{show_rating: bool, rating_done: bool, rating: ?int, rating_url: ?string} */
    public function ratingPayload(SupportConversation $conversation, ?string $ratingUrl = null): array
    {
        $done = (bool) $conversation->rating;
        $show = $conversation->awaitsRating();

        return [
            'show_rating' => $show,
            'rating_done' => $done,
            'rating' => $conversation->rating ? (int) $conversation->rating : null,
            'rating_url' => ($show || $done) ? $ratingUrl : null,
        ];
    }

    public function recordConversationRating(SupportConversation $conversation, int $rating, ?string $comment = null): SupportConversation
    {
        // One submission only — never create a ticket/CNV or overwrite an existing CSAT.
        if ($conversation->rating) {
            return $conversation->fresh() ?? $conversation;
        }

        $rating = max(1, min(5, $rating));
        $comment = $comment !== null ? trim((string) $comment) : '';
        $meta = is_array($conversation->automation_meta) ? $conversation->automation_meta : [];
        if ($comment !== '') {
            $meta['rating_comment'] = $comment;
        }

        $conversation->update([
            'rating' => $rating,
            'rated_at' => now(),
            'automation_meta' => $meta,
            'resolution_note' => $comment !== ''
                ? trim((string) $conversation->resolution_note."\nRating note: ".$comment)
                : $conversation->resolution_note,
        ]);

        return $conversation->fresh();
    }

    public function waitingAcknowledgement(): string
    {
        if (str_starts_with(app()->getLocale(), 'en')) {
            return "We're finding the right support agent for your case. Please wait a moment.";
        }

        return 'Tunatafuta mtoa huduma anayefaa kukusaidia. Tafadhali subiri kidogo.';
    }

    /** True when a searching/waiting acknowledgement was already posted (never emit twice). */
    public function hasWaitingAcknowledgement(SupportConversation $conversation): bool
    {
        return $conversation->messages()
            ->where('is_automated', true)
            ->where(function ($q) {
                $q->where('body', 'like', 'Tunatafuta mtoa huduma%')
                    ->orWhere('body', 'like', "We're finding the right support agent%")
                    ->orWhere('body', 'like', 'We are finding the right support agent%')
                    ->orWhere('body', 'like', 'Tumepokea ujumbe wako%')
                    ->orWhere('body', 'like', 'Ujumbe wako umepokelewa%')
                    ->orWhere('body', 'like', 'We received your message%')
                    ->orWhere('body', 'like', 'We’ve received your message%')
                    ->orWhere('body', 'like', "We've received your message%");
            })
            ->exists();
    }

    /**
     * Explicit confirmation templates only — not every question-shaped message.
     * Keys: confirm (Thibitisha), issue_resolved_check (Tatizo limetatuliwa?).
     */
    public function isHumanConfirmationTemplate(?string $templateKey, string $body = ''): bool
    {
        $key = trim((string) $templateKey);
        if (in_array($key, ['confirm', 'issue_resolved_check'], true)) {
            return true;
        }

        $body = trim($body);

        return str_starts_with($body, 'Je, tatizo lako limetatuliwa')
            || str_starts_with($body, 'Has your issue been resolved');
    }

    public function markAwaitingCustomerResolution(
        SupportConversation $conversation,
        ?string $resolutionCategory = null,
        ?string $note = null,
    ): SupportConversation {
        $meta = is_array($conversation->automation_meta) ? $conversation->automation_meta : [];
        $meta['awaiting_customer_resolution'] = true;
        $meta['awaiting_customer_resolution_at'] = now()->toIso8601String();
        if ($resolutionCategory !== null && $resolutionCategory !== '') {
            $meta['pending_resolution_category'] = $resolutionCategory;
        }
        if ($note !== null && trim($note) !== '') {
            $meta['pending_resolution_note'] = trim($note);
        }
        $conversation->update(['automation_meta' => $meta]);

        return $conversation->fresh() ?? $conversation;
    }

    /** @return list<array{key: string, label: string}> */
    public function humanResolutionChoices(?string $locale = null): array
    {
        $sw = str_starts_with(strtolower((string) ($locale ?? app()->getLocale())), 'sw');

        return [
            ['key' => 'yes', 'label' => $sw ? 'Ndiyo' : 'Yes'],
            ['key' => 'no', 'label' => $sw ? 'Hapana' : 'No'],
            ['key' => 'another', 'label' => $sw ? 'Nina tatizo lingine' : 'I have another issue'],
        ];
    }

    public function isAwaitingCustomerResolution(SupportConversation $conversation): bool
    {
        $meta = is_array($conversation->automation_meta) ? $conversation->automation_meta : [];

        return ! empty($meta['awaiting_customer_resolution'])
            && ! in_array((string) $conversation->status, [self::STATUS_CLOSED, self::STATUS_RESOLVED], true);
    }

    /**
     * One closure engine for Thibitisha / Tatizo limetatuliwa? / Human Resolve.
     *
     * @return array<string, mixed>
     */
    public function confirmHumanResolution(
        SupportConversation $conversation,
        string $choice,
        ?User $actor = null,
        ?string $ratingUrl = null,
        ?Customer $customer = null,
        ?User $user = null,
    ): array {
        $choice = strtolower(trim($choice));
        if (! in_array($choice, ['yes', 'no', 'another'], true)) {
            throw new \InvalidArgumentException('invalid_resolution_choice');
        }

        $meta = is_array($conversation->automation_meta) ? $conversation->automation_meta : [];
        if (empty($meta['awaiting_customer_resolution'])) {
            throw new \InvalidArgumentException('not_awaiting_resolution');
        }

        $sw = str_starts_with(app()->getLocale(), 'sw');
        $label = match ($choice) {
            'yes' => $sw ? 'Ndiyo' : 'Yes',
            'no' => $sw ? 'Hapana' : 'No',
            default => $sw ? 'Nina tatizo lingine' : 'I have another issue',
        };

        $this->appendMessage(
            $conversation,
            ($customer || $user || $conversation->customer_id || $conversation->user_id) ? 'customer' : 'guest',
            $label,
            $actor?->id ?? $user?->id,
            false,
            true,
        );

        $pendingCategory = is_string($meta['pending_resolution_category'] ?? null)
            ? (string) $meta['pending_resolution_category']
            : null;
        $pendingNote = is_string($meta['pending_resolution_note'] ?? null)
            ? (string) $meta['pending_resolution_note']
            : null;

        unset(
            $meta['awaiting_customer_resolution'],
            $meta['awaiting_customer_resolution_at'],
            $meta['pending_resolution_category'],
            $meta['pending_resolution_note'],
        );
        $conversation->update(['automation_meta' => $meta]);

        if ($choice === 'no') {
            $this->appendMessage(
                $conversation,
                'staff',
                $sw
                    ? 'Sawa. Niambie bado kuna nini ili nikusaidie.'
                    : 'Alright. Tell me what still needs help.',
                null,
                true,
                false,
            );
            $fresh = $conversation->fresh(['messages', 'assignedTo']) ?? $conversation;

            return array_merge([
                'ok' => true,
                'resolved' => false,
                'choice' => 'no',
                'conversation_id' => $fresh->id,
                'status' => $fresh->status,
                'messages' => $this->serializeMessages($fresh),
                'resolution_choices' => [],
                'resolution_prompt' => false,
                'mode' => 'human',
            ], $this->memberChatPresence($fresh));
        }

        $category = $pendingCategory ?: ($choice === 'another' ? 'another_issue' : 'customer_confirmed');
        // Another issue: close this human CNV (rating requested for history), then Digital owns the next issue.
        $askRating = $choice === 'yes';
        $resolved = $this->resolve($conversation, null, $pendingNote, $category, $askRating);

        if ($choice === 'yes') {
            $rating = $ratingUrl
                ? $this->ratingPayload($resolved, $ratingUrl)
                : ['show_rating' => false, 'rating_done' => false, 'rating' => null, 'rating_url' => null];

            return array_merge([
                'ok' => true,
                'resolved' => true,
                'choice' => 'yes',
                'conversation_id' => $resolved->id,
                'status' => $resolved->status,
                'messages' => $this->serializeMessages($resolved),
                'composer_locked' => true,
                'mode' => 'human',
            ], $this->memberChatPresence($resolved), $rating);
        }

        // another → start a fresh Digital Assistant conversation (one active CNV invariant).
        $audience = 'member';
        if ($customer) {
            $audience = 'member';
        } elseif ($user) {
            $audience = 'partner';
        }
        $digital = app(SupportAutomationService::class)->start(
            $customer,
            $user,
            $audience,
            null,
            null,
            null,
            app()->getLocale(),
        );

        return array_merge($digital, [
            'ok' => true,
            'resolved' => true,
            'choice' => 'another',
            'previous_conversation_id' => $resolved->id,
            'mode' => 'automation',
            'automation' => true,
            'resolution_choices' => [],
            'resolution_prompt' => false,
            'composer_locked' => false,
            'show_rating' => false,
        ]);
    }

    /**
     * Place an existing conversation into the human Waiting queue in place.
     * Does not open/create another conversation — preserves reference, history, and automation meta.
     */
    public function placeInWaitingQueue(
        SupportConversation $conversation,
        string $body,
        ?string $topic = null,
        bool $consumeOptionalFollowup = false,
        bool $sendWaitingAck = false,
    ): SupportConversation {
        $body = trim($body);
        $meta = is_array($conversation->automation_meta) ? $conversation->automation_meta : [];

        if ($consumeOptionalFollowup) {
            if ((bool) ($meta['waiting_followup_used'] ?? false)) {
                throw new \InvalidArgumentException('composer_locked');
            }
            $meta['waiting_followup_used'] = true;
            $meta['waiting_followup_allowed'] = false;
            $sendWaitingAck = true;
        }

        $wasAlreadyWaiting = (bool) $conversation->needs_human
            && in_array((string) $conversation->status, [self::STATUS_WAITING], true)
            && ! $conversation->assigned_to;

        $conversation->update([
            'needs_human' => true,
            'assigned_to' => null,
            'status' => self::STATUS_WAITING,
            // Fresh escalation time so the row sorts as a current handover (not the old CNV created_at).
            'waiting_since' => $wasAlreadyWaiting && $conversation->waiting_since
                ? $conversation->waiting_since
                : now(),
            'topic' => $topic ?: $conversation->topic,
            'last_message_at' => now(),
            'automation_meta' => $meta,
        ]);

        if ($body !== '') {
            $sender = ($conversation->customer_id || $conversation->user_id) ? 'customer' : 'guest';
            $this->appendMessage($conversation, $sender, $body, $conversation->user_id, false, true);
        }

        // Waiting ack is for the optional follow-up (or explicit request), not the escalate hop itself.
        if ($sendWaitingAck && ! $this->hasWaitingAcknowledgement($conversation)) {
            $this->appendMessage(
                $conversation,
                'staff',
                $this->waitingAcknowledgement(),
                null,
                true,
                false,
            );
            $conversation->update([
                'waiting_nudge_level' => max(1, (int) ($conversation->waiting_nudge_level ?? 0)),
            ]);
        }

        return $conversation->fresh(['customer', 'user', 'messages', 'assignedTo']) ?? $conversation;
    }

    /**
     * Re-stamp legacy sequential / missing conversation numbers to alphanumeric (PK unchanged).
     */
    public function ensureAlphanumericReference(SupportConversation $conversation): SupportConversation
    {
        if (! $conversation->needsAlphanumericReference()) {
            return $conversation;
        }

        $conversation->update(['conversation_number' => $this->nextConversationNumber()]);

        return $conversation->fresh() ?? $conversation;
    }

    public function ensureTicketAlphanumericReference(SupportTicket $ticket): SupportTicket
    {
        if (! $ticket->needsAlphanumericReference()) {
            return $ticket;
        }

        $tickets = app(SupportTicketService::class);
        $ticket->update(['ticket_number' => $tickets->nextTicketNumber()]);

        return $ticket->fresh() ?? $ticket;
    }

    /** Localized customer-facing conversation/ticket status (never raw open/escalated keys). */
    public function customerFacingStatusLabel(string $status, ?string $locale = null): string
    {
        $sw = str_starts_with(strtolower((string) ($locale ?? app()->getLocale())), 'sw');

        return match (strtolower(trim($status))) {
            'open' => $sw ? 'Imefunguliwa' : 'Open',
            'waiting' => $sw ? 'Inasubiri' : 'Waiting',
            'assigned', 'active', 'in_progress' => $sw ? 'Inaendelea' : 'In progress',
            'escalated' => $sw ? 'Imepelekwa' : 'Escalated',
            'resolved' => $sw ? 'Imetatuliwa' : 'Resolved',
            'closed' => $sw ? 'Imefungwa' : 'Closed',
            'awaiting_guarantor', 'guarantor_pending' => $sw ? 'Inasubiri mdhamini' : 'Awaiting guarantor',
            'pending_documents', 'documents_requested' => $sw ? 'Inasubiri nyaraka' : 'Documents requested',
            'screening', 'under_review' => $sw ? 'Inachunguzwa' : 'Under review',
            'offer_issued', 'offer_ready' => $sw ? 'Ofa iko tayari' : 'Offer ready',
            'approved' => $sw ? 'Imeidhinishwa' : 'Approved',
            'rejected' => $sw ? 'Imekataliwa' : 'Rejected',
            'submitted' => $sw ? 'Imewasilishwa' : 'Submitted',
            'draft' => $sw ? 'Rasimu' : 'Draft',
            default => $sw ? 'Inaendelea' : 'In progress',
        };
    }

    /**
     * Member/Partner chat header presence (does not affect message delivery).
     * Agent identity is shown only after Accept (assigned/active), never on waiting queue.
     *
     * @return array{assigned_to:?int, agent_first_name:?string, status:string, presence:string, desk_label:string, composer_locked?:bool}
     */
    public function memberChatPresence(?SupportConversation $conversation, ?string $locale = null): array
    {
        $sw = str_starts_with(strtolower((string) ($locale ?? app()->getLocale())), 'sw');
        $waitingLabel = $sw ? 'Inasubiri mtoa huduma' : 'Waiting for support';
        $teamLabel = $sw ? 'Timu ya Usaidizi' : 'Support Team';
        $assignedLabel = $sw ? 'Kopafasta Support' : 'Kopafasta Support';

        if (! $conversation) {
            return [
                'assigned_to' => null,
                'agent_first_name' => null,
                'status' => self::STATUS_WAITING,
                'presence' => 'online',
                'desk_label' => $waitingLabel,
                'brand_title' => null,
                'composer_locked' => false,
            ];
        }

        $conversation->loadMissing('assignedTo');
        $accepted = $conversation->assigned_to
            && in_array($conversation->status, [self::STATUS_ASSIGNED, self::STATUS_ACTIVE], true);
        $agentFirst = $accepted
            ? ($this->personFirstName((string) ($conversation->assignedTo?->name ?? '')) ?: null)
            : null;

        $meta = is_array($conversation->automation_meta) ? $conversation->automation_meta : [];
        $humanOwned = (bool) ($conversation->needs_human)
            || $accepted
            || in_array((string) $conversation->status, [self::STATUS_WAITING, self::STATUS_ASSIGNED, self::STATUS_ACTIVE], true)
                && in_array((string) ($conversation->handling_state ?? ''), [
                    SupportAutomationService::STATE_ESCALATED,
                    SupportAutomationService::STATE_HUMAN,
                ], true);

        $waitingLock = in_array((string) $conversation->status, [self::STATUS_WAITING], true)
            && (bool) ($conversation->needs_human)
            && (bool) ($meta['waiting_followup_used'] ?? false)
            && ! $accepted;

        return [
            'assigned_to' => $accepted ? (int) $conversation->assigned_to : null,
            'agent_first_name' => $agentFirst,
            'status' => (string) $conversation->status,
            'presence' => $agentFirst ? 'assigned' : 'online',
            'desk_label' => $agentFirst ? $assignedLabel : ($humanOwned ? $waitingLabel : $waitingLabel),
            'brand_title' => $agentFirst
                ? ($assignedLabel.' · '.$agentFirst)
                : ($humanOwned ? $teamLabel : null),
            'composer_locked' => $waitingLock
                || in_array((string) $conversation->status, [self::STATUS_CLOSED, self::STATUS_RESOLVED], true),
        ];
    }

    /** Human-readable desk state for staff UI. */
    public function deskState(SupportConversation $conversation, ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $handling = (string) ($conversation->handling_state ?? '');
        if ($handling !== '') {
            return app(SupportAutomationService::class)->handlingLabel($handling, $locale);
        }

        return $this->customerFacingStatusLabel((string) $conversation->status, $locale);
    }

    /**
     * Customer/staff chat text must be an approved human-readable string.
     * Never stringify Eloquent models, arrays, DTOs, or JSON dumps into bubbles.
     */
    public function safeChatText(mixed $value, ?string $locale = null): string
    {
        // Eloquent models implement Stringable and cast to JSON — never allow that into chat.
        if ($value instanceof \Illuminate\Database\Eloquent\Model
            || $value instanceof \Illuminate\Support\Collection
            || is_array($value)
        ) {
            \Illuminate\Support\Facades\Log::warning('support.chat.blocked_payload', [
                'type' => is_object($value) ? $value::class : gettype($value),
            ]);

            return $this->unsafePayloadFallback($locale);
        }

        if (is_object($value) && ! $value instanceof \Stringable) {
            \Illuminate\Support\Facades\Log::warning('support.chat.blocked_payload', [
                'type' => $value::class,
            ]);

            return $this->unsafePayloadFallback($locale);
        }

        if (is_scalar($value) || $value === null || $value instanceof \Stringable) {
            $text = trim((string) $value);
        } else {
            return $this->unsafePayloadFallback($locale);
        }

        if ($text === '') {
            return '';
        }

        if ($this->looksLikeSerializedDump($text)) {
            \Illuminate\Support\Facades\Log::warning('support.chat.blocked_serialized_dump', [
                'length' => strlen($text),
            ]);

            return $this->unsafePayloadFallback($locale);
        }

        if ($this->looksLikeFrameworkLeak($text)) {
            \Illuminate\Support\Facades\Log::warning('support.chat.blocked_framework_leak', [
                'length' => strlen($text),
            ]);

            return $this->unsafePayloadFallback($locale);
        }

        return $text;
    }

    public function looksLikeFrameworkLeak(string $text): bool
    {
        $t = $text;

        return (bool) preg_match(
            '/\bRoute\s*\[[^\]]+\]\s+not defined\b|\bIlluminate\\\\|\bSymfony\\\\|\bSQLSTATE\[|\bstack trace\b|\bUndefined (variable|array key|property)\b|\bCall to (undefined|a member function)\b/i',
            $t
        );
    }

    public function looksLikeSerializedDump(string $text): bool
    {
        $t = ltrim($text);
        if ($t === '' || ($t[0] !== '{' && $t[0] !== '[' && ! str_contains($t, '{"'))) {
            return false;
        }

        // Full JSON object/array dump, or a message that embedded one.
        $needles = [
            '"email_verified_at"',
            '"preferences"',
            '"remember_token"',
            '"password"',
            '"two_factor_secret"',
            '"updated_at"',
            '"created_at"',
            '"affiliate_id"',
            '"risk_',
        ];
        $hits = 0;
        foreach ($needles as $needle) {
            if (str_contains($t, $needle)) {
                $hits++;
            }
        }

        if ($hits >= 2) {
            return true;
        }

        // Bare model dump: starts with { and has id + first_name/phone style attributes.
        if ($t[0] === '{' && str_contains($t, '"id"') && (
            str_contains($t, '"first_name"') || str_contains($t, '"email"') || str_contains($t, '"phone"')
        ) && str_contains($t, '"created_at"')) {
            return true;
        }

        return false;
    }

    public function unsafePayloadFallback(?string $locale = null): string
    {
        if (str_starts_with(strtolower((string) ($locale ?? app()->getLocale())), 'en')) {
            return 'I found the relevant account information, but I need a moment to present it clearly. Please try again, or Talk to Support.';
        }

        return 'Nimepata taarifa za akaunti, lakini nahitaji muda mfupi kuziwasilisha wazi. Jaribu tena, au Ongea na Usaidizi.';
    }

    /**
     * Person first names only — never accept models/JSON dumps as a "name".
     */
    public function safePersonFirstName(mixed $value): string
    {
        if ($value instanceof \Illuminate\Database\Eloquent\Model || is_array($value) || is_object($value)) {
            return '';
        }
        $text = trim((string) $value);
        if ($text === '' || $this->looksLikeSerializedDump($text) || strlen($text) > 60 || str_contains($text, '{')) {
            return '';
        }

        return $this->personFirstName($text);
    }

    public function assignedAgentOfflineAcknowledgement(): string
    {
        if (str_starts_with(app()->getLocale(), 'en')) {
            return 'Your support agent is offline right now. Your message has been saved and they will see it when they return.';
        }

        return 'Mtoa huduma wako hayupo mtandaoni kwa sasa. Ujumbe wako umehifadhiwa na atauona atakaporejea.';
    }

    /**
     * Once per offline period while an assigned agent is offline — do not unassign.
     */
    public function maybeSendAssignedAgentOfflineAck(SupportConversation $conversation): void
    {
        if (! $conversation->assigned_to) {
            return;
        }
        if (! in_array((string) $conversation->status, [self::STATUS_ASSIGNED, self::STATUS_ACTIVE], true)) {
            return;
        }

        $agent = $conversation->assignedTo ?: User::query()->find($conversation->assigned_to);
        if (! $agent) {
            return;
        }

        $availability = app(CustomerSupportWorkspaceService::class)->availability($agent);
        if ($availability === 'online') {
            return;
        }

        $marker = 'support_offline_ack:'.$agent->id.':'.now()->toDateString();
        $already = $conversation->messages()
            ->where('is_automated', true)
            ->where(function ($q) {
                $q->where('body', 'like', 'Mtoa huduma wako hayupo%')
                    ->orWhere('body', 'like', 'Your support agent is offline%');
            })
            ->where('created_at', '>=', now()->subHours(8))
            ->exists();
        if ($already) {
            return;
        }

        $this->appendMessage(
            $conversation,
            'staff',
            $this->assignedAgentOfflineAcknowledgement(),
            null,
            true,
            false,
        );
        // Keep assignment; only touch last_message_at via appendMessage.
        unset($marker);
    }

    public function offlineAgentAcknowledgement(): string
    {
        if (str_starts_with(app()->getLocale(), 'en')) {
            return 'Your Support agent is offline right now. They will reply when they are back online. Your message is saved — they remain assigned to you.';
        }

        return 'Mtoa huduma wako hayupo mtandaoni kwa sasa. Atakujibu atakaporudi mtandaoni. Ujumbe wako umehifadhiwa — bado anahudumia mazungumzo yako.';
    }

    /**
     * Once per offline stretch: notify the member that the assigned agent is offline.
     * Does not unassign — ownership stays with the agent.
     */
    public function maybeNotifyAssignedAgentOffline(SupportConversation $conversation): void
    {
        if (! $conversation->assigned_to || in_array($conversation->status, [self::STATUS_CLOSED, self::STATUS_RESOLVED], true)) {
            return;
        }

        $agent = $conversation->assignedTo ?: User::query()->find($conversation->assigned_to);
        if (! $agent) {
            return;
        }

        $availability = app(CustomerSupportWorkspaceService::class)->availability($agent);
        if ($availability === 'online') {
            return;
        }

        $since = $conversation->messages()
            ->where('sender_type', 'staff')
            ->where('is_automated', false)
            ->latest('id')
            ->value('created_at')
            ?? $conversation->accepted_at
            ?? $conversation->created_at;

        $already = $conversation->messages()
            ->where('is_automated', true)
            ->where(function ($q) {
                $q->where('body', 'like', 'Mtoa huduma wako hayupo mtandaoni%')
                    ->orWhere('body', 'like', 'Your Support agent is offline%');
            })
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->exists();

        if ($already) {
            return;
        }

        $this->appendMessage(
            $conversation,
            'staff',
            $this->offlineAgentAcknowledgement(),
            null,
            true,
            false,
        );
    }

    public function waitingFollowUpAcknowledgement(): string
    {
        return 'Samahani kwa kusubiri. Watoa huduma wetu wote wanahudumia wateja wengine kwa sasa. Ujumbe wako bado uko kwenye foleni na tutakuhudumia mara tu mhudumu atakapopatikana.';
    }

    /**
     * Send staged waiting nudges based on Settings thresholds (minutes).
     * Level 1 = first ack (on create). Level 2 = delay apology after threshold.
     */
    public function maybeSendWaitingNudge(SupportConversation $conversation): void
    {
        if ($conversation->assigned_to || ! in_array($conversation->status, [self::STATUS_WAITING], true)) {
            return;
        }

        $since = $conversation->waiting_since ?? $conversation->created_at ?? now();
        $minutes = max(0, $since->diffInMinutes(now()));
        $level2At = (int) \App\Models\Setting::get('support.waiting.followup_minutes', 3);

        if ((int) ($conversation->waiting_nudge_level ?? 0) < 2 && $minutes >= $level2At) {
            $this->appendMessage(
                $conversation,
                'staff',
                $this->waitingFollowUpAcknowledgement(),
                null,
                true,
                false,
            );
            $conversation->update(['waiting_nudge_level' => 2]);
        }
    }

    public function waitingDurationLabel(SupportConversation $conversation): string
    {
        $since = $conversation->waiting_since
            ?? $conversation->last_message_at
            ?? $conversation->created_at
            ?? now();
        $seconds = max(0, $since->diffInSeconds(now()));
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;
        if ($h > 0) {
            return sprintf('%02d:%02d:%02d', $h, $m, $s);
        }

        return sprintf('%02d:%02d', $m, $s);
    }

    public function markReadForStaff(SupportConversation $conversation): void
    {
        $conversation->messages()
            ->whereNull('read_at')
            ->whereIn('sender_type', ['customer', 'guest', 'bot'])
            ->update(['read_at' => now()]);
    }

    public function unreadCount(SupportConversation $conversation): int
    {
        return $conversation->messages()
            ->whereNull('read_at')
            ->whereIn('sender_type', ['customer', 'guest'])
            ->count();
    }

    public function agentIntroduction(SupportConversation $conversation, User $agent): string
    {
        $requesterFirst = $this->requesterFirstName($conversation);
        $agentFirst = $this->personFirstName($agent->name) ?: 'Mtoa huduma';

        $greeting = $requesterFirst !== ''
            ? "Habari {$requesterFirst},"
            : 'Habari,';

        $body = "{$greeting} jina langu ni {$agentFirst} kutoka Huduma kwa Wateja ya Kopafasta. Nitafanya kila niwezalo kukusaidia kutatua suala lako.";

        // Contact footer only on the first human introduction for this interaction.
        $sig = app(SupportQuickReplyService::class)->signature('sw');
        if ($sig !== '') {
            $body .= "\n\n".$sig;
        }

        return $body;
    }

    public function requesterFirstName(SupportConversation $conversation): string
    {
        $customer = $conversation->customer;
        if ($customer) {
            $fromField = $this->personFirstName((string) ($customer->first_name ?? ''));
            if ($fromField !== '') {
                return $fromField;
            }
            $fromFull = $this->personFirstName(trim((string) ($customer->full_name ?? '')));
            if ($fromFull !== '') {
                return $fromFull;
            }
        }

        $guest = $this->personFirstName((string) ($conversation->guest_name ?? ''));
        if ($guest !== '') {
            return $guest;
        }

        return $this->personFirstName((string) ($conversation->user?->name ?? ''));
    }

    public function personFirstName(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return '';
        }

        $first = explode(' ', preg_replace('/\s+/', ' ', $name) ?? $name, 2)[0] ?? '';
        $first = trim($first, " \t\n\r\0\x0B,.");

        return $first;
    }

    /**
     * Serialize messages for member/staff polling JSON.
     *
     * @return list<array{id:int, role:string, sender_type:string, text:string, at:?string}>
     */
    public function serializeMessages(SupportConversation $conversation): array
    {
        return $conversation->messages()->orderBy('id')->get()
            ->reject(function (SupportMessage $m) {
                if (! $m->is_automated) {
                    return false;
                }
                $body = (string) $m->body;

                // Legacy offline presence bubbles — never show to customers again.
                return str_contains($body, 'Mtoa huduma wako hayupo')
                    || str_contains($body, 'Your support agent is offline')
                    || str_contains($body, 'Your Support agent is offline');
            })
            ->values()
            ->map(fn (SupportMessage $m) => [
                'id' => (int) $m->id,
                'role' => in_array($m->sender_type, ['staff', 'bot'], true) ? 'bot' : 'user',
                'sender_type' => (string) $m->sender_type,
                'text' => $this->safeChatText((string) $m->body),
                'at' => $m->created_at?->toIso8601String(),
                'time' => $m->created_at ? format_app_datetime($m->created_at, 'H:i') : null,
            ])->all();
    }
}
