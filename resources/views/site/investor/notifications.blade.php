<x-site.investor-layout title="Notifications — Investor" active="notifications">
    <h1 class="text-2xl lg:text-3xl font-bold tracking-tight mb-6">Notifications</h1>

    <div class="glass-card rounded-2xl ring-1 ring-brand/10 divide-y divide-slate-100">
        @forelse ($notifications as $n)
            <div class="p-4 flex items-start gap-3">
                <div class="size-9 rounded-full bg-emerald-100 text-brand grid place-items-center font-bold text-xs uppercase shrink-0">{{ substr($n->channel ?? 'N', 0, 2) }}</div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-sm">{{ $n->title ?? $n->subject ?? 'Notification' }}</p>
                    <p class="text-sm text-slate-600">{{ $n->body ?? $n->message ?? '' }}</p>
                    <p class="text-xs text-slate-400 mt-1">{{ $n->created_at?->diffForHumans() }}</p>
                </div>
            </div>
        @empty
            <div class="p-10 text-center text-sm text-gray-500">No notifications yet.</div>
        @endforelse
    </div>
    <div class="mt-6">{{ $notifications->links() }}</div>
</x-site.investor-layout>
