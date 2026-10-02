<?php

namespace App\Services\Support;

use App\Models\Setting;
use App\Models\SupportConversation;
use App\Models\SupportTicket;
use App\Models\SupportTicketEvent;
use App\Models\User;
use App\Services\AdminRoleViewService;
use App\Services\RoleService;
use App\Support\SupportTaxonomy;
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

    /** Sticky Support shell until Exit/Switch role — survives Tickets → admin.support-tickets.*. */
    public const SHELL_SESSION_KEY = 'support_shell_active';

    public function __construct(
        private readonly AdminRoleViewService $roleView,
        private readonly RoleService $roles,
    ) {}

    public function isSupportRoleKey(?string $roleKey): bool
    {
        return in_array((string) $roleKey, [self::WORKSPACE_KEY, ...self::ROLE_KEYS], true);
    }

    /**
     * True when Account/Role is viewing Support, sticky Support shell is active,
     * or a native Support staff user is signed in.
     *
     * Admin (permission bypass) must NOT be silently re-entered into Viewing: Support
     * merely by visiting admin.support.* — that trapped Exit → Admin.
     */
    public function inSupportShell(?User $viewer = null): bool
    {
        $viewer ??= auth('admin')->user() ?? auth()->user();
        $ctx = $this->roleView->active();

        // Explicit Account/Role viewing Support.
        if ($ctx && $this->isSupportRoleKey($ctx['role_key'] ?? null)) {
            $this->markSupportShell();

            return true;
        }

        // Sticky shell after explicit enter — survives Tickets/Members/Guests routes.
        if ($this->isSupportShellSticky() && $this->isSupportRelatedRoute()) {
            $this->ensureSupportWorkspaceContext($viewer);

            return true;
        }

        if (! $viewer) {
            return false;
        }

        // Native Support staff (not Admin/manager bypass) always use Support chrome.
        if ($this->roles->hasPermissionBypass($viewer)
            || $viewer->hasRole('manager')
            || $viewer->hasRole('super_admin')) {
            return false;
        }

        if ($viewer->hasRole(self::ROLE_KEY) || $viewer->hasRole('partner_support')) {
            if (request()->routeIs('admin.support.*') || $this->isSupportRelatedRoute()) {
                $this->markSupportShell();
                $this->ensureSupportWorkspaceContext($viewer);
            }

            return true;
        }

        return false;
    }

    public function markSupportShell(): void
    {
        if (request()->hasSession()) {
            request()->session()->put(self::SHELL_SESSION_KEY, true);
        }
    }

    public function clearSupportShell(): void
    {
        if (request()->hasSession()) {
            request()->session()->forget(self::SHELL_SESSION_KEY);
        }
    }

    public function isSupportShellSticky(): bool
    {
        return request()->hasSession() && (bool) request()->session()->get(self::SHELL_SESSION_KEY, false);
    }

    public function isSupportRelatedRoute(): bool
    {
        return request()->routeIs(
            'admin.support.*',
            'admin.support-tickets.*',
            'admin.support-chats.*',
            'admin.customers.*',
            'admin.partners.*',
        );
    }

    /**
     * Ensure Support role-view context so Team selector + actingAgent work.
     * Reuses AdminRoleViewService — does not invent a second switcher.
     */
    public function ensureSupportWorkspaceContext(?User $viewer = null): void
    {
        $viewer ??= auth('admin')->user() ?? auth()->user();
        if (! $viewer) {
            return;
        }

        $ctx = $this->roleView->active();
        if ($ctx && $this->isSupportRoleKey($ctx['role_key'] ?? null)) {
            return;
        }

        // Quietly open Support workspace (same session shape as enterWorkspace).
        session()->put(AdminRoleViewService::SESSION_KEY, [
            'admin_id' => $viewer->id,
            'subject_type' => 'workspace',
            'subject_id' => null,
            'subject_name' => null,
            'filter_mode' => 'all',
            'workspace_key' => self::WORKSPACE_KEY,
            'role_key' => self::WORKSPACE_KEY,
            'role_label' => 'Support',
            'underlying_roles' => AdminRoleViewService::SUPPORT_ROLE_KEYS,
            'entered_at' => now()->toIso8601String(),
        ]);
        $this->markSupportShell();
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
     * Selected support staff when filtered; null means All Support with no assignable agent.
     *
     * When Admin/Support is in All Support view but the logged-in user is themselves
     * an agent / partner_support, they are the acting agent (Accept must not 403).
     * Pure Admin watching All without selecting staff returns null — caller must prompt.
     */
    public function actingAgent(?User $viewer = null): ?User
    {
        $viewer ??= auth('admin')->user() ?? auth()->user();
        $ctx = $this->roleView->active();

        if ($ctx && $this->isSupportRoleKey($ctx['role_key'] ?? null)) {
            if (($ctx['filter_mode'] ?? 'all') === 'staff' && ! empty($ctx['subject_id'])) {
                return User::query()->find((int) $ctx['subject_id']);
            }

            // All Support: the signed-in support agent is the actor.
            if ($viewer && ($viewer->hasRole(self::ROLE_KEY) || $viewer->hasRole('partner_support'))) {
                return $viewer;
            }

            return null;
        }

        if ($viewer && ($viewer->hasRole(self::ROLE_KEY) || $viewer->hasRole('partner_support'))) {
            return $viewer;
        }

        return null;
    }

    /** True when the user may be assigned as the Support conversation agent. */
    public function isAssignableSupportAgent(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole(self::ROLE_KEY) || $user->hasRole('partner_support');
    }

    /** @return list<array{id: int, name: string, subtitle: string}> */
    public function staffOptions(): array
    {
        $options = $this->roleView->workspaceStaffOptions();
        if ($options !== []) {
            return $options;
        }

        // Fallback when role-view staff directory is empty — list Support agents directly.
        return collect($this->roleView->staffRoleDirectory())
            ->firstWhere('key', self::WORKSPACE_KEY)['staff'] ?? [];
    }

    /** @return list<array{id: int, name: string, subtitle: string, active_count: int, label: string}> */
    public function assignableAgentsWithWorkload(): array
    {
        $agents = $this->staffOptions();
        $rows = [];
        foreach ($agents as $opt) {
            $id = (int) ($opt['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $active = SupportConversation::query()
                ->where('assigned_to', $id)
                ->whereIn('status', ['assigned', 'active'])
                ->count();
            $name = (string) ($opt['name'] ?? 'Agent');
            $rows[] = [
                'id' => $id,
                'name' => $name,
                'subtitle' => (string) ($opt['subtitle'] ?? ''),
                'active_count' => $active,
                'label' => $name.' · '.$active.' active',
            ];
        }

        return $rows;
    }

    /**
     * @return array{waiting_now: int, longest_waiting_seconds: int, accepted_today: int, active_now: int}
     */
    public function queueKpis(): array
    {
        $waiting = SupportConversation::query()
            ->where('status', 'waiting')
            ->whereNull('assigned_to')
            ->get(['id', 'waiting_since', 'created_at', 'last_message_at']);

        $longest = 0;
        foreach ($waiting as $row) {
            $since = $row->waiting_since ?? $row->created_at ?? now();
            $longest = max($longest, $since->diffInSeconds(now()));
        }

        return [
            'waiting_now' => $waiting->count(),
            'longest_waiting_seconds' => $longest,
            'accepted_today' => SupportConversation::query()
                ->whereDate('accepted_at', now()->toDateString())
                ->count(),
            'active_now' => SupportConversation::query()
                ->whereIn('status', ['assigned', 'active'])
                ->whereNotNull('assigned_to')
                ->count(),
        ];
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
     * Actionable unread/activity badges for Inbox filters and Support nav.
     * Counts new unread customer/guest messages — not total open workload.
     *
     * @return array{waiting:int,active:int,mine:int,tickets:int,inbox:int,tickets_nav:int}
     */
    public function attentionBadges(?User $agent = null): array
    {
        $agent ??= $this->actingAgent();
        $agentId = $agent?->id;

        $unreadBase = SupportConversation::query()
            ->whereNotIn('status', ['closed', 'resolved'])
            ->whereHas('messages', fn ($m) => $m->whereNull('read_at')->whereIn('sender_type', ['customer', 'guest']));

        $waiting = (clone $unreadBase)->where(function ($q) {
            $q->where('status', 'waiting')
                ->orWhere(function ($inner) {
                    $inner->where('needs_human', true)->whereNull('assigned_to');
                });
        })->count();

        $active = (clone $unreadBase)
            ->whereNotNull('assigned_to')
            ->whereIn('status', ['assigned', 'active'])
            ->count();

        $mine = $agentId
            ? (clone $unreadBase)->where('assigned_to', $agentId)->count()
            : 0;

        $ticketsUnread = SupportTicket::query()
            ->whereNotIn('status', ['resolved', 'closed'])
            ->where(function ($q) use ($agentId) {
                $q->whereIn('status', ['open', 'in_progress']);
                if ($agentId) {
                    $q->orWhere('assigned_to', $agentId);
                }
            })
            ->where(function ($q) {
                // Action-required: unassigned open, or assigned needing attention recently.
                $q->whereNull('assigned_to')
                    ->orWhere('updated_at', '>=', now()->subDay());
            })
            ->count();

        // Cap ticket badge to actionable volume for nav (avoid decorative totals).
        $ticketsNav = SupportTicket::query()
            ->whereIn('status', ['open', 'in_progress'])
            ->where(function ($q) use ($agentId) {
                $q->whereNull('assigned_to');
                if ($agentId) {
                    $q->orWhere('assigned_to', $agentId);
                }
            })
            ->count();

        return [
            'waiting' => $waiting,
            'active' => $active,
            'mine' => $mine,
            'tickets' => min($ticketsUnread, 99),
            'inbox' => min($waiting + $mine, 99),
            'tickets_nav' => min($ticketsNav, 99),
        ];
    }

    /**
     * @return list<array{label: string, route: string, active_prefixes: list<string>, badge?: int}>
     */
    public function navItems(): array
    {
        $badges = $this->attentionBadges();

        return [
            [
                'label' => __('admin.support.nav.home'),
                'route' => 'admin.support.home',
                'active_prefixes' => ['admin.support.home'],
            ],
            [
                'label' => __('admin.support.nav.inbox'),
                'route' => 'admin.support.inbox',
                'active_prefixes' => ['admin.support.inbox', 'admin.support-chats.'],
                'badge' => $badges['inbox'] > 0 ? $badges['inbox'] : null,
            ],
            [
                'label' => __('admin.support.nav.tickets'),
                'route' => 'admin.support.cases',
                'active_prefixes' => ['admin.support.cases', 'admin.support-tickets.'],
                'badge' => $badges['tickets_nav'] > 0 ? $badges['tickets_nav'] : null,
            ],
            [
                'label' => __('admin.support.nav.members'),
                'route' => 'admin.support.members',
                'active_prefixes' => ['admin.support.members', 'admin.customers.'],
            ],
            [
                'label' => __('admin.support.nav.reports'),
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
        // Waiting rows stay oldest-first; active/mine follow by latest activity.
        $waitingIds = $waiting->pluck('id');
        $rest = $mineOrOpen
            ->reject(fn (SupportConversation $c) => $waitingIds->contains($c->id))
            ->sortByDesc(fn (SupportConversation $row) => $row->last_message_at?->timestamp
                ?? $row->updated_at?->timestamp
                ?? 0)
            ->values();
        $queue = $waiting->values()->concat($rest)->unique('id')->values()->take(12);

        $ticketsNeedingAttention = $this->ticketsNeedingAttention($agentId);
        $openTicketCount = $team
            ? SupportTicket::query()->whereIn('status', ['open', 'in_progress'])->count()
            : $this->myOpenTickets($agentId)->count();

        $slaAtRisk = $this->slaTicketCount($agentId, 'at_risk');
        $overdue = $this->slaTicketCount($agentId, 'overdue');
        $resolvedToday = SupportTicket::query()
            ->whereIn('status', ['resolved', 'closed'])
            ->whereDate('resolved_at', now()->toDateString())
            ->when($agentId, fn ($q) => $q->where('assigned_to', $agentId))
            ->count()
            + SupportConversation::query()
                ->whereIn('status', ['resolved', 'closed'])
                ->whereDate('updated_at', now()->toDateString())
                ->when($agentId, fn ($q) => $q->where('assigned_to', $agentId))
                ->count();

        $activeChats = $team
            ? SupportConversation::query()
                ->whereIn('status', ['assigned', 'active'])
                ->whereNotNull('assigned_to')
                ->count()
            : SupportConversation::query()
                ->where('assigned_to', $agentId)
                ->whereIn('status', ['assigned', 'active'])
                ->count();

        $longestWaitingSeconds = 0;
        foreach ($waiting as $row) {
            $since = $row->waiting_since ?? $row->created_at ?? now();
            $longestWaitingSeconds = max($longestWaitingSeconds, $since->diffInSeconds(now()));
        }

        return [
            'agent' => $agent,
            'team_view' => $team || $agentId === null,
            'staff_options' => $this->staffOptions(),
            'digital_assistants' => collect(app(SupportAutomationService::class)->allPersonas())
                ->map(fn (array $p) => [
                    'key' => (string) $p['key'],
                    'name' => (string) $p['name'],
                    'active' => (bool) ($p['active'] ?? true),
                    'kind' => 'digital_assistant',
                    'badge' => 'Digital Assistant',
                    'url' => route('admin.support.assistants', ['persona' => $p['key']]),
                ])
                ->values()
                ->all(),
            'selected_staff_id' => $agentId,
            'availability' => $agent ? $this->availability($agent) : null,
            'agents_online' => $this->agentsOnlineCount(),
            'counters' => [
                'waiting' => $waiting->count(),
                'longest_waiting_seconds' => $longestWaitingSeconds,
                'active_chats' => $activeChats,
                'open_tickets' => $openTicketCount,
                'sla_at_risk' => $slaAtRisk,
                'overdue' => $overdue,
                'resolved_today' => $resolvedToday,
                // Legacy keys kept for older blades/tests.
                'unread' => $this->unreadCount($agentId),
                'assigned_to_me' => $mineOrOpen->count(),
            ],
            'queue' => $queue->take(6)->map(fn (SupportConversation $c) => $this->serializeConversation($c))->all(),
            'tickets' => $ticketsNeedingAttention->map(fn (SupportTicket $t) => $this->serializeTicket($t))->all(),
            'recurring_issues' => $this->recurringIssues(),
            'performance' => $this->performanceSnapshot($agentId, 'today'),
            'gaps' => $this->infrastructureGaps(),
        ];
    }

    /**
     * Aggregate Issues that crossed the Settings threshold (count within window).
     * Reporting only — never auto-merges or alters tickets.
     *
     * @return list<array{issue: string, label: string, count: int, window_hours: int}>
     */
    public function recurringIssues(): array
    {
        $threshold = SupportTaxonomy::recurringIssueCount();
        $windowHours = SupportTaxonomy::recurringWindowHours();
        $from = now()->subHours($windowHours);

        $rows = SupportTicket::query()
            ->selectRaw('category, COUNT(*) as total')
            ->where('created_at', '>=', $from)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->groupBy('category')
            ->havingRaw('COUNT(*) >= ?', [$threshold])
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        $labels = SupportTaxonomy::categories();

        return $rows->map(function ($row) use ($labels, $windowHours) {
            $key = (string) $row->category;

            return [
                'issue' => $key,
                'label' => (string) ($labels[$key] ?? str_replace('_', ' ', ucfirst($key))),
                'count' => (int) $row->total,
                'window_hours' => $windowHours,
            ];
        })->all();
    }

    /**
     * Count open tickets by SLA state.
     * at_risk ≈ approaching due (within warning window); overdue = past due_at.
     */
    public function slaTicketCount(?int $agentId, string $state): int
    {
        $query = SupportTicket::query()
            ->whereIn('status', ['open', 'in_progress'])
            ->whereNotNull('sla_due_at');

        if ($agentId) {
            $query->where('assigned_to', $agentId);
        }

        if ($state === 'overdue') {
            return (int) $query->where('sla_due_at', '<', now())->count();
        }

        if ($state === 'at_risk') {
            $warningPct = (float) Setting::get('support.sla.warning_percent', 80);
            // Approximate: due within the next remaining 20% of a typical normal SLA (480 min).
            $windowMinutes = max(30, (int) round(480 * ((100 - $warningPct) / 100)));

            return (int) $query
                ->where('sla_due_at', '>=', now())
                ->where('sla_due_at', '<=', now()->addMinutes($windowMinutes))
                ->count();
        }

        return 0;
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
    public function performanceSnapshot(
        ?int $agentId,
        string $range = 'today',
        ?string $fromDate = null,
        ?string $toDate = null,
    ): array {
        [$from, $label, $to] = $this->rangeBounds($range, $fromDate, $toDate);

        $resolvedTickets = SupportTicket::query()
            ->whereIn('status', ['resolved', 'closed'])
            ->where('resolved_at', '>=', $from)
            ->when($to, fn ($q) => $q->where('resolved_at', '<=', $to))
            ->when($agentId, fn ($q) => $q->where('assigned_to', $agentId));

        $resolvedConversations = SupportConversation::query()
            ->whereIn('status', ['resolved', 'closed'])
            ->where(function ($q) use ($from, $to) {
                $q->where(function ($inner) use ($from, $to) {
                    $inner->where('resolved_at', '>=', $from)
                        ->when($to, fn ($qq) => $qq->where('resolved_at', '<=', $to));
                })->orWhere(function ($inner) use ($from, $to) {
                    $inner->whereNull('resolved_at')->where('closed_at', '>=', $from)
                        ->when($to, fn ($qq) => $qq->where('closed_at', '<=', $to));
                })->orWhere(function ($inner) use ($from, $to) {
                    $inner->whereNull('resolved_at')->whereNull('closed_at')->where('updated_at', '>=', $from)
                        ->when($to, fn ($qq) => $qq->where('updated_at', '<=', $to));
                });
            })
            ->when($agentId, fn ($q) => $q->where('assigned_to', $agentId));

        $assignedQuery = SupportTicket::query()->where('created_at', '>=', $from)
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->when($agentId, fn ($q) => $q->where('assigned_to', $agentId));
        $conversationsReceived = SupportConversation::query()->where('created_at', '>=', $from)
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to));
        $conversationsQuery = SupportConversation::query()->where('updated_at', '>=', $from)
            ->when($to, fn ($q) => $q->where('updated_at', '<=', $to))
            ->when($agentId, fn ($q) => $q->where('assigned_to', $agentId));
        $backlogQuery = SupportTicket::query()->whereIn('status', ['open', 'in_progress'])
            ->when($agentId, fn ($q) => $q->where('assigned_to', $agentId));
        $escalationsQuery = SupportTicketEvent::query()
            ->where('event', 'escalated')
            ->where('created_at', '>=', $from)
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->when($agentId, fn ($q) => $q->whereHas('ticket', fn ($tq) => $tq->where('assigned_to', $agentId)));

        $resolvedTicketCount = (clone $resolvedTickets)->count();
        $resolvedConversationCount = (clone $resolvedConversations)->count();

        // Avg first human response: accepted_at − waiting_since/created_at (minutes).
        $firstResponseMinutes = SupportConversation::query()
            ->whereNotNull('accepted_at')
            ->where('accepted_at', '>=', $from)
            ->when($to, fn ($q) => $q->where('accepted_at', '<=', $to))
            ->when($agentId, fn ($q) => $q->where('assigned_to', $agentId))
            ->get(['accepted_at', 'waiting_since', 'created_at'])
            ->map(function (SupportConversation $c) {
                $start = $c->waiting_since ?? $c->created_at;
                if (! $start || ! $c->accepted_at) {
                    return null;
                }

                return max(0, $start->diffInMinutes($c->accepted_at));
            })
            ->filter(fn ($v) => $v !== null);
        $avgFirstResponse = $firstResponseMinutes->isNotEmpty()
            ? (int) round($firstResponseMinutes->avg())
            : null;

        // Avg resolution: prefer time_to_resolve_minutes snapshot, else resolved_at − created_at.
        $resolutionSamples = SupportTicket::query()
            ->whereIn('status', ['resolved', 'closed'])
            ->where('resolved_at', '>=', $from)
            ->when($to, fn ($q) => $q->where('resolved_at', '<=', $to))
            ->when($agentId, fn ($q) => $q->where('assigned_to', $agentId))
            ->get(['time_to_resolve_minutes', 'resolved_at', 'created_at'])
            ->map(function (SupportTicket $t) {
                if ($t->time_to_resolve_minutes !== null) {
                    return (int) $t->time_to_resolve_minutes;
                }
                if ($t->resolved_at && $t->created_at) {
                    return max(0, $t->created_at->diffInMinutes($t->resolved_at));
                }

                return null;
            })
            ->filter(fn ($v) => $v !== null);
        $avgResolution = $resolutionSamples->isNotEmpty()
            ? (int) round($resolutionSamples->avg())
            : null;

        // SLA met: resolved before sla_due_at among tickets that had an SLA snapshot.
        $slaEligible = SupportTicket::query()
            ->whereIn('status', ['resolved', 'closed'])
            ->whereNotNull('sla_due_at')
            ->where('resolved_at', '>=', $from)
            ->when($to, fn ($q) => $q->where('resolved_at', '<=', $to))
            ->when($agentId, fn ($q) => $q->where('assigned_to', $agentId));
        $slaTotal = (clone $slaEligible)->count();
        $slaMet = $slaTotal > 0
            ? (clone $slaEligible)->whereColumn('resolved_at', '<=', 'sla_due_at')->count()
            : 0;
        $slaMetPct = $slaTotal > 0 ? (int) round(($slaMet / $slaTotal) * 100) : null;

        // First-contact resolution: conversations closed with no linked ticket in range.
        $fcrBase = (clone $resolvedConversations)->count();
        $fcrWithTicket = SupportConversation::query()
            ->whereIn('status', ['resolved', 'closed'])
            ->where(function ($q) use ($from, $to) {
                $q->where(function ($inner) use ($from, $to) {
                    $inner->where('resolved_at', '>=', $from)
                        ->when($to, fn ($qq) => $qq->where('resolved_at', '<=', $to));
                })->orWhere(function ($inner) use ($from, $to) {
                    $inner->where('closed_at', '>=', $from)
                        ->when($to, fn ($qq) => $qq->where('closed_at', '<=', $to));
                });
            })
            ->when($agentId, fn ($q) => $q->where('assigned_to', $agentId))
            ->whereHas('tickets')
            ->count();
        $fcrPct = $fcrBase > 0
            ? (int) round((($fcrBase - $fcrWithTicket) / $fcrBase) * 100)
            : null;

        $ratings = SupportConversation::query()
            ->whereNotNull('rating')
            ->where('rated_at', '>=', $from)
            ->when($to, fn ($q) => $q->where('rated_at', '<=', $to))
            ->when($agentId, fn ($q) => $q->where('assigned_to', $agentId))
            ->pluck('rating');
        $avgRating = $ratings->isNotEmpty() ? round((float) $ratings->avg(), 1) : null;

        $topIssues = SupportTicket::query()
            ->selectRaw('category, COUNT(*) as total')
            ->where('created_at', '>=', $from)
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->when($agentId, fn ($q) => $q->where('assigned_to', $agentId))
            ->whereNotNull('category')
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'issue' => (string) $row->category,
                'count' => (int) $row->total,
            ])
            ->all();

        return [
            'range' => $range,
            'range_label' => $label,
            'from' => $from->toDateString(),
            'to' => $to?->toDateString(),
            'resolved' => $resolvedTicketCount + $resolvedConversationCount,
            'resolved_tickets' => $resolvedTicketCount,
            'resolved_conversations' => $resolvedConversationCount,
            'tickets_assigned' => $assignedQuery->count(),
            'conversations_received' => $conversationsReceived->count(),
            'conversations_handled' => $conversationsQuery->count(),
            'open_backlog' => $backlogQuery->count(),
            'escalations' => $escalationsQuery->count(),
            'avg_first_response_minutes' => $avgFirstResponse,
            'avg_resolution_minutes' => $avgResolution,
            'first_contact_resolution' => $fcrPct,
            'sla_met' => $slaMetPct,
            'sla_met_count' => $slaMet,
            'sla_eligible_count' => $slaTotal,
            'customer_rating' => $avgRating,
            'top_issues' => $topIssues,
            'gaps' => array_filter([
                'avg_first_response' => $avgFirstResponse === null
                    ? 'No accepted_at timestamps in this range yet.'
                    : null,
                'sla_met' => $slaMetPct === null
                    ? 'No tickets with SLA snapshots resolved in this range yet.'
                    : null,
                'customer_rating' => $avgRating === null
                    ? 'No customer ratings in this range yet.'
                    : null,
            ]),
        ];
    }

    /** @return list<string> */
    public function infrastructureGaps(): array
    {
        return [
            'Department workspaces for escalated cases are not built in this pass (Support remains customer contact).',
            'Agent availability: Online/Offline is stored on user preferences; Accept refuses Offline agents. Assigned Offline agents keep ownership and receive offline acknowledgements.',
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
            ->orderByRaw('COALESCE(waiting_since, last_message_at, created_at) desc')
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

        $waitingSince = $conversation->waiting_since
            ?? $conversation->last_message_at
            ?? $conversation->updated_at
            ?? $conversation->created_at;
        $unread = $conversation->messages()
            ->whereNull('read_at')
            ->whereIn('sender_type', ['customer', 'guest'])
            ->count();

        $preview = preg_replace('/\s+/', ' ', (string) ($last?->body ?? '')) ?? '';
        $desk = app(SupportConversationService::class)->deskState($conversation);
        $isWaiting = ! $conversation->assigned_to
            && ! in_array((string) $conversation->status, ['closed', 'resolved'], true)
            && (
                (bool) $conversation->needs_human
                || in_array((string) $conversation->status, ['waiting'], true)
                || in_array((string) ($conversation->handling_state ?? ''), [
                    SupportAutomationService::STATE_ESCALATED,
                    SupportAutomationService::STATE_HUMAN,
                ], true)
            );

        return [
            'id' => $conversation->id,
            'number' => $conversation->publicNumber(),
            'conversation_number' => $conversation->publicNumber(),
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
            'is_waiting' => $isWaiting,
            'waiting_label' => $isWaiting
                ? 'Waiting '.app(SupportConversationService::class)->waitingDurationLabel($conversation)
                : null,
            'waiting_since' => $waitingSince?->toIso8601String(),
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

    /** @return array{0: Carbon, 1: string, 2: ?Carbon} */
    private function rangeBounds(string $range, ?string $fromDate = null, ?string $toDate = null): array
    {
        if ($range === 'custom' && filled($fromDate)) {
            try {
                $from = Carbon::parse($fromDate)->startOfDay();
            } catch (\Throwable) {
                $from = now()->startOfDay();
            }
            try {
                $to = filled($toDate) ? Carbon::parse($toDate)->endOfDay() : now();
            } catch (\Throwable) {
                $to = now();
            }
            if ($to->lt($from)) {
                $to = (clone $from)->endOfDay();
            }

            return [$from, $from->toDateString().' – '.$to->toDateString(), $to];
        }

        return match ($range) {
            '7d' => [now()->subDays(7)->startOfDay(), '7 days', null],
            '30d' => [now()->subDays(30)->startOfDay(), '30 days', null],
            default => [now()->startOfDay(), 'Today', null],
        };
    }

    /**
     * Digital Support Assistant profiles — Settings personas, not Staff users.
     * Metrics from SupportConversation / CSAT only (no invented numbers).
     *
     * @return list<array<string, mixed>>
     */
    public function digitalAssistantPerformance(?string $range = '30d', ?string $fromDate = null, ?string $toDate = null): array
    {
        [$from, $label, $to] = $this->rangeBounds($range ?? '30d', $fromDate, $toDate);
        $automation = app(SupportAutomationService::class);
        $personas = $automation->allPersonas();
        $out = [];

        foreach ($personas as $persona) {
            $key = (string) $persona['key'];
            $name = (string) $persona['name'];
            $active = (bool) ($persona['active'] ?? true);

            $base = SupportConversation::query()
                ->where('created_at', '>=', $from)
                ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                ->where('automation_meta->persona_key', $key);

            $handled = (clone $base)->count();
            $guestHandled = (clone $base)
                ->whereNull('customer_id')
                ->whereNull('user_id')
                ->count();
            $memberHandled = (clone $base)->whereNotNull('customer_id')->count();
            $partnerHandled = (clone $base)
                ->whereNull('customer_id')
                ->whereNotNull('user_id')
                ->count();
            $resolvedAuto = (clone $base)
                ->whereIn('status', ['resolved', 'closed'])
                ->where(function ($q) {
                    $q->where('resolution_kind', 'msaidizi')
                        ->orWhere('handling_state', SupportAutomationService::STATE_RESOLVED_AUTOMATED);
                })
                ->whereNull('assigned_to')
                ->count();
            $handedOver = (clone $base)
                ->where(function ($q) {
                    $q->where('needs_human', true)
                        ->orWhere('handling_state', SupportAutomationService::STATE_ESCALATED)
                        ->orWhere('handling_state', SupportAutomationService::STATE_HUMAN)
                        ->orWhere('handling_state', SupportAutomationService::STATE_RESOLVED_SUPPORT)
                        ->orWhere('resolution_kind', 'support')
                        ->orWhereNotNull('assigned_to');
                })
                ->count();
            $guestRepeats = (clone $base)
                ->whereNull('customer_id')
                ->whereNull('user_id')
                ->whereNotNull('guest_phone')
                ->where('automation_meta->guest_repeat', true)
                ->count();
            $ctaShown = (clone $base)
                ->where(function ($q) {
                    $q->where('automation_meta->guest_cta_shown', true)
                        ->orWhere('automation_meta->show_join_cta', true);
                })
                ->count();
            $ctaClicked = (clone $base)
                ->where('automation_meta->guest_cta_clicked', true)
                ->count();

            $ratings = SupportConversation::query()
                ->whereNotNull('rating')
                ->where('rated_at', '>=', $from)
                ->when($to, fn ($q) => $q->where('rated_at', '<=', $to))
                ->where('automation_meta->persona_key', $key)
                ->get(['rating', 'automation_meta']);

            $activeNow = SupportConversation::query()
                ->where('automation_meta->persona_key', $key)
                ->whereNotIn('status', ['resolved', 'closed'])
                ->where(function ($q) {
                    $q->where('needs_human', false)
                        ->orWhereNull('needs_human');
                })
                ->whereNotIn('handling_state', [
                    SupportAutomationService::STATE_ESCALATED,
                    SupportAutomationService::STATE_HUMAN,
                    SupportAutomationService::STATE_RESOLVED_SUPPORT,
                ])
                ->whereNull('assigned_to')
                ->count();

            // Digital resolution time: active digital handling only (excludes overnight idle + human time).
            $digitalResolvedRows = (clone $base)
                ->whereIn('status', ['resolved', 'closed'])
                ->where(function ($q) {
                    $q->where('resolution_kind', 'msaidizi')
                        ->orWhere('handling_state', SupportAutomationService::STATE_RESOLVED_AUTOMATED);
                })
                ->whereNull('assigned_to')
                ->whereNotNull('resolved_at')
                ->whereNotNull('created_at')
                ->where(function ($q) {
                    $q->whereNull('resolution_category')
                        ->orWhere('resolution_category', '!=', 'duplicate_reconcile');
                })
                ->with(['messages' => fn ($q) => $q->orderBy('id')->select(['id', 'support_conversation_id', 'created_at'])])
                ->get(['id', 'created_at', 'resolved_at', 'resolution_kind', 'handling_state', 'assigned_to', 'resolution_category']);

            $resolutionSamples = [];
            foreach ($digitalResolvedRows as $row) {
                $seconds = $this->digitalActiveResolutionSeconds($row);
                if ($seconds !== null) {
                    $resolutionSamples[] = $seconds;
                }
            }
            $avgResolutionSeconds = $resolutionSamples !== []
                ? (int) round(array_sum($resolutionSamples) / count($resolutionSamples))
                : null;
            $medianResolutionSeconds = null;
            if ($resolutionSamples !== []) {
                sort($resolutionSamples);
                $mid = (int) floor((count($resolutionSamples) - 1) / 2);
                $medianResolutionSeconds = (int) $resolutionSamples[$mid];
            }

            $recent = SupportConversation::query()
                ->where('automation_meta->persona_key', $key)
                ->where('created_at', '>=', $from)
                ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                ->with([
                    'customer:id,first_name,middle_name,last_name,phone',
                    'user:id,name',
                    'assignedTo:id,name',
                    'messages' => fn ($q) => $q->orderBy('id')->select(['id', 'support_conversation_id', 'created_at']),
                ])
                ->latest('last_message_at')
                ->limit(50)
                ->get()
                ->map(function (SupportConversation $c) use ($automation) {
                    return $this->digitalConversationRow($c, $automation);
                })
                ->all();

            $out[] = [
                'key' => $key,
                'name' => $name,
                'active' => $active,
                'conversations_handled' => $handled,
                'active_now' => $activeNow,
                'guests_handled' => $guestHandled,
                'members_handled' => $memberHandled,
                'partners_handled' => $partnerHandled,
                'resolved_without_human' => $resolvedAuto,
                'handed_over' => $handedOver,
                'guest_repeat_conversations' => $guestRepeats,
                'registration_cta_shown' => $ctaShown,
                'registration_cta_clicked' => $ctaClicked,
                'resolution_rate' => $handled > 0 ? (int) round(($resolvedAuto / $handled) * 100) : null,
                'handover_rate' => $handled > 0 ? (int) round(($handedOver / $handled) * 100) : null,
                'avg_resolution_seconds' => $avgResolutionSeconds,
                'median_resolution_seconds' => $medianResolutionSeconds,
                'avg_rating' => $ratings->isNotEmpty() ? round((float) $ratings->avg('rating'), 1) : null,
                'ratings_count' => $ratings->count(),
                'recent_conversations' => $recent,
                'range_label' => $label,
                'profile_url' => route('admin.support.assistants', ['persona' => $key, 'range' => $range ?? '30d']),
            ];
        }

        return $out;
    }

    /**
     * Active digital handling duration: message-to-message gaps ≤ 30 minutes until digital resolve.
     * Excludes overnight idle, human-handled, duplicate-reconcile, and rows without reliable timestamps.
     */
    public function digitalActiveResolutionSeconds(SupportConversation $conversation): ?int
    {
        if (! in_array((string) $conversation->status, ['resolved', 'closed'], true)) {
            return null;
        }
        if ($conversation->assigned_to) {
            return null;
        }
        if ((string) ($conversation->resolution_category ?? '') === 'duplicate_reconcile') {
            return null;
        }
        $digital = (string) ($conversation->resolution_kind ?? '') === 'msaidizi'
            || (string) ($conversation->handling_state ?? '') === SupportAutomationService::STATE_RESOLVED_AUTOMATED;
        if (! $digital || ! $conversation->resolved_at || ! $conversation->created_at) {
            return null;
        }

        $idleCap = 30 * 60;
        $times = $conversation->relationLoaded('messages')
            ? $conversation->messages->pluck('created_at')->filter()->values()
            : $conversation->messages()->orderBy('id')->pluck('created_at')->filter()->values();

        if ($times->isEmpty()) {
            // No message timestamps — do not invent; exclude from avg/median.
            return null;
        }

        $active = 0;
        $prev = $times->first();
        foreach ($times->slice(1) as $at) {
            $gap = max(0, $prev->diffInSeconds($at));
            if ($gap <= $idleCap) {
                $active += $gap;
            }
            $prev = $at;
        }
        $finalGap = max(0, $prev->diffInSeconds($conversation->resolved_at));
        if ($finalGap <= $idleCap) {
            $active += $finalGap;
        }

        return max(0, $active);
    }

    /**
     * @return array<string, mixed>
     */
    private function digitalConversationRow(SupportConversation $c, SupportAutomationService $automation): array
    {
        $meta = is_array($c->automation_meta) ? $c->automation_meta : [];
        $isGuest = ! $c->customer_id && ! $c->user_id;
        $isMember = (bool) $c->customer_id;
        $type = $isMember ? 'Member' : ($c->user_id ? 'Partner' : 'Guest');
        $customerName = $isMember
            ? trim((string) ($c->customer?->full_name ?: $c->customer?->legalDisplayName()))
            : ($isGuest
                ? trim((string) ($c->guest_name ?: ($meta['guest_name'] ?? '')))
                : trim((string) ($c->user?->name ?? '')));
        if ($customerName === '') {
            $customerName = null;
        }

        $topicRaw = (string) ($c->topic ?: ($meta['issue_slug'] ?? $meta['category_key'] ?? ''));
        $topic = $topicRaw !== ''
            ? $automation->customerFacingTopicLabel(
                $topicRaw,
                $isMember ? 'member' : ($isGuest ? 'guest' : 'partner'),
                app()->getLocale()
            )
            : null;

        $digitallyResolved = in_array((string) $c->status, ['resolved', 'closed'], true)
            && (
                (string) ($c->resolution_kind ?? '') === 'msaidizi'
                || (string) ($c->handling_state ?? '') === SupportAutomationService::STATE_RESOLVED_AUTOMATED
            )
            && ! $c->assigned_to;

        $handedToHuman = (bool) $c->needs_human
            || in_array((string) ($c->handling_state ?? ''), [
                SupportAutomationService::STATE_ESCALATED,
                SupportAutomationService::STATE_HUMAN,
                SupportAutomationService::STATE_RESOLVED_SUPPORT,
            ], true)
            || (bool) $c->assigned_to
            || (string) ($c->resolution_kind ?? '') === 'support';

        $humanResolved = in_array((string) $c->status, ['resolved', 'closed'], true)
            && $handedToHuman
            && ! $digitallyResolved;

        if ($digitallyResolved) {
            $outcome = 'Resolved digitally';
        } elseif ($humanResolved) {
            $outcome = 'Human resolved';
        } elseif ($handedToHuman && ! in_array((string) $c->status, ['resolved', 'closed'], true)) {
            $outcome = 'Handed to human';
        } elseif (in_array((string) $c->status, ['resolved', 'closed'], true)) {
            $outcome = 'Closed';
        } else {
            $outcome = 'Open';
        }

        $resolutionSeconds = $digitallyResolved ? $this->digitalActiveResolutionSeconds($c) : null;
        $handoverName = $c->assignedTo?->name;

        return [
            'id' => $c->id,
            'number' => $c->publicNumber(),
            'customer' => $customerName,
            'type' => $type,
            'topic' => $topic,
            'started_at' => optional($c->created_at)->toIso8601String(),
            'started_label' => $c->created_at ? format_app_datetime($c->created_at, 'd M H:i') : null,
            'resolved_at' => optional($c->resolved_at)->toIso8601String(),
            'resolved_label' => $c->resolved_at ? format_app_datetime($c->resolved_at, 'd M H:i') : null,
            'resolution_seconds' => $resolutionSeconds,
            'outcome' => $outcome,
            'handover' => $handoverName,
            'csat' => $c->rating ? (int) $c->rating : null,
            'rating_comment' => isset($meta['rating_comment']) ? (string) $meta['rating_comment'] : null,
            'url' => route('admin.support.inbox.show', $c),
        ];
    }

    /**
     * Overall Support report strip: Guest / Member / Partner + digital vs human outcomes.
     *
     * @return array<string, mixed>
     */
    public function supportVolumeSnapshot(?string $range = '30d', ?string $fromDate = null, ?string $toDate = null): array
    {
        [$from, $label, $to] = $this->rangeBounds($range ?? '30d', $fromDate, $toDate);
        $base = SupportConversation::query()
            ->where('created_at', '>=', $from)
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to));

        $total = (clone $base)->count();
        $guest = (clone $base)->whereNull('customer_id')->whereNull('user_id')->count();
        $member = (clone $base)->whereNotNull('customer_id')->count();
        $partner = (clone $base)->whereNull('customer_id')->whereNotNull('user_id')->count();
        $digitalResolved = (clone $base)
            ->whereIn('status', ['resolved', 'closed'])
            ->where(function ($q) {
                $q->where('resolution_kind', 'msaidizi')
                    ->orWhere('handling_state', SupportAutomationService::STATE_RESOLVED_AUTOMATED);
            })
            ->count();
        $humanHandovers = (clone $base)
            ->where(function ($q) {
                $q->where('needs_human', true)
                    ->orWhereIn('handling_state', [
                        SupportAutomationService::STATE_ESCALATED,
                        SupportAutomationService::STATE_HUMAN,
                        SupportAutomationService::STATE_RESOLVED_SUPPORT,
                    ]);
            })
            ->count();
        $humanResolved = (clone $base)
            ->whereIn('status', ['resolved', 'closed'])
            ->where(function ($q) {
                $q->where('resolution_kind', 'support')
                    ->orWhere('handling_state', SupportAutomationService::STATE_RESOLVED_SUPPORT)
                    ->orWhereNotNull('assigned_to');
            })
            ->count();
        $ratings = SupportConversation::query()
            ->whereNotNull('rating')
            ->where('rated_at', '>=', $from)
            ->when($to, fn ($q) => $q->where('rated_at', '<=', $to))
            ->pluck('rating');

        return [
            'range_label' => $label,
            'total_conversations' => $total,
            'guest_conversations' => $guest,
            'member_conversations' => $member,
            'partner_conversations' => $partner,
            'digital_resolved' => $digitalResolved,
            'human_handovers' => $humanHandovers,
            'human_resolved' => $humanResolved,
            'avg_csat' => $ratings->isNotEmpty() ? round((float) $ratings->avg(), 1) : null,
            'csat_count' => $ratings->count(),
        ];
    }
}
