<x-admin.layout title="Support chats" heading="Live support chats" subheading="Borrower AI chat escalations and human-assist queue">
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <div class="mb-4 flex gap-3 text-sm">
        <a href="{{ route('admin.settings.chatbot') }}" class="font-semibold text-brand hover:underline">Manage chatbot FAQs →</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-left">Borrower</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-left">Human?</th>
                    <th class="px-4 py-3 text-left">Assigned</th>
                    <th class="px-4 py-3 text-left">Last activity</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($conversations as $conversation)
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-4 py-3">
                            @if ($conversation->customer)
                                {{ trim($conversation->customer->first_name.' '.$conversation->customer->last_name) }}
                            @elseif ($conversation->user)
                                {{ $conversation->user->name }}
                            @else
                                Guest
                            @endif
                        </td>
                        <td class="px-4 py-3 capitalize">{{ $conversation->status }}</td>
                        <td class="px-4 py-3">{{ $conversation->needs_human ? 'Yes' : 'No' }}</td>
                        <td class="px-4 py-3">{{ $conversation->assignedTo?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $conversation->last_message_at?->diffForHumans() ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.support-chats.show', $conversation) }}" class="text-amber-700 font-semibold hover:underline">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">No chat conversations yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($conversations->hasPages())
            <div class="px-4 py-3 border-t border-gray-100">{{ $conversations->links() }}</div>
        @endif
    </div>
</x-admin.layout>
