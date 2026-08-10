<x-admin.layout title="Support chat" heading="Conversation #{{ $supportConversation->id }}" subheading="Reply to borrower or assign a support agent">
<div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-4">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5 max-h-[32rem] overflow-y-auto space-y-3">
                @forelse ($supportConversation->messages as $message)
                    <div class="rounded-lg px-4 py-3 text-sm {{ $message->sender_type === 'staff' ? 'bg-amber-50 ml-8' : ($message->sender_type === 'bot' ? 'bg-gray-50 mr-8' : 'bg-sky-50 mr-8') }}">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 mb-1">
                            {{ $message->sender_type }}
                            @if ($message->is_automated) · automated @endif
                            · {{ $message->created_at?->format('d M H:i') }}
                        </p>
                        <p class="whitespace-pre-wrap text-gray-800">{{ $message->body }}</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 text-center py-8">No messages in this conversation yet.</p>
                @endforelse
            </div>

            <form method="POST" action="{{ route('admin.support-chats.reply', $supportConversation) }}" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5 space-y-3">
                @csrf
                <label class="block text-sm font-semibold text-gray-900">Staff reply</label>
                <textarea name="body" rows="4" required class="w-full rounded-lg border-gray-300 text-sm"></textarea>
                <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-5 py-2 rounded-lg">Send reply</button>
            </form>
        </div>

        <div class="space-y-4">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5 text-sm space-y-2">
                <p><span class="text-gray-500">Status:</span> <span class="font-semibold capitalize">{{ $supportConversation->status }}</span></p>
                <p><span class="text-gray-500">Needs human:</span> {{ $supportConversation->needs_human ? 'Yes' : 'No' }}</p>
                @if ($supportConversation->customer)
                    <p><span class="text-gray-500">Customer:</span> {{ $supportConversation->customer->customer_number }}</p>
                @endif
            </div>

            <form method="POST" action="{{ route('admin.support-chats.assign', $supportConversation) }}" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5 space-y-3">
                @csrf
                <label class="block text-sm font-semibold text-gray-900">Assign support agent</label>
                <select name="assigned_to" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="">Unassigned</option>
                    @foreach ($supportAgents as $agent)
                        <option value="{{ $agent->id }}" @selected($supportConversation->assigned_to === $agent->id)>{{ $agent->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="w-full bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-4 py-2 rounded-lg">Update assignment</button>
            </form>

            <a href="{{ route('admin.support-chats.index') }}" class="inline-block text-sm font-semibold text-brand hover:underline">← All chats</a>
        </div>
    </div>
</x-admin.layout>
