<div id="customer-notifications" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-sm font-semibold text-gray-900">Notifications</h3>
        <p class="text-xs text-gray-500 mt-0.5">SMS, email, and in-app messages sent to this customer</p>
    </div>
    <ul class="divide-y divide-gray-100">
        @forelse ($dossier['notifications'] as $note)
            <li class="px-5 py-4 text-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-medium text-gray-900">{{ ucfirst(str_replace('_', ' ', $note->channel ?? 'message')) }}</p>
                        <p class="text-gray-600 mt-1">{{ $note->message }}</p>
                    </div>
                    <span class="shrink-0 text-xs text-gray-500">{{ $note->sent_at?->format('d M Y H:i') ?? $note->created_at?->format('d M Y H:i') }}</span>
                </div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400 mt-2">{{ $note->status ?? 'sent' }}</p>
            </li>
        @empty
            <li class="px-5 py-8 text-center text-gray-500 text-sm">No notifications logged.</li>
        @endforelse
    </ul>
</div>
