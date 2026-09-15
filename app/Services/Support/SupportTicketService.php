<?php

namespace App\Services\Support;

use App\Models\Customer;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\SupportTicketEvent;
use App\Models\User;
use App\Services\AuditService;
use App\Support\SupportTaxonomy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupportTicketService
{
    public const SOURCES = [
        'admin',
        'public_feedback',
        'customer_portal',
        'complaint',
        'broken_page',
        'chatbot',
        'reward',
    ];

    public const EVENTS = [
        'created',
        'assigned',
        'opened',
        'response',
        'internal_note',
        'reassigned',
        'status_changed',
        'escalated',
        'resolved',
        'reopened',
    ];

    private const ROUND_ROBIN_KEY = 'support.round_robin_last_agent_id';

    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): SupportTicket
    {
        return DB::transaction(function () use ($payload) {
            $customerId = isset($payload['customer_id']) && $payload['customer_id'] !== ''
                ? (int) $payload['customer_id']
                : null;

            $contactKind = $payload['contact_kind']
                ?? ($customerId ? 'customer' : 'guest');

            $subject = SupportTaxonomy::resolveSubject(
                $payload['subject'] ?? null,
                $payload['subject_other'] ?? null,
            );

            $priority = (string) ($payload['priority'] ?? 'normal');
            if ($customerId && empty($payload['priority_locked'])) {
                $customer = Customer::query()->find($customerId);
                if ($customer && app(\App\Services\LoyaltyRedemptionService::class)->activePrioritySupport($customer)) {
                    $priority = 'urgent';
                }
            }

            $ticket = SupportTicket::create([
                'ticket_number' => $payload['ticket_number']
                    ?? ('TKT-'.now()->format('ymd').'-'.Str::upper(Str::random(4))),
                'customer_id' => $customerId,
                'guest_name' => $payload['guest_name'] ?? null,
                'guest_email' => $payload['guest_email'] ?? null,
                'guest_phone' => $payload['guest_phone'] ?? null,
                'source' => $payload['source'] ?? 'admin',
                'contact_kind' => $contactKind,
                'assigned_to' => $payload['assigned_to'] ?? null,
                'subject' => $subject,
                'description' => (string) ($payload['description'] ?? ''),
                'priority' => $priority,
                'status' => $payload['status'] ?? 'open',
                'category' => $payload['category'] ?? 'general',
                'resolved_at' => $payload['resolved_at'] ?? null,
                'resolution_notes' => $payload['resolution_notes'] ?? null,
            ]);

            $actor = ($payload['actor'] ?? null) instanceof User ? $payload['actor'] : null;

            $this->addEvent($ticket, 'created', $actor, null, [
                'source' => $ticket->source,
                'contact_kind' => $ticket->contact_kind,
                'category' => $ticket->category,
            ]);

            if (empty($payload['assigned_to'])) {
                $this->autoAssign($ticket, $actor);
            } else {
                $this->addEvent($ticket, 'assigned', $actor, null, [
                    'assigned_to' => (int) $payload['assigned_to'],
                    'mode' => 'manual',
                ]);
            }

            return $ticket->fresh(['assignee', 'customer', 'events']);
        });
    }

    /**
     * Round-robin among active Support Agents (role=agent).
     * Future: skill/category rules, language, VIP lane — hook here without a second engine.
     */
    public function autoAssign(SupportTicket $ticket, ?User $actor = null): SupportTicket
    {
        if ($ticket->assigned_to) {
            return $ticket;
        }

        $agents = $this->activeAgents();
        if ($agents->isEmpty()) {
            return $ticket;
        }

        $lastId = (int) Setting::get(self::ROUND_ROBIN_KEY, 0);
        $next = $agents->first(fn (User $u) => $u->id > $lastId) ?? $agents->first();

        $ticket->update(['assigned_to' => $next->id]);
        Setting::set(self::ROUND_ROBIN_KEY, $next->id);

        $this->addEvent($ticket, 'assigned', $actor, null, [
            'assigned_to' => $next->id,
            'mode' => 'round_robin',
        ]);

        return $ticket->fresh(['assignee']);
    }

    public function reassign(SupportTicket $ticket, int $userId, ?User $actor = null, ?string $body = null): SupportTicket
    {
        $from = $ticket->assigned_to;
        $ticket->update(['assigned_to' => $userId]);

        $this->addEvent($ticket, 'reassigned', $actor, $body, [
            'from' => $from,
            'to' => $userId,
        ]);

        return $ticket->fresh(['assignee']);
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function addEvent(
        SupportTicket $ticket,
        string $event,
        ?User $actor = null,
        ?string $body = null,
        ?array $meta = null,
    ): SupportTicketEvent {
        $row = SupportTicketEvent::create([
            'support_ticket_id' => $ticket->id,
            'actor_user_id' => $actor?->id,
            'event' => $event,
            'body' => $body,
            'meta' => $meta,
        ]);

        $this->audit->logTicketEvent($actor, $ticket, $event, $meta ?? [], $body);

        return $row;
    }

    public function linkCustomer(SupportTicket $ticket, int $customerId, ?User $actor = null): SupportTicket
    {
        $ticket->update([
            'customer_id' => $customerId,
            'contact_kind' => 'customer',
        ]);

        $this->addEvent($ticket, 'opened', $actor, 'Linked guest ticket to customer', [
            'customer_id' => $customerId,
        ]);

        return $ticket->fresh(['customer']);
    }

    public function transitionStatus(
        SupportTicket $ticket,
        string $status,
        ?User $actor = null,
        ?string $body = null,
        ?array $extra = null,
    ): SupportTicket {
        $from = $ticket->status;
        if ($from === $status) {
            return $ticket;
        }

        $updates = ['status' => $status];
        if (in_array($status, ['resolved', 'closed'], true) && empty($ticket->resolved_at)) {
            $updates['resolved_at'] = now();
        }
        if ($status === 'open' && in_array($from, ['resolved', 'closed'], true)) {
            $updates['resolved_at'] = null;
        }
        if (array_key_exists('resolution_notes', $extra ?? [])) {
            $updates['resolution_notes'] = $extra['resolution_notes'];
        }
        if (array_key_exists('priority', $extra ?? [])) {
            $updates['priority'] = $extra['priority'];
        }

        $ticket->update($updates);

        $event = match (true) {
            $status === 'resolved' => 'resolved',
            in_array($from, ['resolved', 'closed'], true) && ! in_array($status, ['resolved', 'closed'], true) => 'reopened',
            ($extra['escalated'] ?? false) === true || ($extra['priority'] ?? null) === 'urgent' => 'escalated',
            default => 'status_changed',
        };

        $this->addEvent($ticket, $event, $actor, $body, [
            'from' => $from,
            'to' => $status,
            ...(is_array($extra) ? $extra : []),
        ]);

        return $ticket->fresh();
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    public function activeAgents()
    {
        return User::query()
            ->where('role', 'agent')
            ->where(function ($q) {
                $q->where('is_active', true)->orWhereNull('is_active');
            })
            ->where(function ($q) {
                $q->whereNull('locked_until')->orWhere('locked_until', '<=', now());
            })
            ->orderBy('id')
            ->get();
    }
}
