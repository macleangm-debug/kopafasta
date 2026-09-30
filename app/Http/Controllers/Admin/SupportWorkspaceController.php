<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use App\Models\SupportTicket;
use App\Services\AdminRoleViewService;
use App\Services\AuditService;
use App\Services\Support\CustomerSupportWorkspaceService;
use App\Services\Support\SupportTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportWorkspaceController extends Controller
{
    public function __construct(
        private readonly CustomerSupportWorkspaceService $workspace,
        private readonly SupportTicketService $tickets,
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

    public function inbox(): View
    {
        $agent = $this->workspace->actingAgent();
        $waiting = $this->workspace->waitingConversations();
        $mine = $this->workspace->myConversations($agent?->id);
        $conversations = $waiting->merge($mine)->unique('id')->sortByDesc(function ($row) {
            return $row->last_message_at?->timestamp ?? 0;
        })->values();

        return view('admin.support-workspace.inbox', [
            'conversations' => $conversations->map(fn ($c) => $this->workspace->serializeConversation($c))->all(),
            'agent' => $agent,
            'supportShell' => true,
        ]);
    }

    public function showConversation(SupportConversation $supportConversation): View
    {
        $supportConversation->load(['customer', 'user', 'assignedTo', 'messages.senderUser']);

        $openCases = $supportConversation->customer_id
            ? SupportTicket::query()
                ->where('customer_id', $supportConversation->customer_id)
                ->whereIn('status', ['open', 'in_progress'])
                ->latest()
                ->limit(5)
                ->get()
            : collect();

        return view('admin.support-workspace.conversation', [
            'conversation' => $supportConversation,
            'serialized' => $this->workspace->serializeConversation($supportConversation),
            'openCases' => $openCases,
            'supportShell' => true,
        ]);
    }

    public function cases(): RedirectResponse
    {
        return redirect()->route('admin.support-tickets.index');
    }

    public function members(): RedirectResponse
    {
        return redirect()->route('admin.customers.index');
    }

    public function notifications(): View
    {
        $dashboard = $this->workspace->dashboard();

        return view('admin.support-workspace.notifications', [
            'dashboard' => $dashboard,
            'supportShell' => true,
        ]);
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
        abort_unless($agent, 403);

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

        $supportConversation->messages()->create([
            'sender_type' => 'staff',
            'sender_user_id' => $agent?->id,
            'body' => trim($data['body']),
            'is_automated' => false,
        ]);

        $supportConversation->update([
            'last_message_at' => now(),
            'needs_human' => false,
            'status' => 'replied',
            'assigned_to' => $supportConversation->assigned_to ?: $agent?->id,
        ]);

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
        ]);

        $actor = $this->roleView->actorForAudit($request->user('admin'));
        $agent = $this->workspace->actingAgent();

        $last = $supportConversation->messages()->latest('id')->first();
        $ticket = $this->tickets->create([
            'customer_id' => $supportConversation->customer_id,
            'guest_name' => $supportConversation->customer_id
                ? null
                : ($supportConversation->user?->name ?: 'Guest'),
            'guest_email' => $supportConversation->customer_id ? null : $supportConversation->user?->email,
            'guest_phone' => $supportConversation->customer_id ? null : $supportConversation->user?->phone,
            'subject' => ($data['subject'] ?? null) ?: 'Support conversation #'.$supportConversation->id,
            'category' => ($data['category'] ?? null) ?: 'general',
            'priority' => ($data['priority'] ?? null) ?: 'normal',
            'description' => ($data['body'] ?? null) ?: ($last?->body ?: 'Created from support conversation #'.$supportConversation->id),
            'source' => 'chatbot',
            'assigned_to' => $agent?->id,
            'status' => 'open',
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
