<x-site.affiliate-layout title="Referrals" active="referrals">
    <div class="mb-6">
        <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-1">Attribution</p>
        <h1 class="text-2xl font-bold">Referral activity</h1>
        <p class="text-sm text-gray-500 mt-1">Track clicks, registrations, and applications from your links.</p>
    </div>

    <div class="grid sm:grid-cols-3 gap-3 mb-6">
        @foreach ($breakdown as $label => $count)
            <div class="glass-card p-4">
                <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">{{ ucfirst(str_replace('_', ' ', $label)) }}</p>
                <p class="text-2xl font-bold mt-1 tabular-nums">{{ $count }}</p>
            </div>
        @endforeach
    </div>

    <div class="glass-card overflow-hidden">
        <div class="hidden sm:block overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50/80 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="text-left px-4 py-3">Event</th>
                        <th class="text-left px-4 py-3">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($recent as $event)
                        <tr>
                            <td class="px-4 py-3">{{ ucfirst(str_replace('_', ' ', $event->event_type)) }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $event->created_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="px-4 py-8 text-center text-gray-500">No referral events yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="sm:hidden divide-y divide-gray-100">
            @forelse ($recent as $event)
                <div class="px-4 py-3 flex items-center justify-between gap-3">
                    <span class="text-sm font-medium">{{ ucfirst(str_replace('_', ' ', $event->event_type)) }}</span>
                    <span class="text-xs text-gray-500 shrink-0">{{ $event->created_at?->diffForHumans() }}</span>
                </div>
            @empty
                <div class="p-8">
                    <x-site.empty-state icon="🔗" title="No referral events yet" description="Share your affiliate link to start tracking activity." :action-url="route('site.affiliate.dashboard')" action-label="Back to dashboard" class="!p-6 border-0 shadow-none" />
                </div>
            @endforelse
        </div>
    </div>
</x-site.affiliate-layout>
