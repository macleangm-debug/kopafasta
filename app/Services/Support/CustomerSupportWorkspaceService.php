<?php

namespace App\Services\Support;

use App\Models\SupportConversation;
use App\Models\SupportTicket;
use App\Models\SupportTicketEvent;
use App\Models\User;
use App\Services\AdminRoleViewService;
use App\Services\RoleService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Support workspace foundation (Customer Support + Partner Support capabilities).
 * Reuses SupportConversation + SupportTicket. Does not invent a second ticket/chat engine.
 */
class CustomerSupportWorkspaceService
{
    public const ROLE_KEY = 'agent';

    public const WORKSPACE_KEY = AdminRoleViewService::WORKSPACE_SUPPORT;

    /** @var list<string> */
    public const ROLE_KEYS = AdminRoleViewService::SUPPORT_ROLE_KEYS;

    public const AVAILABILITY_KEY = 'support_availability';

    public const AVAILABILITY_STATES = ['online', 'away', 'offline'];

    public function __construct(
        private readonly AdminRoleViewService $roleView,
        private readonly RoleService $roles,
    ) {}

    public function isSupportRoleKey(?string $roleKey): bool
    {
        return in_array((string) $roleKey, [self::WORKSPACE_KEY, ...self::ROLE_KEYS], true);
    }

    /**
     * True when Account/Role is viewing Support, a support agent is signed in,
     * or the request is already inside the support workspace routes.
     */
    public function inSupportShell(?User $viewer = null): bool
    {
        $ctx = $this->roleView->active();
        if ($ctx && $this->isSupportRoleKey($ctx['role_key'] ?? null)) {
            return true;
        }

        if (request()->routeIs('admin.support.*')) {
            return true;
        }

        $viewer ??= auth('admin')->user() ?? auth()->user();
        if (! $viewer) {
            return false;
        }

        if ($this->roles->hasPermissionBypass($viewer) || $viewer->hasRole('manager') || $viewer->hasRole('super_admin')) {
            return false;
        }

        return $viewer->hasRole(self::ROLE_KEY) || $viewer->hasRole('partner_support');
    }

    public function homeUrl(): string
    {
        return route('admin.support.home');
    }

    public function isTeamView(): bool
    {
        $ctx = $this->roleView->active();
        if ($ctx && $this->isSupportRoleKey($ctx['role_key'] ?? null)) {
            return ($ctx['filter_mode'] ?? 'all') !== 'staff' || empty($ctx['subject_id']);
        }

        return false;
    }

    /**
     * Selected support staff when filtered; null means All Support (aggregate).
     */
    public function actingAgent(?User $viewer = null): ?User
    {
        $ctx = $this->roleView->active();
        if ($ctx && $this->isSupportRoleKey($ctx['role_key'] ?? null)) {
            if (($ctx['filter_mode'] ?? 'all') === 'staff' && ! empty($ctx['subject_id'])) {
                return User::query()->find((int) $ctx['subject_id']);
            }

            // Team/aggregate view — no single agent.
            return null;
        }

        $viewer ??= auth('admin')->user() ?? auth()->user();
        if ($viewer && ($viewer->hasRole(self::ROLE_KEY) || $viewer->hasRole('partner_support'))) {
            return $viewer;
        }

        return null;
    }

    /** @return list<array{id: int, name: string, subtitle: string}> */
    public function staffOptions(): array
    {
        return $this->roleView->workspaceStaffOptions();
    }

    public function availability(?User $agent = null): string
    {
        $agent ??= $this->actingAgent();
        if (! $agent) {
            return 'offline';
        }

        $state = (string) data_get($agent->preferences, self::AVAILABILITY_KEY, 'offline');

        return in_array($state, self::AVAILABILITY_STATES, true) ? $state : 'offline';
    }

