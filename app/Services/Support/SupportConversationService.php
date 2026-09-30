<?php

namespace App\Services\Support;

use App\Models\Customer;
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

    public function __construct(
        private readonly AuditService $audit,
    ) {}

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
                'status' => $conversation->assigned_to ? self::STATUS_ASSIGNED : self::STATUS_WAITING,
                'topic' => $topic ?: $conversation->topic,
                'channel' => $channel ?: ($conversation->channel ?: 'web_chat'),
                'last_message_at' => now(),
            ]);

            $sender = ($customer || $user) ? 'customer' : 'guest';
            $this->appendMessage($conversation, $sender, $body, $user?->id, false, true);

            $hasWaitingAck = $conversation->messages()
                ->where('is_automated', true)
                ->where('body', 'like', 'Tumepokea ujumbe%')
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
                ]);
            }

            return $conversation->fresh(['customer', 'user', 'messages']);
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
                    return $this->normalizeLegacyStatus($existing);
                }
            }

            return SupportConversation::query()->create([
                'channel' => $channel ?: 'web_chat',
                'status' => self::STATUS_WAITING,
                'needs_human' => true,
                'guest_name' => $guestName,
                'guest_phone' => $guestPhone,
                'last_message_at' => now(),
            ]);
        }

        $existing = $query->first();
        if ($existing) {
            return $this->normalizeLegacyStatus($existing);
        }

        return SupportConversation::query()->create([
            'customer_id' => $customer?->id,
            'user_id' => $user?->id,
            'channel' => $channel ?: 'web_chat',
            'status' => self::STATUS_WAITING,
            'needs_human' => true,
            'guest_name' => $customer ? null : $guestName,
            'guest_phone' => $customer ? null : $guestPhone,
            'last_message_at' => now(),
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
     */
    public function accept(SupportConversation $conversation, User $agent): SupportConversation
    {
        $firstAssign = ! $conversation->assigned_to || (int) $conversation->assigned_to !== (int) $agent->id;

        $conversation->update([
            'assigned_to' => $agent->id,
            'status' => self::STATUS_ASSIGNED,
            'needs_human' => true,
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
                if ($senderUserId && ! $conversation->assigned_to) {
                    $updates['assigned_to'] = $senderUserId;
                }
            } elseif (in_array($senderType, ['customer', 'guest'], true)) {
                $updates['needs_human'] = true;
                $updates['status'] = $conversation->assigned_to ? self::STATUS_ASSIGNED : self::STATUS_WAITING;
            } else {
                $updates['last_message_at'] = now();
            }
            $conversation->update($updates);
        } else {
            $conversation->update(['last_message_at' => now()]);
        }

        return $message;
    }

    public function resolve(SupportConversation $conversation, ?User $actor = null, ?string $note = null): SupportConversation
    {
        if (in_array($conversation->status, [self::STATUS_RESOLVED, self::STATUS_CLOSED], true)) {
            return $conversation;
        }

        $body = app(SupportQuickReplyService::class)->compose('resolved', 'sw');
        if ($note) {
            $body = trim($body)."\n\n".$note;
        }

        $this->appendMessage($conversation, 'staff', $body, $actor?->id, true, false);

        $conversation->update([
            'status' => self::STATUS_RESOLVED,
            'needs_human' => false,
            'last_message_at' => now(),
        ]);

        return $conversation->fresh();
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

    public function waitingAcknowledgement(): string
    {
        return 'Tumepokea ujumbe wako. Timu yetu ya Huduma kwa Wateja imejulishwa. Mtoa huduma atakapochukua mazungumzo haya, utaendelea kuwasiliana naye hapa.';
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
            'time' => $m->created_at?->format('H:i'),
        ])->all();
    }

    /** Human-readable desk state for staff UI. */
    public function deskState(SupportConversation $conversation): string
    {
        return match (true) {
            in_array($conversation->status, [self::STATUS_RESOLVED, self::STATUS_CLOSED], true) => 'Resolved',
            $conversation->status === self::STATUS_ACTIVE => 'Active',
            (bool) $conversation->assigned_to => 'Assigned',
            default => 'Waiting',
        };
    }
}
