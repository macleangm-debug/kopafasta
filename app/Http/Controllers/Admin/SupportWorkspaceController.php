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
        $filter = (string) $request->query('filter', 'all');
        $q = trim((string) $request->query('q', ''));

        $conversations = SupportConversation::query()
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
            ->when($filter === 'cases', function ($query) {
                $query->whereHas('tickets');
            })
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(function ($inner) use ($like, $q) {
                    $inner->where('guest_name', 'like', $like)
                        ->orWhere('guest_phone', 'like', $like)
                        ->orWhere('topic', 'like', $like)
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
            ->orderByRaw("CASE WHEN needs_human = 1 AND assigned_to IS NULL THEN 0 WHEN needs_human = 1 THEN 1 ELSE 2 END")
            ->latest('last_message_at')
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
            'quickReplies' => $this->quickReplies->all(),
            'quickReplyBodies' => collect($this->quickReplies->all())->mapWithKeys(
                fn ($row) => [$row['key'] => $this->quickReplies->compose($row['key'], str_starts_with(app()->getLocale(), 'en') ? 'en' : 'sw')]
            )->all(),
            'signature' => $this->quickReplies->signature(str_starts_with(app()->getLocale(), 'en') ? 'en' : 'sw'),
            'activeId' => null,
            'conversation' => null,
            'serialized' => null,
            'context' => null,
            'specialistFollowUps' => $specialistFollowUps,
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
            ->orderByRaw("CASE WHEN needs_human = 1 AND assigned_to IS NULL THEN 0 WHEN needs_human = 1 THEN 1 ELSE 2 END")
            ->latest('last_message_at')
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
            'quickReplies' => $this->quickReplies->all(),
            'quickReplyBodies' => collect($this->quickReplies->all())->mapWithKeys(
                fn ($row) => [$row['key'] => $this->quickReplies->compose($row['key'], $locale)]
            )->all(),
            'signature' => $this->quickReplies->signature($locale),
            'activeId' => $supportConversation->id,
            'conversation' => $supportConversation,
            'serialized' => $this->workspace->serializeConversation($supportConversation),
            'context' => $this->context->forConversation($supportConversation),
            'specialistFollowUps' => 0,
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

    public function members(): RedirectResponse
    {
        return redirect()->route('admin.customers.index');
    }

    public function performance(Request $request): View
    {
        $range = (string) $request->query('range', 'today');
        if (! in_array($range, ['today', '7d', '30d'], true)) {
            $range = 'today';
        }

        $agent = $this->workspace->actingAgent();
        $performance = $this->workspace->performanceSnapshot($agent?->id, $range);

        return view('admin.support-workspace.performance', [
            'agent' => $agent,
            'performance' => $performance,
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

    public function reply(Request $request, SupportConversation $supportConversation): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $actor = $this->roleView->actorForAudit($request->user('admin'));
        $agent = $this->workspace->actingAgent() ?? $actor;

        if (! $supportConversation->assigned_to && $agent) {
            $this->conversations->accept($supportConversation, $agent);
            $supportConversation->refresh();
        }

        $this->conversations->appendMessage(
            $supportConversation,
            'staff',
            trim($data['body']),
            $agent?->id,
            false,
            true,
        );

        if ($actor) {
            $this->audit->logAdminAction($actor, 'admin.support.conversation.reply', $supportConversation, [
                'conversation_id' => $supportConversation->id,
            ]);
        }

        return back()->with('status', 'Reply sent.');
    }

    public function accept(Request $request, SupportConversation $supportConversation): RedirectResponse
    {
        $agent = $this->workspace->actingAgent();
        abort_unless($agent, 403, 'Select a support staff member before accepting.');

        $this->conversations->accept($supportConversation, $agent);

        $actor = $this->roleView->actorForAudit($request->user('admin'));
        if ($actor) {
            $this->audit->logAdminAction($actor, 'admin.support.conversation.accept', $supportConversation, [
                'assigned_to' => $agent->id,
            ]);
        }

        return redirect()
            ->route('admin.support.inbox.show', $supportConversation)
            ->with('status', 'Assigned to '.$agent->name.'. Introduction sent.');
    }

    public function resolveConversation(Request $request, SupportConversation $supportConversation): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $actor = $this->roleView->actorForAudit($request->user('admin'));
        $agent = $this->workspace->actingAgent() ?? $actor;
        $this->conversations->resolve($supportConversation, $agent, $data['note'] ?? null);

        if ($actor) {
            $this->audit->logAdminAction($actor, 'admin.support.conversation.resolve', $supportConversation, [
                'conversation_id' => $supportConversation->id,
            ]);
        }

        return redirect()
            ->route('admin.support.inbox')
            ->with('status', 'Conversation resolved. No case required.');
    }

    public function newInteraction(Request $request): View
    {
        $customerId = $request->query('customer_id');
        $customer = $customerId
            ? \App\Models\Customer::query()->find($customerId)
            : null;

        return view('admin.support-workspace.interaction', [
            'customer' => $customer,
            'context' => $customer ? $this->context->forCustomer($customer) : null,
            'channel' => (string) $request->query('channel', 'phone'),
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
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $customers->map(fn (\App\Models\Customer $c) => [
                'id' => $c->id,
                'label' => trim($c->first_name.' '.$c->last_name)
                    .' · '.($c->phone ?: '—')
                    .' · '.($c->customer_number ?: ('#'.$c->id)),
                'url' => route('admin.support.interactions.new', [
                    'customer_id' => $c->id,
                    'channel' => request('channel', 'phone'),
                ]),
            ]),
        ]);
    }

    public function storeInteraction(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'in:phone,walk_in,other'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'guest_name' => ['nullable', 'string', 'max:120'],
            'guest_phone' => ['nullable', 'string', 'max:32'],
            'subject' => ['nullable', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:5000'],
            'create_case' => ['nullable', 'boolean'],
        ]);

        $actor = $this->roleView->actorForAudit($request->user('admin'));
        $agent = $this->workspace->actingAgent() ?? $actor;
        $customer = ! empty($data['customer_id'])
            ? \App\Models\Customer::query()->find($data['customer_id'])
            : null;

        if (! $customer) {
            $request->validate([
                'guest_name' => ['required', 'string', 'max:120'],
                'guest_phone' => ['required', 'string', 'max:32'],
            ]);
        }

        $channel = match ($data['channel']) {
            'walk_in' => 'walk_in',
            'other' => 'other',
            default => 'phone',
        };

        $conversation = $this->conversations->openConversationFor(
            $customer,
            $customer?->user,
            $customer ? null : $data['guest_name'],
            $customer ? null : \App\Support\PhoneNumber::digits($data['guest_phone']),
            $channel,
        );

        $conversation->update([
            'topic' => $data['subject'] ?? $conversation->topic ?? ucfirst(str_replace('_', ' ', $channel)).' interaction',
            'needs_human' => false,
            'status' => 'active',
            'assigned_to' => $agent?->id ?: $conversation->assigned_to,
            'last_message_at' => now(),
        ]);

        $this->conversations->appendMessage(
            $conversation,
            'staff',
            trim($data['body']),
            $agent?->id,
            false,
            true,
        );

        if ($request->boolean('create_case')) {
            $ctx = $this->context->forConversation($conversation);
            $relatedType = ($ctx['application']['id'] ?? null) ? 'application' : (($ctx['loan']['id'] ?? null) ? 'loan' : ($customer ? 'account' : null));
            $relatedId = $ctx['application']['id'] ?? $ctx['loan']['id'] ?? $customer?->id;

            $ticket = $this->tickets->create([
                'customer_id' => $customer?->id,
                'guest_name' => $customer ? null : $data['guest_name'],
                'guest_phone' => $customer ? null : \App\Support\PhoneNumber::digits($data['guest_phone']),
                'subject' => $data['subject'] ?? ('Phone interaction #'.$conversation->id),
                'category' => 'general',
                'priority' => 'normal',
                'description' => trim($data['body']),
                'source' => 'admin',
                'assigned_to' => $agent?->id,
                'status' => 'open',
                'support_conversation_id' => $conversation->id,
                'related_type' => $relatedType,
                'related_id' => $relatedId,
                'actor' => $actor,
            ]);

            return redirect()
                ->route('admin.support-tickets.show', $ticket)
                ->with('status', 'Interaction recorded and case '.$ticket->ticket_number.' created.');
        }

        return redirect()
            ->route('admin.support.inbox.show', $conversation)
            ->with('status', 'Interaction recorded.');
    }

    public function createCase(Request $request, SupportConversation $supportConversation): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:180'],
            'category' => ['nullable', 'string', 'max:64'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'body' => ['nullable', 'string', 'max:5000'],
            'related_type' => ['nullable', 'in:application,loan,payment,account'],
            'related_id' => ['nullable', 'integer'],
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

        $ticket = $this->tickets->create([
            'customer_id' => $supportConversation->customer_id,
            'guest_name' => $supportConversation->customer_id
                ? null
                : ($supportConversation->guest_name ?: ($supportConversation->user?->name ?: 'Guest')),
            'guest_email' => $supportConversation->customer_id ? null : $supportConversation->user?->email,
            'guest_phone' => $supportConversation->customer_id
                ? null
                : ($supportConversation->guest_phone ?: $supportConversation->user?->phone),
            'subject' => ($data['subject'] ?? null)
                ?: ($supportConversation->topic ?: 'Support conversation #'.$supportConversation->id),
            'category' => ($data['category'] ?? null) ?: 'general',
            'priority' => ($data['priority'] ?? null) ?: 'normal',
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