    public function setAvailability(User $agent, string $state, ?User $actor = null): string
    {
        $state = in_array($state, self::AVAILABILITY_STATES, true) ? $state : 'offline';
        $prefs = is_array($agent->preferences) ? $agent->preferences : [];
        $prefs[self::AVAILABILITY_KEY] = $state;
        $agent->forceFill(['preferences' => $prefs])->save();

        return $state;
    }

    /**
     * @return list<array{label: string, route: string, active_prefixes: list<string>}>
     */
    public function navItems(): array
    {
        return [
            [
                'label' => 'Home',
                'route' => 'admin.support.home',
                'active_prefixes' => ['admin.support.home'],
            ],
            [
                'label' => 'Inbox',
                'route' => 'admin.support.inbox',
                'active_prefixes' => ['admin.support.inbox', 'admin.support-chats.'],
            ],
            [
                'label' => 'Cases',
                'route' => 'admin.support.cases',
                'active_prefixes' => ['admin.support.cases', 'admin.support-tickets.'],
            ],
            [
                'label' => 'Members',
                'route' => 'admin.support.members',
                'active_prefixes' => ['admin.support.members', 'admin.customers.'],
            ],
            [
                'label' => 'Reports',
                'route' => 'admin.support.performance',
                'active_prefixes' => ['admin.support.performance', 'admin.support.reports'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function dashboard(?User $agent = null): array
    {
        $team = $agent === null && $this->isTeamView();
        if (! $team && $agent === null) {
            $agent = $this->actingAgent();
        }
        $agentId = $team ? null : $agent?->id;

        $waiting = $this->waitingConversations();
        $mineOrOpen = $team
            ? $this->openConversations()
            : $this->myConversations($agentId);
        $queue = $waiting->merge($mineOrOpen)->unique('id')->sortByDesc(function ($row) {
            return $row->last_message_at?->timestamp ?? $row->updated_at?->timestamp ?? 0;
        })->values()->take(12);

        $ticketsNeedingAttention = $this->ticketsNeedingAttention($agentId);
        $openTicketCount = $team
            ? SupportTicket::query()->whereIn('status', ['open', 'in_progress'])->count()
            : $this->myOpenTickets($agentId)->count();

        return [
            'agent' => $agent,
            'team_view' => $team || $agentId === null,
            'staff_options' => $this->staffOptions(),
            'selected_staff_id' => $agentId,
            'availability' => $agent ? $this->availability($agent) : null,
            'agents_online' => $this->agentsOnlineCount(),
            'counters' => [
                'unread' => $this->unreadCount($agentId),
                'waiting' => $waiting->count(),
                'assigned_to_me' => $mineOrOpen->count(),
                'open_tickets' => $openTicketCount,
                'overdue' => null, // gap: no due_at / SLA clock on support_tickets
            ],
            'queue' => $queue->map(fn (SupportConversation $c) => $this->serializeConversation($c))->all(),
            'tickets' => $ticketsNeedingAttention->map(fn (SupportTicket $t) => $this->serializeTicket($t))->all(),
            'performance' => $this->performanceSnapshot($agentId, 'today'),
            'gaps' => $this->infrastructureGaps(),
        ];
    }

    public function agentsOnlineCount(): int
    {
        return User::query()
            ->where('is_active', true)
            ->get()
            ->filter(function (User $user) {
                if (! $user->hasRole('agent') && ! $user->hasRole('partner_support')) {
                    return false;
                }

                return $this->availability($user) === 'online';
            })
            ->count();
    }

    /** @return Collection<int, SupportConversation> */
    public function openConversations(): Collection
    {
        return SupportConversation::query()
            ->with(['customer', 'user', 'assignedTo', 'messages' => fn ($q) => $q->latest('id')->limit(1)])
            ->whereNotIn('status', ['closed', 'resolved'])
            ->latest('last_message_at')
            ->limit(40)
            ->get();
    }

    /** @return array<string, mixed> */
    public function performanceSnapshot(?int $agentId, string $range = 'today'): array
    {
        [$from, $label] = $this->rangeBounds($range);

        $resolvedQuery = SupportTicket::query()
            ->whereIn('status', ['resolved', 'closed'])
            ->where('resolved_at', '>=', $from);
        $assignedQuery = SupportTicket::query()->where('created_at', '>=', $from);
        $conversationsQuery = SupportConversation::query()->where('updated_at', '>=', $from);
        $backlogQuery = SupportTicket::query()->whereIn('status', ['open', 'in_progress']);
        $escalationsQuery = SupportTicketEvent::query()
            ->where('event', 'escalated')
            ->where('created_at', '>=', $from);

        if ($agentId) {
            $resolvedQuery->where('assigned_to', $agentId);
            $assignedQuery->where('assigned_to', $agentId);
            $conversationsQuery->where('assigned_to', $agentId);
            $backlogQuery->where('assigned_to', $agentId);
            $escalationsQuery->whereHas('ticket', fn ($q) => $q->where('assigned_to', $agentId));
        }

        return [
            'range' => $range,
            'range_label' => $label,
            'resolved' => $resolvedQuery->count(),
            'tickets_assigned' => $assignedQuery->count(),
            'conversations_handled' => $conversationsQuery->count(),
            'open_backlog' => $backlogQuery->count(),
            'escalations' => $escalationsQuery->count(),
            'avg_first_response_minutes' => null,
            'avg_resolution_minutes' => null,
            'first_contact_resolution' => null,
            'sla_met' => null,
            'customer_rating' => null,
            'gaps' => [
                'avg_first_response' => 'No first-response timer recorded on tickets/conversations.',
                'avg_resolution' => 'Resolution duration not derived yet (only resolved_at exists).',
                'first_contact_resolution' => 'FCR not recorded.',
                'sla_met' => 'No SLA due clock on support_tickets.',
                'customer_rating' => 'CSAT stored on support_ticket_ratings when member rates after resolve.',
                'overdue' => 'No due_at on support_tickets — Overdue counter withheld.',
            ],
        ];
    }

    /** @return list<string> */
    public function infrastructureGaps(): array
    {
        return [
            'Department workspaces for escalated cases are not built in this pass (Support remains customer contact).',
            'Overdue / SLA / first-response timers: not stored — shown as gaps on Reports.',
            'Agent availability: stored on user preferences only; round-robin does not yet filter Offline agents.',
        ];
    }

    /** @return Collection<int, SupportConversation> */
    public function waitingConversations(): Collection
    {
        return SupportConversation::query()
            ->with(['customer', 'user', 'assignedTo', 'messages' => fn ($q) => $q->latest('id')->limit(1)])
            ->where(function ($q) {
                $q->where('needs_human', true)
                    ->orWhere(function ($inner) {
                        $inner->whereNull('assigned_to')
                            ->whereIn('status', ['open', 'waiting', 'needs_human']);
                    });
            })
            ->whereNotIn('status', ['closed', 'resolved'])
            ->latest('last_message_at')
            ->limit(40)
            ->get();
    }

    /** @return Collection<int, SupportConversation> */
    public function myConversations(?int $agentId): Collection
    {
        if (! $agentId) {
            return collect();
        }

        return SupportConversation::query()
            ->with(['customer', 'user', 'assignedTo', 'messages' => fn ($q) => $q->latest('id')->limit(1)])
            ->where('assigned_to', $agentId)
            ->whereNotIn('status', ['closed', 'resolved'])
            ->latest('last_message_at')
            ->limit(40)
            ->get();
    }

    /** @return Collection<int, SupportTicket> */
    public function myOpenTickets(?int $agentId): Collection
    {
        if (! $agentId) {
            return collect();
        }

        return SupportTicket::query()
            ->with('customer')
            ->where('assigned_to', $agentId)
            ->whereIn('status', ['open', 'in_progress'])
            ->latest()
            ->limit(40)
            ->get();
    }

    /** @return Collection<int, SupportTicket> */
    public function ticketsNeedingAttention(?int $agentId): Collection
    {
        $query = SupportTicket::query()
            ->with('customer')
            ->whereIn('status', ['open', 'in_progress'])
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->latest()
            ->limit(12);

        if ($agentId) {
            $query->where('assigned_to', $agentId);
        }

        return $query->get();
    }

    public function unreadCount(?int $agentId): int
    {
        // Approximate: waiting for human + my conversations whose latest message is not staff.
        $waiting = SupportConversation::query()
            ->where('needs_human', true)
            ->whereNotIn('status', ['closed', 'resolved'])
            ->count();

        if (! $agentId) {
            return $waiting;
        }

        $mineNeedingReply = SupportConversation::query()
            ->where('assigned_to', $agentId)
            ->whereNotIn('status', ['closed', 'resolved', 'replied'])
            ->where('needs_human', true)
            ->count();

        return $waiting + $mineNeedingReply;
    }

    /** @return array<string, mixed> */
    public function serializeConversation(SupportConversation $conversation): array
    {
        // Prefer last member/guest message for list preview so system acks don't hide "Habari".
        $lastCustomer = $conversation->messages()
            ->whereIn('sender_type', ['customer', 'guest'])
            ->latest('id')
            ->first();
        $last = $lastCustomer
            ?: $conversation->messages()->latest('id')->first();

        $isMember = (bool) $conversation->customer_id;
        $name = $isMember
            ? trim(($conversation->customer?->first_name.' '.$conversation->customer?->last_name) ?: '')
            : (string) ($conversation->guest_name ?: ($conversation->user?->name ?: ''));

        if ($name === '') {
            $name = $isMember ? 'Member' : 'Guest / Non-member';
        }

        $waitingSince = $conversation->last_message_at ?? $conversation->updated_at ?? $conversation->created_at;
        $unread = $conversation->messages()
            ->whereNull('read_at')
            ->whereIn('sender_type', ['customer', 'guest'])
            ->count();

        $preview = preg_replace('/\s+/', ' ', (string) ($last?->body ?? '')) ?? '';
        $desk = app(SupportConversationService::class)->deskState($conversation);

        return [
            'id' => $conversation->id,
            'name' => $name,
            'is_member' => $isMember,
            'guest_label' => $isMember ? null : 'Guest / Non-member',
            'topic' => $conversation->topic,
            'channel' => $conversation->channel,
            'preview' => \Illuminate\Support\Str::limit($preview, 72),
            'status' => (string) $conversation->status,
            'desk_state' => $desk,
            'needs_human' => (bool) $conversation->needs_human,
            'unread' => $unread,
            'assigned_to' => $conversation->assigned_to,
            'waiting_label' => ($desk === 'Waiting' && $waitingSince)
                ? $waitingSince->diffForHumans(null, true).' waiting'
                : null,
            'url' => route('admin.support.inbox.show', $conversation),
            'member_url' => $conversation->customer_id
                ? route('admin.customers.show', $conversation->customer_id)
                : null,
        ];
    }

    /** @return array<string, mixed> */
    public function serializeTicket(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'number' => (string) $ticket->ticket_number,
            'subject' => (string) ($ticket->subject ?: $ticket->category ?: 'Case'),
            'status' => (string) $ticket->status,
            'priority' => (string) $ticket->priority,
            'contact' => $ticket->contactLabel(),
            'is_guest' => $ticket->customer_id === null,
            'url' => route('admin.support-tickets.show', $ticket),
        ];
    }

    /** @return array{0: Carbon, 1: string} */
    private function rangeBounds(string $range): array
    {
        return match ($range) {
            '7d' => [now()->subDays(7)->startOfDay(), '7 days'],
            '30d' => [now()->subDays(30)->startOfDay(), '30 days'],
            default => [now()->startOfDay(), 'Today'],
        };
    }
}
