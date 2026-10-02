<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use App\Services\AdminRoleViewService;
use App\Services\AuditService;
use App\Services\Support\CustomerSupportWorkspaceService;
use App\Services\Support\SupportContextPresenter;
use App\Services\Support\SupportConversationService;
use App\Services\Support\SupportQuickReplyService;
use App\Services\Support\SupportTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupportWorkspaceController extends Controller
{
    public function __construct(
        private readonly CustomerSupportWorkspaceService $workspace,
        private readonly SupportTicketService $tickets,
        private readonly SupportConversationService $conversations,
        private readonly SupportQuickReplyService $quickReplies,
        private readonly SupportContextPresenter $context,
        private readonly AdminRoleViewService $roleView,
        private readonly AuditService $audit,
    ) {}

    public function home(): View
    {
        $dashboard = $this->workspace->dashboard();

        return view('admin.support-workspace.home', [
            'dashboard' => $dashboard,
            'supportShell' => true,
        ]);
    }

    public function inbox(Request $request): View
    {
        $agent = $this->workspace->actingAgent();
        $filter = (string) $request->query('filter', 'waiting');
        $q = trim((string) $request->query('q', ''));

        $conversations = SupportConversation::query()
            ->with(['customer', 'user', 'assignedTo'])
            ->when($filter === 'resolved', fn ($query) => $query->whereIn('status', ['closed', 'resolved']))
            ->when($filter !== 'resolved', fn ($query) => $query->whereNotIn('status', ['closed', 'resolved']))
            ->when($filter === 'waiting', function ($query) {
                $query->where(function ($q) {
                    $q->where('status', 'waiting')
                        ->orWhere(function ($inner) {
                            $inner->where('needs_human', true)->whereNull('assigned_to');
                        });
                });
            })
            ->when($filter === 'mine' && $agent, fn ($query) => $query->where('assigned_to', $agent->id))
            ->when($filter === 'unread', function ($query) {
                $query->whereHas('messages', fn ($m) => $m->whereNull('read_at')->whereIn('sender_type', ['customer', 'guest']));
            })
            ->when($filter === 'active', function ($query) {
                $query->whereNotNull('assigned_to')->whereIn('status', ['assigned', 'active']);
            })
            ->when($filter === 'tickets', fn ($query) => $query->whereHas('tickets', fn ($t) => $t->whereNotIn('status', ['resolved', 'closed'])))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(function ($inner) use ($like, $q) {
                    $inner->where('guest_name', 'like', $like)
                        ->orWhere('guest_phone', 'like', $like)
                        ->orWhere('topic', 'like', $like)
                        ->orWhere('conversation_number', 'like', $like)
                        ->orWhereHas('messages', fn ($m) => $m->where('body', 'like', $like)->whereIn('sender_type', ['customer', 'guest']))
                        ->orWhereHas('customer', function ($c) use ($like) {
                            $c->where('first_name', 'like', $like)
                                ->orWhere('last_name', 'like', $like)
                                ->orWhere('phone', 'like', $like)
                                ->orWhere('customer_number', 'like', $like);
                        });
                    if (ctype_digit($q)) {
                        $inner->orWhere('id', (int) $q);
                    }
                });
            })
            ->when($filter === 'waiting', fn ($query) => $query->orderByRaw('COALESCE(waiting_since, created_at) asc'))
            ->when($filter !== 'waiting', function ($query) {
                $query->orderByRaw("CASE WHEN needs_human = 1 AND assigned_to IS NULL THEN 0 WHEN needs_human = 1 THEN 1 ELSE 2 END")
                    ->latest('last_message_at');
            })
            ->limit(80)
            ->get();

        $specialistFollowUps = \App\Models\SupportTicketEvent::query()
            ->where('event', 'specialist_response')
            ->where('created_at', '>=', now()->subDays(14))
            ->whereHas('ticket', fn ($t) => $t->whereNotIn('status', ['resolved', 'closed']))
            ->count();

        return view('admin.support-workspace.inbox', [
            'conversations' => $conversations->map(fn ($c) => $this->workspace->serializeConversation($c))->all(),
            'filter' => $filter,
            'q' => $q,
            'agent' => $agent,
            'queueKpis' => $this->workspace->queueKpis(),
            'assignableAgents' => $this->workspace->assignableAgentsWithWorkload(),
            'canPickAgent' => ! ($agent && $this->workspace->isAssignableSupportAgent($agent)),
            'quickReplies' => $this->quickReplies->all(),
            'quickReplyBodies' => collect($this->quickReplies->all())->mapWithKeys(
                fn ($row) => [$row['key'] => $this->quickReplies->compose($row['key'], str_starts_with(app()->getLocale(), 'en') ? 'en' : 'sw', [], false)]
            )->all(),
            'signature' => $this->quickReplies->signature(str_starts_with(app()->getLocale(), 'en') ? 'en' : 'sw'),
            'activeId' => null,
            'conversation' => null,
            'serialized' => null,
            'context' => null,
            'specialistFollowUps' => $specialistFollowUps,
            'ticketTaxonomy' => \App\Support\SupportTaxonomy::all(),
            'similarSearchUrl' => route('admin.support-tickets.similar'),
            'attentionBadges' => $this->workspace->attentionBadges($agent),
            'supportShell' => true,
        ]);
    }

    public function showConversation(SupportConversation $supportConversation): View
    {
        $supportConversation->load(['customer', 'user', 'assignedTo', 'messages.senderUser', 'tickets']);
        $this->conversations->markReadForStaff($supportConversation);

        $agent = $this->workspace->actingAgent();
        $filter = (string) request()->query('filter', 'all');
        $q = trim((string) request()->query('q', ''));
        $locale = str_starts_with(app()->getLocale(), 'en') ? 'en' : 'sw';

        $list = SupportConversation::query()
            ->with(['customer', 'user', 'assignedTo'])
            ->whereNotIn('status', ['closed', 'resolved'])
            ->when($filter === 'waiting', function ($query) {
                $query->where(function ($q) {
                    $q->where('status', 'waiting')
                        ->orWhere(function ($inner) {
                            $inner->where('needs_human', true)->whereNull('assigned_to');
                        });
                });
            })
            ->when($filter === 'mine' && $agent, fn ($query) => $query->where('assigned_to', $agent->id))
            ->when($filter === 'unread', function ($query) {
                $query->whereHas('messages', fn ($m) => $m->whereNull('read_at')->whereIn('sender_type', ['customer', 'guest']));
            })
            ->when($filter === 'cases', fn ($query) => $query->whereHas('tickets'))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(function ($inner) use ($like, $q) {
                    $inner->where('guest_name', 'like', $like)
                        ->orWhere('guest_phone', 'like', $like)
                        ->orWhere('topic', 'like', $like)
                        ->orWhere('conversation_number', 'like', $like)
                        ->orWhereHas('messages', fn ($m) => $m->where('body', 'like', $like)->whereIn('sender_type', ['customer', 'guest']))
                        ->orWhereHas('customer', function ($c) use ($like) {
                            $c->where('first_name', 'like', $like)
                                ->orWhere('last_name', 'like', $like)
                                ->orWhere('phone', 'like', $like)
                                ->orWhere('customer_number', 'like', $like);
                        });
                    if (ctype_digit($q)) {
                        $inner->orWhere('id', (int) $q);
                    }
                });
            })
            ->when($filter === 'waiting', fn ($query) => $query->orderByRaw('COALESCE(waiting_since, created_at) asc'))
            ->when($filter !== 'waiting', function ($query) {
                $query->orderByRaw("CASE WHEN needs_human = 1 AND assigned_to IS NULL THEN 0 WHEN needs_human = 1 THEN 1 ELSE 2 END")
                    ->latest('last_message_at');
            })
            ->limit(80)
            ->get();

        if ($list->where('id', $supportConversation->id)->isEmpty()) {
            $list = $list->prepend($supportConversation)->unique('id')->values();
        }

        return view('admin.support-workspace.inbox', [
            'conversations' => $list->map(fn ($c) => $this->workspace->serializeConversation($c))->all(),
            'filter' => $filter,
            'q' => $q,
            'agent' => $agent,
            'queueKpis' => $this->workspace->queueKpis(),
            'assignableAgents' => $this->workspace->assignableAgentsWithWorkload(),
            'canPickAgent' => ! ($agent && $this->workspace->isAssignableSupportAgent($agent)),
            'quickReplies' => $this->quickReplies->all(),
            'quickReplyBodies' => collect($this->quickReplies->all())->mapWithKeys(function ($row) use ($locale, $supportConversation, $agent) {
                $vars = [];
                if ($row['key'] === 'introduction') {
                    $vars = [
                        'member_first_name' => $this->conversations->requesterFirstName($supportConversation),
                        'agent_first_name' => $agent
                            ? ($this->conversations->personFirstName($agent->name) ?: 'Mtoa huduma')
                            : '',
                    ];
                }

                // Signature only on Introduction quick-reply body (editable before send).
                return [$row['key'] => $this->quickReplies->compose(
                    $row['key'],
                    $locale,
                    $vars,
                    $row['key'] === 'introduction'
                )];
            })->all(),
            'signature' => $this->quickReplies->signature($locale),
            'activeId' => $supportConversation->id,
            'conversation' => $supportConversation,
            'serialized' => $this->workspace->serializeConversation($supportConversation),
            'context' => $this->context->forConversation($supportConversation),
            'specialistFollowUps' => 0,
            'ticketTaxonomy' => \App\Support\SupportTaxonomy::all(),
            'similarSearchUrl' => route('admin.support-tickets.similar'),
            'attentionBadges' => $this->workspace->attentionBadges($this->workspace->actingAgent()),
            'supportShell' => true,
        ]);
    }

    public function notifications(): RedirectResponse
    {
        return redirect()->route('admin.support.inbox');
    }

    public function cases(): RedirectResponse
    {
        return redirect()->route('admin.support-tickets.index');
    }

    public function members(Request $request): RedirectResponse
    {
        $tab = (string) $request->query('tab', 'members');

        return match ($tab) {
            'guests' => redirect()->route('admin.customers.guests.index'),
            'partners' => redirect()->route('admin.partners.index'),
            default => redirect()->route('admin.customers.index'),
        };
    }

    public function performance(Request $request): View
    {
        $range = (string) $request->query('range', 'today');
        if (! in_array($range, ['today', '7d', '30d', 'custom'], true)) {
            $range = 'today';
        }

        $fromDate = $request->query('from');
        $toDate = $request->query('to');
        if ($range === 'custom' && ! filled($fromDate)) {
            $range = 'today';
        }

        $teamView = $this->workspace->isTeamView();
        $agent = $teamView ? null : $this->workspace->actingAgent();
        $performance = $this->workspace->performanceSnapshot(
            $teamView ? null : $agent?->id,
            $range,
            filled($fromDate) ? (string) $fromDate : null,
            filled($toDate) ? (string) $toDate : null,
        );

        return view('admin.support-workspace.performance', [
            'agent' => $agent,
            'teamView' => $teamView,
            'performance' => $performance,
            'supportShell' => true,
        ]);
    }

    public function assistants(Request $request): View
    {
        $range = (string) $request->query('range', '30d');
        if (! in_array($range, ['today', '7d', '30d', 'custom'], true)) {
            $range = '30d';
        }

        $fromDate = $request->query('from');
        $toDate = $request->query('to');
        if ($range === 'custom' && ! filled($fromDate)) {
            $range = '30d';
        }

        $assistants = $this->workspace->digitalAssistantPerformance(
            $range,
            filled($fromDate) ? (string) $fromDate : null,
            filled($toDate) ? (string) $toDate : null,
        );

        return view('admin.support-workspace.assistants', [
            'assistants' => $assistants,
            'range' => $range,
            'from' => $fromDate,
            'to' => $toDate,
            'supportShell' => true,
        ]);
    }

    public function availability(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'availability' => ['required', 'in:online,away,offline'],
        ]);

        $agent = $this->workspace->actingAgent();
        abort_unless($agent, 422, 'Select a support staff member before setting availability.');

        $actor = $this->roleView->actorForAudit($request->user('admin'));
        $state = $this->workspace->setAvailability($agent, $data['availability'], $actor);

        if ($actor) {
            $this->audit->logAdminAction($actor, 'admin.support.availability', $agent, [
                'availability' => $state,
                'subject_user_id' => $agent->id,
            ]);
        }

        return back()->with('status', 'Availability set to '.ucfirst($state).'.');
    }

    public function reply(Request $request, SupportConversation $supportConversation): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $actor = $this->roleView->actorForAudit($request->user('admin'));
        $agent = $this->workspace->actingAgent();

        if (! $supportConversation->assigned_to) {
            if (! $agent || ! $this->workspace->isAssignableSupportAgent($agent)) {
                $message = __('admin.support.errors.select_agent_before_reply');
                if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
                    return response()->json(['ok' => false, 'error' => $message], 422);
                }

                return back()->withInput()->with('error', $message);
            }
            if ($this->workspace->availability($agent) !== 'online') {
                $message = __('admin.support.errors.must_be_online');
                if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
                    return response()->json(['ok' => false, 'error' => $message], 422);
                }

                return back()->withInput()->with('error', $message);
            }
            try {
                $this->conversations->accept($supportConversation, $agent);
            } catch (\InvalidArgumentException $e) {
                if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
                    return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
                }

                return back()->withInput()->with('error', $e->getMessage());
            }
            $supportConversation->refresh();
        } elseif ($agent && $this->workspace->availability($agent) !== 'online') {
            $message = __('admin.support.errors.must_be_online_to_reply');
            if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'error' => $message], 422);
            }

            return back()->withInput()->with('error', $message);
        }

        $senderId = $agent?->id ?? $supportConversation->assigned_to;
        $message = $this->conversations->appendMessage(
            $supportConversation,
            'staff',
            trim($data['body']),
            $senderId ? (int) $senderId : null,
            false,
            true,
        );

        $supportConversation->loadMissing('customer');
        if ($supportConversation->customer) {
            $this->notifyMemberSupportReply($supportConversation, trim($data['body']));
        }

        if ($actor) {
            $this->audit->logAdminAction($actor, 'admin.support.conversation.reply', $supportConversation, [
                'conversation_id' => $supportConversation->id,
                'message_id' => $message->id,
            ]);
        }

        $supportConversation->load(['messages' => fn ($q) => $q->orderBy('id')]);

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'conversation_id' => $supportConversation->id,
                'message_id' => $message->id,
                'messages' => $this->conversations->serializeMessages($supportConversation),
            ]);
        }

        return back()->with('status', __('admin.support.status.reply_sent'));
    }

    private function notifyMemberSupportReply(SupportConversation $conversation, string $body): void
    {
        $customer = $conversation->customer;
        if (! $customer) {
            return;
        }

        // Deduplicate: one in-app ping per conversation per minute.
        $recent = \App\Models\NotificationLog::query()
            ->where('customer_id', $customer->id)
            ->where('template', 'support_replied')
            ->where('created_at', '>=', now()->subMinute())
            ->where('message', 'like', '%'.$conversation->publicNumber().'%')
            ->exists();
        if ($recent) {
            return;
        }

        $url = route('site.borrower.support', ['chat' => 1]);
        app(\App\Services\NotificationService::class)->notifyInApp(
            $customer,
            __('borrower.notifications.support_replied_body', ['ref' => $conversation->publicNumber()], 'sw'),
            'support',
            'support_replied',
            __('borrower.notifications.support_replied_title', [], 'sw'),
            $url,
            __('borrower.notifications.support_replied_cta', [], 'sw'),
            [
                'title_key' => 'borrower.notifications.support_replied_title',
                'body_key' => 'borrower.notifications.support_replied_body',
                'cta_key' => 'borrower.notifications.support_replied_cta',
                'ref' => $conversation->publicNumber(),
                'support_conversation_id' => $conversation->id,
            ],
        );
    }

    public function accept(Request $request, SupportConversation $supportConversation): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'agent_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $agent = $this->workspace->actingAgent();
        if (! empty($data['agent_id'])) {
            $picked = \App\Models\User::query()->find((int) $data['agent_id']);
            if ($picked && $this->workspace->isAssignableSupportAgent($picked)) {
                $agent = $picked;
            }
        }

        if (! $agent || ! $this->workspace->isAssignableSupportAgent($agent)) {
            // Admin in All Support: return assignable staff list instead of a dead-end error.
            $options = $this->workspace->assignableAgentsWithWorkload();
            $message = 'Choose a Support staff member to Accept this conversation.';
            if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'ok' => false,
                    'error' => $message,
                    'needs_agent' => true,
                    'agents' => $options,
                ], 422);
            }

            return redirect()
                ->route('admin.support.inbox.show', $supportConversation)
                ->with('error', $message)
                ->with('assignableAgents', $options);
        }

        if ($this->workspace->availability($agent) !== 'online') {
            $message = 'That Support person must be Online to accept this conversation (currently '.ucfirst($this->workspace->availability($agent)).').';
            if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'error' => $message], 422);
            }

            return redirect()
                ->route('admin.support.inbox.show', $supportConversation)
                ->with('error', $message);
        }

        try {
            $this->conversations->accept($supportConversation, $agent);
        } catch (\InvalidArgumentException $e) {
            if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
            }

            return redirect()
                ->route('admin.support.inbox.show', $supportConversation)
                ->with('error', $e->getMessage());
        }

        $actor = $this->roleView->actorForAudit($request->user('admin'));
        if ($actor) {
            $this->audit->logAdminAction($actor, 'admin.support.conversation.accept', $supportConversation, [
                'assigned_to' => $agent->id,
            ]);
        }

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            $supportConversation->refresh()->load(['messages' => fn ($q) => $q->orderBy('id')]);

            return response()->json([
                'ok' => true,
                'conversation_id' => $supportConversation->id,
                'assigned_to' => $agent->id,
                'assigned_name' => $agent->name,
                'messages' => $this->conversations->serializeMessages($supportConversation),
                'redirect' => route('admin.support.inbox.show', $supportConversation),
            ]);
        }

        return redirect()
            ->route('admin.support.inbox.show', $supportConversation)
            ->with('status', 'Assigned to '.$agent->name.'. Introduction sent.');
    }

    public function conversationThread(SupportConversation $supportConversation): \Illuminate\Http\JsonResponse
    {
        $this->conversations->markReadForStaff($supportConversation);
        $supportConversation->load(['messages' => fn ($q) => $q->orderBy('id')]);

        return response()->json([
            'ok' => true,
            'conversation_id' => $supportConversation->id,
            'status' => $supportConversation->status,
            'assigned_to' => $supportConversation->assigned_to,
            'desk_state' => $this->conversations->deskState($supportConversation),
            'messages' => $this->conversations->serializeMessages($supportConversation),
        ]);
    }

    public function resolveConversation(Request $request, SupportConversation $supportConversation): RedirectResponse
    {
        $data = $request->validate([
            'resolution_category' => ['required', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:2000'],
            'ask_rating' => ['nullable', 'boolean'],
        ]);

        $actor = $this->roleView->actorForAudit($request->user('admin'));
        $agent = $this->workspace->actingAgent() ?? $actor;
        $this->conversations->resolve(
            $supportConversation,
            $agent,
            $data['note'] ?? null,
            $data['resolution_category'],
            $request->boolean('ask_rating', true),
        );

        if ($actor) {
            $this->audit->logAdminAction($actor, 'admin.support.conversation.resolve', $supportConversation, [
                'conversation_id' => $supportConversation->id,
                'resolution_category' => $data['resolution_category'],
            ]);
        }

        return redirect()
            ->route('admin.support.inbox', ['filter' => 'waiting'])
            ->with('status', 'Conversation closed. Member can rate and start a fresh thread next time.');
    }

    public function newInteraction(Request $request): View
    {
        $customerId = $request->query('customer_id');
        $customer = $customerId
            ? \App\Models\Customer::query()->find($customerId)
            : null;
        $partnerId = $request->query('partner_id');
        $partner = ($partnerId && class_exists(\App\Models\Vendor::class))
            ? \App\Models\Vendor::query()->find($partnerId)
            : null;

        return view('admin.support-workspace.interaction', [
            'customer' => $customer,
            'partner' => $partner,
            'context' => $customer ? $this->context->forCustomer($customer) : null,
            'channel' => (string) $request->query('channel', 'phone'),
            'party' => ($customer || $partner) ? 'registered' : (string) $request->query('party', 'registered'),
            'supportShell' => true,
        ]);
    }

    public function searchCustomers(Request $request): \Illuminate\Http\JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $like = '%'.$q.'%';
        $digits = preg_replace('/\D+/', '', $q) ?: '';
        $rows = [];

        $customers = \App\Models\Customer::query()
            ->where(function ($query) use ($like, $digits, $q) {
                $query->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('customer_number', 'like', $like)
                    ->orWhere('member_no', 'like', $like);
                if ($digits !== '') {
                    $query->orWhere('phone', 'like', '%'.$digits.'%');
                }
                if (preg_match('/^APP-/i', $q) || ctype_digit($q)) {
                    $query->orWhereIn('id', function ($sub) use ($like, $q) {
                        $sub->select('customer_id')
                            ->from('loan_applications')
                            ->where('application_number', 'like', $like);
                        if (ctype_digit($q)) {
                            $sub->orWhere('id', (int) $q);
                        }
                    });
                }
            })
            ->orderBy('first_name')
            ->limit(12)
            ->get();

        foreach ($customers as $c) {
            $rows[] = [
                'id' => $c->id,
                'kind' => 'member',
                'kind_label' => 'Member',
                'label' => trim($c->first_name.' '.$c->last_name)
                    .' · '.($c->phone ?: '—')
                    .' · '.($c->customer_number ?: ('#'.$c->id)),
                'url' => route('admin.support.interactions.new', [
                    'customer_id' => $c->id,
                    'party' => 'registered',
                    'channel' => request('channel', 'phone'),
                    'support_action' => request('support_action', 'conversation'),
                ]),
            ];
        }

        if (class_exists(\App\Models\Vendor::class)) {
            $partners = \App\Models\Vendor::query()
                ->where(function ($query) use ($like, $digits) {
                    $query->where('name', 'like', $like);
                    if (\Illuminate\Support\Facades\Schema::hasColumn('vendors', 'phone')) {
                        $query->orWhere('phone', 'like', $like);
                        if ($digits !== '') {
                            $query->orWhere('phone', 'like', '%'.$digits.'%');
                        }
                    }
                    if (\Illuminate\Support\Facades\Schema::hasColumn('vendors', 'vendor_number')) {
                        $query->orWhere('vendor_number', 'like', $like);
                    }
                    if (\Illuminate\Support\Facades\Schema::hasColumn('vendors', 'affiliate_code')) {
                        $query->orWhere('affiliate_code', 'like', $like);
                    }
                })
                ->orderBy('name')
                ->limit(8)
                ->get();

            foreach ($partners as $p) {
                $rows[] = [
                    'id' => 'p-'.$p->id,
                    'kind' => 'partner',
                    'kind_label' => 'Partner',
                    'label' => (string) ($p->name ?: 'Partner')
                        .' · '.((string) ($p->phone ?? '—'))
                        .' · '.((string) ($p->vendor_number ?? $p->affiliate_code ?? ('#'.$p->id))),
                    'url' => route('admin.support.interactions.new', [
                        'partner_id' => $p->id,
                        'party' => 'registered',
                        'channel' => request('channel', 'phone'),
                        'support_action' => request('support_action', 'conversation'),
                    ]),
                ];
            }
        }

        return response()->json(['data' => $rows]);
    }

    public function storeInteraction(Request $request): RedirectResponse
    {
        $subjects = [
            'how_to_join' => 'How to join / registration',
            'loan_application' => 'Loan application inquiry',
            'existing_loan' => 'Existing loan',
            'payment' => 'Payment',
            'guarantor' => 'Guarantor',
            'marketplace' => 'Marketplace / asset',
            'account_profile' => 'Account / profile',
            'technical' => 'Technical problem',
            'complaint' => 'Complaint',
            'partner_inquiry' => 'Partner inquiry',
            'other' => 'Other',
        ];

        $data = $request->validate([
            'party' => ['required', 'in:registered,non_member'],
            'support_action' => ['required', 'in:conversation,interaction,ticket,save_guest'],
            'channel' => ['nullable', 'in:phone,walk_in,other,web_chat'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'partner_id' => ['nullable', 'integer'],
            'guest_first_name' => ['nullable', 'string', 'max:80'],
            'guest_middle_name' => ['nullable', 'string', 'max:80'],
            'guest_last_name' => ['nullable', 'string', 'max:80'],
            'guest_phone' => ['nullable', 'string', 'max:32'],
            'subject_key' => ['nullable', 'string', Rule::in(array_keys($subjects))],
            'subject_other' => ['nullable', 'string', 'max:180'],
            'body' => ['nullable', 'string', 'max:5000'],
        ]);

        $action = (string) $data['support_action'];

        if ($data['party'] === 'non_member' && $action === 'conversation') {
            return back()->withInput()->with('error', __('admin.support.errors.guest_no_outbound_chat'));
        }

        if (! in_array($action, ['conversation', 'save_guest'], true)) {
            $request->validate([
                'subject_key' => ['required', 'string', Rule::in(array_keys($subjects))],
                'body' => ['required', 'string', 'max:5000'],
            ]);
        }

        $actor = $this->roleView->actorForAudit($request->user('admin'));
        $agent = $this->workspace->actingAgent();
        if (! $agent || ! $this->workspace->isAssignableSupportAgent($agent)) {
            $agent = $actor && $this->workspace->isAssignableSupportAgent($actor) ? $actor : null;
        }

        if ($action === 'conversation') {
            if (! $agent || $this->workspace->availability($agent) !== 'online') {
                return back()->withInput()->with('error', __('admin.support.errors.must_be_online'));
            }
        }

        $customer = null;
        $partnerUser = null;
        $guestName = null;
        $guestPhone = null;
        $guestRecord = null;

        if ($data['party'] === 'registered') {
            if (! empty($data['customer_id'])) {
                $customer = \App\Models\Customer::query()->find($data['customer_id']);
            } elseif (! empty($data['partner_id']) && class_exists(\App\Models\Vendor::class)) {
                $partner = \App\Models\Vendor::query()->find($data['partner_id']);
                $partnerUser = $partner?->user;
                $guestName = $partner?->name ?: $partnerUser?->name;
                $guestPhone = \App\Support\PhoneNumber::digits((string) ($partner?->phone ?? $partnerUser?->phone ?? ''));
            }
            if (! $customer && ! $partnerUser && ! $guestName) {
                return back()->withInput()->with('error', __('admin.support.errors.select_person'));
            }
        } else {
            $request->validate([
                'guest_first_name' => ['required', 'string', 'max:80'],
                'guest_last_name' => ['required', 'string', 'max:80'],
                'guest_phone' => ['required', 'string', 'max:32'],
            ]);
            $guestName = trim(collect([
                $data['guest_first_name'] ?? '',
                $data['guest_middle_name'] ?? '',
                $data['guest_last_name'] ?? '',
            ])->filter()->implode(' '));
            $guestPhone = \App\Support\PhoneNumber::digits($data['guest_phone']);

            $guestRecord = app(\App\Services\Support\SupportGuestService::class)->touchGuest(
                (string) ($data['guest_first_name'] ?? ''),
                (string) ($data['guest_last_name'] ?? ''),
                (string) $data['guest_phone'],
                $action === 'save_guest'
                    ? \App\Services\Support\SupportGuestService::SOURCE_OTHER
                    : match ($data['channel'] ?? 'phone') {
                        'walk_in' => \App\Services\Support\SupportGuestService::SOURCE_WALK_IN,
                        'web_chat' => \App\Services\Support\SupportGuestService::SOURCE_GUEST_CHAT,
                        'other' => \App\Services\Support\SupportGuestService::SOURCE_OTHER,
                        default => \App\Services\Support\SupportGuestService::SOURCE_PHONE_CALL,
                    },
            );
        }

        if ($action === 'save_guest') {
            if (! $guestRecord) {
                return back()->withInput()->with('error', __('admin.support.errors.guest_save_failed'));
            }

            if ($actor) {
                $this->audit->logAdminAction($actor, 'admin.support.guest.save', $guestRecord, [
                    'phone' => $guestRecord->phone,
                ]);
            }

            return redirect()
                ->route('admin.customers.guests.show', $guestRecord)
                ->with('status', __('admin.support.status.guest_saved'));
        }

        $subject = $subjects[$data['subject_key'] ?? ''] ?? null;
        if (($data['subject_key'] ?? '') === 'other' && filled($data['subject_other'] ?? null)) {
            $subject = trim((string) $data['subject_other']);
        }

        $channel = match ($data['channel'] ?? 'web_chat') {
            'walk_in' => 'walk_in',
            'other' => 'other',
            'phone' => 'phone',
            default => 'web_chat',
        };

        if ($action === 'ticket') {
            $ticket = $this->tickets->create([
                'customer_id' => $customer?->id,
                'contact_kind' => $customer ? 'customer' : 'guest',
                'guest_name' => $customer ? null : $guestName,
                'guest_phone' => $customer ? null : $guestPhone,
                'source' => 'admin',
                'channel' => $channel,
                'category' => 'other',
                'subject' => $subject ?: 'Support ticket',
                'description' => trim((string) ($data['body'] ?? '')),
                'priority' => 'normal',
                'status' => 'open',
                'assigned_to' => $agent?->id,
                'actor' => $actor,
            ]);

            if ($actor) {
                $this->audit->logAdminAction($actor, 'admin.support.ticket.create', $ticket, [
                    'channel' => $channel,
                    'subject' => $subject,
                ]);
            }

            return redirect()
                ->route('admin.support-tickets.show', $ticket)
                ->with('status', __('admin.support.status.ticket_created'));
        }

        $conversation = $this->conversations->openConversationFor(
            $customer,
            $customer?->user ?: $partnerUser,
            $customer ? null : $guestName,
            $customer ? null : $guestPhone,
            $channel,
        );

        $updates = [
            'topic' => $subject ?: $conversation->topic,
            'channel' => $channel,
            'last_message_at' => now(),
        ];

        if ($action === 'conversation') {
            $updates['needs_human'] = false;
            $updates['status'] = SupportConversationService::STATUS_ACTIVE;
            $updates['assigned_to'] = $agent?->id;
            $updates['accepted_at'] = now();
            $updates['handling_state'] = \App\Services\Support\SupportAutomationService::STATE_HUMAN;
        } else {
            // Record interaction — internal only; never post notes as customer-visible chat.
            $updates['needs_human'] = false;
            $updates['status'] = SupportConversationService::STATUS_ACTIVE;
            $updates['assigned_to'] = $agent?->id ?: $conversation->assigned_to;
            $updates['resolution_note'] = trim((string) ($data['body'] ?? ''));
        }

        $conversation->update($updates);

        if ($actor) {
            $this->audit->logAdminAction($actor, 'admin.support.'.$action.'.record', $conversation, [
                'channel' => $channel,
                'subject' => $subject,
                'party' => $data['party'],
                'internal_notes' => $action === 'interaction' ? trim((string) ($data['body'] ?? '')) : null,
            ]);
        }

        $status = $action === 'conversation'
            ? __('admin.support.status.conversation_opened')
            : __('admin.support.status.interaction_recorded');

        return redirect()
            ->route('admin.support.inbox.show', $conversation)
            ->with('status', $status);
    }

    public function createCase(Request $request, SupportConversation $supportConversation): RedirectResponse
    {
        $taxonomy = \App\Support\SupportTaxonomy::all();
        $categoryKeys = array_keys($taxonomy['categories'] ?? []);

        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:180'],
            'subject_other' => ['nullable', 'string', 'max:180'],
            'category' => ['nullable', 'string', 'max:64', Rule::in([...$categoryKeys, 'other'])],
            'category_other' => ['nullable', 'string', 'max:120'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'body' => ['nullable', 'string', 'max:5000'],
            'related_type' => ['nullable', 'in:application,loan,payment,account'],
            'related_id' => ['nullable', 'integer'],
            'create_anyway' => ['nullable', 'boolean'],
        ]);

        $actor = $this->roleView->actorForAudit($request->user('admin'));
        $agent = $this->workspace->actingAgent();
        $ctx = $this->context->forConversation($supportConversation);

        $relatedType = $data['related_type'] ?? null;
        $relatedId = isset($data['related_id']) ? (int) $data['related_id'] : null;
        if (! $relatedType && ($ctx['application']['id'] ?? null)) {
            $relatedType = 'application';
            $relatedId = (int) $ctx['application']['id'];
        } elseif (! $relatedType && ($ctx['loan']['id'] ?? null)) {
            $relatedType = 'loan';
            $relatedId = (int) $ctx['loan']['id'];
        }

        $last = $supportConversation->messages()
            ->whereIn('sender_type', ['customer', 'guest'])
            ->latest('id')
            ->first()
            ?: $supportConversation->messages()->latest('id')->first();

        $category = \App\Support\SupportTaxonomy::resolveCategory(
            $data['category'] ?? null,
            $data['category_other'] ?? null,
        );
        $subject = \App\Support\SupportTaxonomy::resolveSubject(
            $data['subject'] ?? null,
            $data['subject_other'] ?? null,
        );
        if ($subject === 'General follow-up' && filled($supportConversation->topic)) {
            $subject = (string) $supportConversation->topic;
        }
        $priority = $data['priority']
            ?? \App\Support\SupportTaxonomy::defaultPriorityFor($category);

        $ticket = $this->tickets->create([
            'customer_id' => $supportConversation->customer_id,
            'guest_name' => $supportConversation->customer_id
                ? null
                : ($supportConversation->guest_name ?: ($supportConversation->user?->name ?: 'Guest')),
            'guest_email' => $supportConversation->customer_id ? null : $supportConversation->user?->email,
            'guest_phone' => $supportConversation->customer_id
                ? null
                : ($supportConversation->guest_phone ?: $supportConversation->user?->phone),
            'subject' => $subject,
            'category' => $category,
            'priority' => $priority,
            'description' => ($data['body'] ?? null) ?: ($last?->body ?: 'Created from support conversation #'.$supportConversation->id),
            'source' => 'chatbot',
            'assigned_to' => $agent?->id,
            'status' => 'open',
            'support_conversation_id' => $supportConversation->id,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'actor' => $actor,
        ]);

        if ($actor) {
            $this->audit->logAdminAction($actor, 'admin.support.conversation.create_case', $ticket, [
                'conversation_id' => $supportConversation->id,
                'ticket_id' => $ticket->id,
            ]);
        }

        return redirect()
            ->route('admin.support-tickets.show', $ticket)
            ->with('status', 'Case '.$ticket->ticket_number.' created. Not escalated — escalate only if another department must investigate.');
    }
}
