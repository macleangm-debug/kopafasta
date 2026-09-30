<?php

namespace App\Services\Support;

use App\Models\Customer;
use App\Models\Setting;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

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
     * Settings-backed readable conversation number, e.g. KPF-CNV-000001.
     * Mirrors SupportTicketService::nextTicketNumber.
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

        $seqKey = 'support.conversation_number_seq.kpf';
        $seq = (int) Setting::get($seqKey, 0);
        do {
            $seq++;
            $candidate = $prefix.'-'.str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
        } while (SupportConversation::query()->where('conversation_number', $candidate)->exists());

        Setting::set($seqKey, $seq);

        return $candidate;
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
                    : ($conversation->waiting_since ?: now()),
            ]);

            $sender = ($customer || $user) ? 'customer' : 'guest';
            $this->appendMessage($conversation, $sender, $body, $user?->id, false, true);

            $hasWaitingAck = $conversation->messages()
                ->where('is_automated', true)
                ->where(function ($q) {
                    $q->where('body', 'like', 'Tumepokea ujumbe wako%')
                        ->orWhere('body', 'like', 'Ujumbe wako umepokelewa%')
                        ->orWhere('body', 'like', 'We received your message%');
                })
                ->exists();
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
            } else {
                $this->maybeSendWaitingNudge($conversation->fresh() ?? $conversation);
            }

            $fresh = $conversation->fresh(['customer', 'user', 'messages', 'assignedTo']) ?? $conversation;
            $this->maybeSendAssignedAgentOfflineAck($fresh);

            return $fresh;
        });
    }

    public function openConversationFor(
        ?Customer $customer,
        ?User $user,
        ?string $guestName = null,
        ?string $guestPhone = null,
        ?string $channel = 'web_chat',
    ): SupportConversation {
        // Deterministic active thread: latest non-terminal conversation for this requester.
        $query = SupportConversation::query()
            ->whereNotIn('status', [self::STATUS_CLOSED, self::STATUS_RESOLVED])
            ->latest('id');

        if ($customer) {
            $query->where('customer_id', $customer->id);
        } elseif ($user) {
            $query->where('user_id', $user->id)->whereNull('customer_id');
        } else {
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

        $existing = $query->first();
        if ($existing) {
            $this->retireSiblingOpenConversations($existing, $customer, $user, $guestPhone);

            return $this->normalizeLegacyStatus($existing);
        }

        $created = SupportConversation::query()->create([
            'conversation_number' => $this->nextConversationNumber(),
            'customer_id' => $customer?->id,
            'user_id' => $user?->id,
            'channel' => $channel ?: 'web_chat',
            'status' => self::STATUS_WAITING,
            'needs_human' => true,
            'guest_name' => $customer ? null : $guestName,
            'guest_phone' => $customer ? null : $guestPhone,
            'last_message_at' => now(),
        ]);

        $this->retireSiblingOpenConversations($created, $customer, $user, $guestPhone);

        return $created;
    }

    /**
     * Keep exactly one non-terminal conversation per requester.
     * Older open threads are resolved (history preserved) so Member and Support cannot drift.
     */
    public function retireSiblingOpenConversations(
        SupportConversation $keep,
        ?Customer $customer = null,
        ?User $user = null,
        ?string $guestPhone = null,
    ): void {
        $q = SupportConversation::query()
            ->where('id', '!=', $keep->id)
            ->whereNotIn('status', [self::STATUS_CLOSED, self::STATUS_RESOLVED]);

        if ($customer) {
            $q->where('customer_id', $customer->id);
        } elseif ($user) {
            $q->where('user_id', $user->id)->whereNull('customer_id');
        } elseif ($guestPhone) {
            $q->whereNull('customer_id')->whereNull('user_id')->where('guest_phone', $guestPhone);
        } else {
            return;
        }

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
        string $body,
        ?int $senderUserId = null,
        bool $automated = false,
        bool $advanceState = true,
    ): SupportMessage {
        $message = $conversation->messages()->create([
            'sender_type' => $senderType,
            'sender_user_id' => $senderUserId,
            'body' => trim($body),
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
            if (in_array($senderType, ['customer', 'guest'], true) && $conversation->assigned_to) {
                $this->maybeNotifyAssignedAgentOffline($conversation->fresh() ?? $conversation);
            }
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

    /** In-app CTA so the member can open Help Center and rate with stars. */
    public function notifyRatingRequest(SupportConversation $conversation): void
    {
        if (! $conversation->customer_id || ! $conversation->awaitsRating()) {
            return;
        }

        $customer = Customer::query()->find($conversation->customer_id);
        if (! $customer) {
            return;
        }

        $url = route('site.borrower.support', ['section' => 'history', 'chat' => 1]);
        app(\App\Services\NotificationService::class)->notifyInApp(
            $customer,
            __('borrower.notifications.support_resolved_body'),
            'support',
            'support_rating_request',
            __('borrower.notifications.support_resolved_title'),
            $url,
            null,
            [
                'title_key' => 'borrower.notifications.support_resolved_title',
                'body_key' => 'borrower.notifications.support_resolved_body',
                'params' => [],
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
        $rating = max(1, min(5, $rating));
        $conversation->update([
            'rating' => $rating,
            'rated_at' => now(),
            'resolution_note' => $comment
                ? trim((string) $conversation->resolution_note."\nRating note: ".$comment)
                : $conversation->resolution_note,
        ]);

        return $conversation->fresh();
    }

    public function waitingAcknowledgement(): string
    {
        if (str_starts_with(app()->getLocale(), 'en')) {
            return 'We received your message. Our Support team will help you shortly. You can add more details here while you wait.';
        }

        return 'Tumepokea ujumbe wako. Timu yetu ya Usaidizi itakuhudumia hivi karibuni. Unaweza kuongeza maelezo mengine hapa wakati unasubiri.';
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
        return $conversation->messages()->orderBy('id')->get()->map(fn (SupportMessage $m) => [
            'id' => (int) $m->id,
            'role' => in_array($m->sender_type, ['staff', 'bot'], true) ? 'bot' : 'user',
            'sender_type' => (string) $m->sender_type,
            'text' => (string) $m->body,
            'at' => $m->created_at?->toIso8601String(),
            'time' => $m->created_at ? format_app_datetime($m->created_at, 'H:i') : null,
        ])->all();
    }

    /**
     * Member/Partner chat header presence (does not affect message delivery).
     * Agent identity is shown only after Accept (assigned/active), never on waiting queue.
     *
     * @return array{assigned_to:?int, agent_first_name:?string, status:string, presence:string, desk_label:string}
     */
    public function memberChatPresence(?SupportConversation $conversation): array
    {
        if (! $conversation) {
            return [
                'assigned_to' => null,
                'agent_first_name' => null,
                'status' => self::STATUS_WAITING,
                'presence' => 'online',
                'desk_label' => 'Waiting for support',
            ];
        }

        $conversation->loadMissing('assignedTo');
        $accepted = $conversation->assigned_to
            && in_array($conversation->status, [self::STATUS_ASSIGNED, self::STATUS_ACTIVE], true);
        $agentFirst = $accepted
            ? ($this->personFirstName((string) ($conversation->assignedTo?->name ?? '')) ?: null)
            : null;

        return [
            'assigned_to' => $accepted ? (int) $conversation->assigned_to : null,
            'agent_first_name' => $agentFirst,
            'status' => (string) $conversation->status,
            'presence' => $agentFirst ? 'assigned' : 'online',
            'desk_label' => $agentFirst ? 'Agent assigned' : 'Waiting for support',
        ];
    }

    /** Human-readable desk state for staff UI. */
    public function deskState(SupportConversation $conversation): string
    {
        return match (true) {
            $conversation->status === self::STATUS_CLOSED => 'Closed',
            $conversation->status === self::STATUS_RESOLVED => 'Resolved',
            $conversation->status === self::STATUS_ACTIVE => 'Active',
            (bool) $conversation->assigned_to => 'Assigned',
            default => 'Waiting',
        };
    }
}
