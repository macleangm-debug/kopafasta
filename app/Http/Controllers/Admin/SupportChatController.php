<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportChatController extends Controller
{
    public function index(): View
    {
        $conversations = SupportConversation::query()
            ->with(['customer', 'user', 'assignedTo'])
            ->latest('last_message_at')
            ->paginate(30);

        $supportAgents = User::query()
            ->whereIn('role', ['admin', 'manager', 'officer', 'collector'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin.support-chats.index', compact('conversations', 'supportAgents'));
    }

    public function show(SupportConversation $supportConversation): View
    {
        $supportConversation->load(['customer', 'user', 'assignedTo', 'messages.senderUser']);

        $supportAgents = User::query()
            ->whereIn('role', ['admin', 'manager', 'officer', 'collector'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin.support-chats.show', compact('supportConversation', 'supportAgents'));
    }

    public function assign(Request $request, SupportConversation $supportConversation): RedirectResponse
    {
        $data = $request->validate([
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $supportConversation->update([
            'assigned_to' => $data['assigned_to'] ?? null,
            'status'      => filled($data['assigned_to'] ?? null) ? 'assigned' : 'open',
        ]);

        return back()->with('status', 'Conversation assignment updated.');
    }

    public function reply(Request $request, SupportConversation $supportConversation): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $supportConversation->messages()->create([
            'sender_type'     => 'staff',
            'sender_user_id'  => $request->user()?->id,
            'body'            => trim($data['body']),
            'is_automated'    => false,
        ]);

        $supportConversation->update([
            'last_message_at' => now(),
            'needs_human'     => false,
            'status'          => 'replied',
        ]);

        return back()->with('status', 'Reply sent.');
    }
}
