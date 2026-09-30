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
 */
class SupportConversationService
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /**
     * Speak to Support: create or resume open conversation and post the member message.
     */
    public function requestHuman(
        ?Customer $customer,
        ?User $user,
        string $body,
        ?string $topic = null,
        ?string $guestName = null,
        ?string $guestPhone = null,
    ): SupportConversation {
        $body = trim($body);
        if ($body === '') {
            throw new \InvalidArgumentException('Message body is required.');
        }

        return DB::transaction(function () use ($customer, $user, $body, $topic, $guestName, $guestPhone) {
            $conversation = $this->openConversationFor($customer, $user, $guestName, $guestPhone);

            $conversation->update([
                'needs_human' => true,
                'status' => $conversation->assigned_to ? 'assigned' : 'open',
                'topic' => $topic ?: $conversation->topic,
                'last_message_at' => now(),
            ]);

            $this->appendMessage($conversation, $customer || $user ? 'customer' : 'guest', $body, $user?->id);

            return $conversation->fresh(['customer', 'user', 'messages']);
        });
    }

    public function openConversationFor(
        ?Customer $customer,
        ?User $user,
        ?string $guestName = null,
        ?string $guestPhone = null,
    ): SupportConversation {
        $query = SupportConversation::query()
            ->whereNotIn('status', ['closed', 'resolved'])
            ->latest('id');

        if ($customer) {
            $query->where('customer_id', $customer->id);
        } elseif ($user) {
            $query->where('user_id', $user->id)->whereNull('customer_id');
        } else {
            // Guests: always start a fresh conversation for UAT clarity unless phone matches.
            if ($guestPhone) {
                $existing = SupportConversation::query()
                    ->whereNull('customer_id')
                    ->where('guest_phone', $guestPhone)
                    ->whereNotIn('status', ['closed', 'resolved'])
                    ->latest('id')
                    ->first();
                if ($existing) {
                    return $existing;
                }
            }

            return SupportConversation::query()->create([
                'channel' => 'web_chat',
                'status' => 'open',
                'needs_human' => true,
                'guest_name' => $guestName,
                'guest_phone' => $guestPhone,
                'last_message_at' => now(),
            ]);
        }

        $existing = $query->first();
        if ($existing) {
            return $existing;
        }

        return SupportConversation::query()->create([
            'customer_id' => $customer?->id,
            'user_id' => $user?->id,
            'channel' => 'web_chat',
            'status' => 'open',
            'needs_human' => true,
            'guest_name' => $customer ? null : $guestName,
            'guest_phone' => $customer ? null : $guestPhone,
            'last_message_at' => now(),
        ]);
    }

    public function appendMessage(
        SupportConversation $conversation,
        string $senderType,
        string $body,
        ?int $senderUserId = null,
        bool $automated = false,
    ): SupportMessage {
        $message = $conversation->messages()->create([
            'sender_type' => $senderType,
            'sender_user_id' => $senderUserId,
            'body' => trim($body),
            'is_automated' => $automated,
            'read_at' => $senderType === 'staff' ? now() : null,
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'needs_human' => $senderType !== 'staff',
            'status' => $senderType === 'staff' ? 'replied' : ($conversation->assigned_to ? 'assigned' : 'open'),
        ]);

        return $message;
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
}
