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
            ->when($filter === 'waiting', fn ($query) => $query->where('needs_human', true))
            ->when($filter === 'mine' && $agent, fn ($query) => $query->where('assigned_to', $agent->id))
            ->when($filter === 'unread', function ($query) {
                $query->whereHas('messages', fn ($m) => $m->whereNull('read_at')->whereIn('sender_type', ['customer', 'guest']));
            })
            ->when($filter === 'cases', function ($query) {
                $query->whereHas('tickets');
            })
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(function ($inner) use ($like) {
                    $inner->where('guest_name', 'like', $like)
                        ->orWhere('guest_phone', 'like', $like)
                        ->orWhere('topic', 'like', $like)
                        ->orWhereHas('customer', function ($c) use ($like) {
                            $c->where('first_name', 'like', $like)
                                ->orWhere('last_name', 'like', $like)
                                ->orWhere('phone', 'like', $like)
                                ->orWhere('customer_number', 'like', $like);
                        });
                });
            })
            ->latest('last_message_at')
            ->limit(60)
            ->get();

        return view('admin.support-workspace.inbox', [
            'conversations' => $conversations->map(fn ($c) => $this->workspace->serializeConversation($c))->all(),
            'filter' => $filter,
            'q' => $q,
            'agent' => $agent,
            'quickReplies' => $this->quickReplies->all(),
            'activeId' => null,
            'conversation' => null,
            'serialized' => null,
            'context' => null,
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

        $list = SupportConversation::query()
            ->with(['customer', 'user', 'assignedTo'])
            ->whereNotIn('status', ['closed', 'resolved'])
            ->when($filter === 'waiting', fn ($query) => $query->where('needs_human', true))
            ->when($filter === 'mine' && $agent, fn ($query) => $query->where('assigned_to', $agent->id))
            ->when($filter === 'unread', function ($query) {
                $query->whereHas('messages', fn ($m) => $m->whereNull('read_at')->whereIn('sender_type', ['customer', 'guest']));
            })
            ->when($filter === 'cases', fn ($query) => $query->whereHas('tickets'))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(function ($inner) use ($like) {
                    $inner->where('guest_name', 'like', $like)
                        ->orWhere('guest_phone', 'like', $like)
                        ->orWhere('topic', 'like', $like)
                        ->orWhereHas('customer', function ($c) use ($like) {
                            $c->where('first_name', 'like', $like)
                                ->orWhere('last_name', 'like', $like)
                                ->orWhere('phone', 'like', $like)
                                ->orWhere('customer_number', 'like', $like);
                        });
                });
            })
            ->latest('last_message_at')
            ->limit(60)
            ->get();

        return view('admin.support-workspace.inbox', [
            'conversations' => $list->map(fn ($c) => $this->workspace->serializeConversation($c))->all(),
            'filter' => $filter,
            'q' => $q,
            'agent' => $agent,
            'quickReplies' => $this->quickReplies->all(),
            'activeId' => $supportConversation->id,
            'conversation' => $supportConversation,
            'serialized' => $this->workspace->serializeConversation($supportConversation),
            'context' => $this->context->forConversation($supportConversation),
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

        $this->conversations->appendMessage(
            $supportConversation,
            'staff',
            trim($data['body']),
            $agent?->id,
        );

        if (! $supportConversation->assigned_to && $agent) {
            $supportConversation->update(['assigned_to' => $agent->id]);
        }

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
        abort_unless($agent, 403);

        $supportConversation->update([
            'assigned_to' => $agent->id,
            'status' => 'assigned',
            'needs_human' => true,
        ]);

        $actor = $this->roleView->actorForAudit($request->user('admin'));
        if ($actor) {
            $this->audit->logAdminAction($actor, 'admin.support.conversation.accept', $supportConversation, [
                'assigned_to' => $agent->id,
            ]);
        }

        return redirect()
            ->route('admin.support.inbox.show', $supportConversation)
            ->with('status', 'Conversation assigned to '.$agent->name.'.');
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

        $last = $supportConversation->messages()->latest('id')->first();
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
            ->with('status', 'Case '.$ticket->ticket_number.' created from conversation.');
    }
}
