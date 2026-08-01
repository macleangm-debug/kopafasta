<x-site.vendor-layout title="Notifications" active="notifications">
    <h1 class="text-2xl font-extrabold mb-5">Notifications</h1>

    <div class="glass-card rounded-2xl ring-1 ring-brand/10">
        @if ($notifications->isEmpty())
            <p class="p-8 text-center text-sm text-gray-500">No notifications yet.</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($notifications as $n)
                    <li class="px-5 py-4 flex items-start gap-3">
                        <span class="size-9 rounded-full bg-indigo-100 text-brand grid place-items-center text-xs font-bold uppercase">{{ substr($n->channel ?? 'N', 0, 1) }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold">{{ $n->subject ?? 'Notification' }}</p>
                            <p class="text-sm text-gray-600">{{ $n->message ?? '' }}</p>
                            <p class="text-[11px] text-gray-400 mt-1 uppercase">{{ $n->channel }} · {{ $n->created_at?->diffForHumans() }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="mt-6">{{ $notifications->links() }}</div>
</x-site.vendor-layout>
