<x-admin.layout title="Inbox" heading="" subheading="">
    <x-admin.letterhead
        kicker="Customer Support"
        title="Inbox"
        subtitle="Waiting and assigned conversations. Reply without leaving this workspace." />

    <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
        <ul class="divide-y divide-gray-100">
            @forelse ($conversations as $item)
                <li>
                    <a href="{{ $item['url'] }}" class="flex items-start justify-between gap-3 px-5 py-4 hover:bg-brand-muted/20">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ $item['name'] }}</p>
                            @if (! empty($item['guest_label']))
                                <p class="text-[11px] font-semibold text-amber-700">{{ $item['guest_label'] }}</p>
                            @endif
                            <p class="text-xs text-gray-500 mt-0.5 truncate">{{ $item['preview'] ?: 'No messages yet' }}</p>
                            <p class="text-[11px] text-gray-400 mt-1">
                                {{ $item['needs_human'] ? 'Needs human' : ucfirst($item['status']) }}
                                @if ($item['waiting_label']) · {{ $item['waiting_label'] }} @endif
                            </p>
                        </div>
                        <span class="shrink-0 text-xs font-semibold text-brand">Open →</span>
                    </a>
                </li>
            @empty
                <li class="px-5 py-12 text-center text-sm text-gray-500">
                    Inbox is empty. Existing support chats and human-assist queue appear here once members escalate or staff assign a conversation.
                </li>
            @endforelse
        </ul>
    </div>
</x-admin.layout>
