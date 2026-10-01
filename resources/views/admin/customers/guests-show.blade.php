<x-admin.layout title="Guest · {{ $guest->displayName() }}" heading="" subheading="">
    <div class="mb-4">
        <a href="{{ route('admin.customers.guests.index') }}" class="text-sm font-semibold text-brand hover:underline">← Guests</a>
    </div>

    <x-admin.letterhead
        kicker="Guest contact"
        :title="$guest->displayName()"
        :subtitle="'+'.$guest->phone.' · '.str_replace('_', ' ', $guest->source)" />

    <div class="mt-6 grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 space-y-4">
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 space-y-3">
                <p class="text-[11px] uppercase tracking-wider text-slate-500 font-semibold">Identity</p>
                <p class="text-sm"><span class="text-gray-500">First name</span><br><span class="font-semibold">{{ $guest->first_name }}</span></p>
                <p class="text-sm"><span class="text-gray-500">Last name</span><br><span class="font-semibold">{{ $guest->last_name ?: '—' }}</span></p>
                <p class="text-sm"><span class="text-gray-500">Phone</span><br><span class="font-semibold tabular-nums">+{{ $guest->phone }}</span></p>
                <p class="text-sm"><span class="text-gray-500">Status</span><br><span class="font-semibold capitalize">{{ $guest->registration_status }}</span></p>
                <p class="text-sm"><span class="text-gray-500">Contacts</span><br><span class="font-semibold tabular-nums">{{ $guest->contact_count }}</span></p>
                <p class="text-sm"><span class="text-gray-500">First / last</span><br>
                    <span class="text-gray-800">{{ $guest->first_contact_at?->format('d M Y H:i') ?? '—' }}</span>
                    ·
                    <span class="text-gray-800">{{ $guest->last_contact_at?->format('d M Y H:i') ?? '—' }}</span>
                </p>
            </div>
            <a href="{{ route('admin.support.interactions.new', [
                    'party' => 'non_member',
                    'channel' => 'phone',
                    'guest_first_name' => $guest->first_name,
                    'guest_last_name' => $guest->last_name,
                    'guest_phone' => $guest->phone,
                ]) }}"
               class="inline-flex w-full justify-center rounded-xl bg-brand text-white font-bold text-sm px-4 py-3">
                Record call
            </a>
        </div>

        <div class="lg:col-span-2 rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5">
            <p class="text-[11px] uppercase tracking-wider text-slate-500 font-semibold mb-3">Linked conversations</p>
            <div class="space-y-3">
                @forelse ($conversations as $conversation)
                    <a href="{{ route('admin.support.inbox.show', $conversation) }}"
                       class="block rounded-xl ring-1 ring-slate-200 hover:ring-brand/30 px-4 py-3">
                        <div class="flex items-center justify-between gap-3">
                            <p class="font-semibold text-gray-900">{{ $conversation->publicNumber() }}</p>
                            <p class="text-xs text-gray-500 capitalize">{{ $conversation->status }}</p>
                        </div>
                        <p class="text-sm text-gray-600 mt-1 truncate">{{ $conversation->topic ?: 'Support chat' }}</p>
                    </a>
                @empty
                    <p class="text-sm text-gray-500 py-6 text-center">No conversations yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-admin.layout>
