<?php

namespace App\Services\Support;

use App\Models\Customer;
use App\Models\Setting;
use App\Models\SupportConversation;
use App\Models\SupportTicket;
use App\Models\SupportTicketEvent;
use App\Models\SupportTicketRating;
use App\Models\User;
use App\Services\AuditService;
use App\Support\SupportTaxonomy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
        'specialist_response',
        'resolved',
        'reopened',
    ];

    private const ROUND_ROBIN_KEY = 'support.round_robin_last_agent_id';

    public const TICKET_PREFIX_KEY = 'support.ticket_number_prefix';

    public const TICKET_PREFIX_DEFAULT = 'KPF-TKT';

    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /**
     * Settings-backed readable ticket number, e.g. KPF-TKT-A7K4Q2.
     * New references are alphanumeric; historical sequential numbers are unchanged.
     */
    public function nextTicketNumber(?string $explicit = null): string
    {
        $explicit = trim((string) $explicit);
        if ($explicit !== '') {
            return $explicit;
        }

        $prefix = strtoupper(trim((string) Setting::get(self::TICKET_PREFIX_KEY, self::TICKET_PREFIX_DEFAULT)));
        if ($prefix === '' || $prefix === 'SUP') {
            $prefix = self::TICKET_PREFIX_DEFAULT;
        }

        return app(\App\Services\ReferenceNumberService::class)->prefixedReference(
            $prefix,
            6,
            fn (string $candidate) => SupportTicket::query()->where('ticket_number', $candidate)->exists(),
        );
    }

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
            if ($contactKind === 'member') {
                $contactKind = 'customer';
            }

            $subject = SupportTaxonomy::resolveSubject(
                $payload['subject'] ?? null,
                $payload['subject_other'] ?? null,
            );
            $category = SupportTaxonomy::resolveCategory(
                $payload['category'] ?? null,
                $payload['category_other'] ?? null,
            );

            $priority = isset($payload['priority']) && trim((string) $payload['priority']) !== ''
                ? (string) $payload['priority']
                : SupportTaxonomy::defaultPriorityFor($category);
            if ($customerId && empty($payload['priority_locked'])) {
                $customer = Customer::query()->find($customerId);
                if ($customer && app(\App\Services\LoyaltyRedemptionService::class)->activePrioritySupport($customer)) {
                    $priority = 'urgent';
                }
            }

            // Snapshot Issue SLA at create — later Settings edits never rewrite these.
            $targetMinutes = isset($payload['sla_target_minutes'])
                ? max(15, (int) $payload['sla_target_minutes'])
                : SupportTaxonomy::targetMinutesFor($category);
            $approachingPct = isset($payload['sla_approaching_pct'])
                ? max(50, min(95, (int) $payload['sla_approaching_pct']))
                : SupportTaxonomy::approachingPercentFor($category);
            $slaDueAt = $payload['sla_due_at'] ?? now()->addMinutes($targetMinutes);

            $ticket = SupportTicket::create([
                'ticket_number' => $this->nextTicketNumber(
                    isset($payload['ticket_number']) ? (string) $payload['ticket_number'] : null
                ),
                'customer_id' => $customerId,
                'support_conversation_id' => $payload['support_conversation_id'] ?? null,
                'related_type' => $payload['related_type'] ?? null,
                'related_id' => $payload['related_id'] ?? null,
                'guest_name' => $payload['guest_name'] ?? null,
                'guest_email' => $payload['guest_email'] ?? null,
                'guest_phone' => $payload['guest_phone'] ?? null,
                'source' => $payload['source'] ?? 'admin',
                'contact_kind' => $contactKind,
                'assigned_to' => $payload['assigned_to'] ?? null,
                'assigned_at' => ! empty($payload['assigned_to']) ? now() : null,
                'escalated_to_role' => $payload['escalated_to_role'] ?? null,
                'subject' => $subject,
                'description' => (string) ($payload['description'] ?? ''),
                'priority' => $priority,
                'status' => $payload['status'] ?? 'open',
                'category' => $category !== '' ? $category : 'other',
                'resolved_at' => $payload['resolved_at'] ?? null,
                'resolution_notes' => $payload['resolution_notes'] ?? null,
                'resolution_type' => $payload['resolution_type'] ?? null,
                'sla_due_at' => $slaDueAt,
                'sla_target_minutes' => $targetMinutes,
                'sla_approaching_pct' => $approachingPct,
            ]);

            $actor = ($payload['actor'] ?? null) instanceof User ? $payload['actor'] : null;

            $this->addEvent($ticket, 'created', $actor, null, [
                'source' => $ticket->source,
                'contact_kind' => $ticket->contact_kind,
                'category' => $ticket->category,
                'sla_snapshot' => [
                    'priority' => $priority,
                    'target_minutes' => $targetMinutes,
                    'approaching_pct' => $approachingPct,
                    'due_at' => $ticket->sla_due_at?->toIso8601String(),
                ],
            ]);

            if (empty($payload['assigned_to'])) {
                $this->autoAssign($ticket, $actor);
            } else {
                $this->addEvent($ticket, 'assigned', $actor, null, [
                    'assigned_to' => (int) $payload['assigned_to'],
                    'mode' => 'manual',
                ]);
            }

            $ticket = $ticket->fresh(['assignee', 'customer', 'events']);
            if ($ticket && $ticket->assigned_to) {
                $this->addEvent($ticket, 'opened', $actor, 'In agent queue', [
                    'assigned_to' => $ticket->assigned_to,
                    'queue' => 'agent',
                ]);
            }

            return $ticket;
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
            $now = now();
            $updates['resolved_at'] = $now;
            if ($ticket->time_to_resolve_minutes === null && $ticket->created_at) {
                $updates['time_to_resolve_minutes'] = max(0, (int) $ticket->created_at->diffInMinutes($now));
            }
        }
        if ($status === 'open' && in_array($from, ['resolved', 'closed'], true)) {
            $updates['resolved_at'] = null;
            $updates['time_to_resolve_minutes'] = null;
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

    public function addInternalNote(SupportTicket $ticket, string $body, ?User $actor = null): SupportTicketEvent
    {
        return $this->addEvent($ticket, 'internal_note', $actor, trim($body));
    }

    /**
     * Escalate to an internal department/role. Support remains customer contact.
     */
    public function escalate(
        SupportTicket $ticket,
        string $role,
        string $reason,
        ?User $actor = null,
        ?string $internalNote = null,
        bool $notifyMember = true,
    ): SupportTicket {
        $ticket->update([
            'escalated_to_role' => $role,
            'escalated_at' => now(),
            'status' => in_array($ticket->status, ['resolved', 'closed'], true) ? 'in_progress' : $ticket->status,
            'priority' => $ticket->priority === 'low' ? 'high' : $ticket->priority,
        ]);

        $this->addEvent($ticket, 'escalated', $actor, $reason, [
            'escalated_to_role' => $role,
            'internal_note' => $internalNote,
            'customer_contact_stays_with_support' => true,
        ]);

        if ($internalNote) {
            $this->addInternalNote($ticket, $internalNote, $actor);
        }

        if ($notifyMember && $ticket->support_conversation_id) {
            $conversation = SupportConversation::query()->find($ticket->support_conversation_id);
            if ($conversation) {
                $body = app(SupportQuickReplyService::class)->compose('escalated', 'sw');
                app(SupportConversationService::class)->appendMessage(
                    $conversation,
                    'staff',
                    $body,
                    $actor?->id,
                    true,
                    false,
                );
            }
        }

        return $ticket->fresh(['assignee', 'customer', 'conversation', 'events']);
    }

    /**
     * Specialist (Credit etc.) internal response — never auto-sent to the member.
     * Support remains customer-facing and is flagged to follow up.
     */
    public function addSpecialistResponse(
        SupportTicket $ticket,
        string $body,
        ?User $actor = null,
    ): SupportTicketEvent {
        return $this->addEvent($ticket, 'specialist_response', $actor, trim($body), [
            'visible_to_customer' => false,
            'notify_support' => true,
            'awaiting_support_follow_up' => true,
            'escalated_to_role' => $ticket->escalated_to_role,
        ]);
    }

    /** Cases escalated to a role that Credit (etc.) can later consume as a queue. */
    public function escalationsForRole(string $role)
    {
        return SupportTicket::query()
            ->with(['customer', 'assignee'])
            ->where('escalated_to_role', $role)
            ->whereNotIn('status', ['resolved', 'closed'])
            ->latest('updated_at');
    }

    /**
     * Resolve case, notify member via linked conversation, optionally invite CSAT.
     *
     * @param  array{resolution_type?: string, resolution_notes?: string, invite_rating?: bool}  $payload
     */
    public function resolveCase(SupportTicket $ticket, array $payload, ?User $actor = null): SupportTicket
    {
        if (in_array($ticket->status, ['resolved', 'closed'], true)) {
            return $ticket;
        }

        $type = (string) ($payload['resolution_type'] ?? 'resolved');
        $notes = isset($payload['resolution_notes']) ? trim((string) $payload['resolution_notes']) : null;
        $now = now();
        $timeToResolve = $ticket->created_at
            ? max(0, (int) $ticket->created_at->diffInMinutes($now))
            : null;

        $ticket->update([
            'status' => 'resolved',
            'resolved_at' => $now,
            'resolution_type' => $type,
            'resolution_notes' => $notes,
            'time_to_resolve_minutes' => $timeToResolve,
        ]);

        $this->addEvent($ticket, 'resolved', $actor, $notes, [
            'resolution_type' => $type,
            'time_to_resolve_minutes' => $timeToResolve,
            'sla_met' => $ticket->sla_due_at ? $now->lte($ticket->sla_due_at) : null,
        ]);

        if ($ticket->support_conversation_id) {
            $conversation = SupportConversation::query()->find($ticket->support_conversation_id);
            if ($conversation && ! in_array($conversation->status, ['resolved', 'closed'], true)) {
                app(SupportConversationService::class)->resolve(
                    $conversation,
                    $actor,
                    $notes,
                    $type,
                    ($payload['invite_rating'] ?? true) === true,
                );
            } elseif ($conversation && ($payload['invite_rating'] ?? true) === true && ! $conversation->rating_requested_at) {
                $conversation->update(['rating_requested_at' => now()]);
                if ($conversation->customer_id) {
                    app(SupportConversationService::class)->notifyRatingRequest($conversation->fresh());
                }
            }
        }

        return $ticket->fresh(['assignee', 'customer', 'conversation', 'events', 'rating']);
    }

    public function recordRating(SupportTicket $ticket, int $rating, ?string $comment = null): SupportTicketRating
    {
        if ($ticket->rating) {
            return $ticket->rating;
        }

        $rating = max(1, min(5, $rating));

        return SupportTicketRating::query()->create([
            'support_ticket_id' => $ticket->id,
            'rating' => $rating,
            'comment' => $comment,
        ]);
    }

    /** @return list<string> */
    public function escalationRoles(): array
    {
        return [
            'screening',
            'credit',
            'accounting',
            'recovery',
            'partner_support',
            'technical',
            'manager',
            'admin',
        ];
    }

    /**
     * Settings-backed SLA due time from priority.
     * Keys: support.sla.minutes.{priority} — defaults urgent 120, high 240, normal 480, low 1440.
     */
    public function slaDueAtForPriority(string $priority): \Carbon\CarbonInterface
    {
        $defaults = [
            'urgent' => 120,
            'high' => 240,
            'normal' => 480,
            'low' => 1440,
        ];
        $minutes = (int) Setting::get(
            'support.sla.minutes.'.$priority,
            $defaults[$priority] ?? $defaults['normal']
        );

        return now()->addMinutes(max(15, $minutes));
    }

    /**
     * @return array{label: string, state: string, due_in: ?string, overdue_by: ?string}
     */
    public function slaStatus(SupportTicket $ticket): array
    {
        if (in_array($ticket->status, ['resolved', 'closed'], true)) {
            $elapsed = $ticket->resolved_at && $ticket->created_at
                ? $ticket->created_at->diff($ticket->resolved_at)->format('%H:%I')
                : null;

            return [
                'label' => 'Resolved'.($elapsed ? ' · '.$elapsed : ''),
                'state' => 'resolved',
                'due_in' => null,
                'overdue_by' => null,
            ];
        }

        $due = $ticket->sla_due_at;
        if (! $due) {
            return ['label' => 'On track', 'state' => 'on_track', 'due_in' => null, 'overdue_by' => null];
        }

        $now = now();
        if ($due->isPast()) {
            $over = $due->diff($now);

            return [
                'label' => 'Overdue by '.$over->format('%H:%I'),
                'state' => 'overdue',
                'due_in' => null,
                'overdue_by' => $over->format('%H:%I'),
            ];
        }

        $remaining = $now->diffInMinutes($due);
        $total = max(
            1,
            (int) ($ticket->sla_target_minutes
                ?: $ticket->created_at?->diffInMinutes($due)
                ?: 480)
        );
        $warningPct = (float) ($ticket->sla_approaching_pct
            ?: Setting::get('support.sla.warning_percent', 80));
        if (($remaining / $total) * 100 <= (100 - $warningPct) || $remaining <= 60) {
            return [
                'label' => 'Warning · Due in '.$now->diff($due)->format('%H:%I'),
                'state' => 'warning',
                'due_in' => $now->diff($due)->format('%H:%I'),
                'overdue_by' => null,
            ];
        }

        return [
            'label' => 'On track · Due in '.$now->diff($due)->format('%H:%I'),
            'state' => 'on_track',
            'due_in' => $now->diff($due)->format('%H:%I'),
            'overdue_by' => null,
        ];
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    public function activeAgents()
    {
        return User::query()
            ->where(function ($q) {
                $q->where('role', 'agent')
                    ->orWhereJsonContains('roles', 'agent');
            })
            ->where(function ($q) {
                $q->where('is_active', true)->orWhereNull('is_active');
            })
            ->where(function ($q) {
                $q->whereNull('locked_until')->orWhere('locked_until', '<=', now());
            })
            ->orderBy('id')
            ->get()
            ->filter(fn (User $u) => $u->hasRole('agent'))
            ->values();
    }

    /**
     * Supervisors may manually override round-robin on create/reassign.
     */
    public function canOverrideAssignment(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole('admin')
            || $user->hasRole('super_admin')
            || $user->hasRole('manager');
    }

    /**
     * Prior tickets sharing category/issue with overlapping subject keywords.
     * Returns safe fields only — no customer/guest PII.
     *
     * @param  SupportTicket|array{category?: ?string, subject?: ?string, subject_other?: ?string, exclude_id?: ?int}  $criteria
     * @return Collection<int, object{ticket_number: string, subject: string, category: ?string, status: string, resolution_summary: ?string}>
     */
    public function similarTickets(SupportTicket|array $criteria): Collection
    {
        if ($criteria instanceof SupportTicket) {
            $category = trim((string) $criteria->category);
            $subject = trim((string) $criteria->subject);
            $excludeId = $criteria->id;
        } else {
            $category = SupportTaxonomy::resolveCategory(
                $criteria['category'] ?? null,
                $criteria['category_other'] ?? null,
            );
            $subject = SupportTaxonomy::resolveSubject(
                $criteria['subject'] ?? null,
                $criteria['subject_other'] ?? null,
            );
            $excludeId = isset($criteria['exclude_id']) ? (int) $criteria['exclude_id'] : null;
        }

        if ($category === '' && $subject === '') {
            return collect();
        }

        $keywords = $this->subjectKeywords($subject);

        $candidates = SupportTicket::query()
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->when($category !== '', fn ($q) => $q->where('category', $category))
            ->orderByDesc('id')
            ->limit(40)
            ->get(['ticket_number', 'subject', 'category', 'status', 'resolution_notes', 'resolution_type']);

        return $candidates
            ->filter(function (SupportTicket $ticket) use ($subject, $keywords) {
                if (strcasecmp((string) $ticket->subject, $subject) === 0) {
                    return true;
                }
                if ($keywords === []) {
                    return false;
                }
                $hay = strtolower((string) $ticket->subject);
                foreach ($keywords as $keyword) {
                    if (str_contains($hay, $keyword)) {
                        return true;
                    }
                }

                return false;
            })
            ->take(5)
            ->map(fn (SupportTicket $ticket) => (object) [
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'category' => $ticket->category,
                'status' => $ticket->status,
                'resolution_summary' => $this->safeResolutionSummary($ticket),
            ])
            ->values();
    }

    /** @return list<string> */
    private function subjectKeywords(string $subject): array
    {
        $parts = preg_split('/[^a-zA-Z0-9]+/', strtolower($subject)) ?: [];
        $stop = ['a', 'an', 'the', 'and', 'or', 'of', 'to', 'for', 'in', 'on', 'at', 'by', 'other', 'issue'];

        return array_values(array_filter(
            $parts,
            fn ($part) => is_string($part) && strlen($part) >= 3 && ! in_array($part, $stop, true)
        ));
    }

    private function safeResolutionSummary(SupportTicket $ticket): ?string
    {
        $notes = trim((string) ($ticket->resolution_notes ?? ''));
        if ($notes !== '') {
            return mb_strlen($notes) > 120 ? mb_substr($notes, 0, 117).'…' : $notes;
        }

        $type = trim((string) ($ticket->resolution_type ?? ''));
        if ($type === '') {
            return null;
        }

        return ucfirst(str_replace('_', ' ', $type));
    }
}
