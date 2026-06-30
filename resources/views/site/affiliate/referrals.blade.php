<x-site.affiliate-layout title="Referrals" active="referrals">
    <h1 class="text-2xl font-bold mb-4">Referral activity</h1>
    <div class="grid sm:grid-cols-3 gap-3 mb-6">
        @foreach ($breakdown as $label => $count)
            <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
                <p class="text-xs uppercase tracking-wide text-gray-500">{{ ucfirst(str_replace('_', ' ', $label)) }}</p>
                <p class="text-2xl font-bold mt-1">{{ $count }}</p>
            </div>
        @endforeach
    </div>
    <div class="rounded-2xl border border-gray-200 bg-white overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
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
                    <tr><td colspan="2" class="px-4 py-6 text-gray-500">No referral events yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-site.affiliate-layout>
