<x-admin.layout title="Guest · {{ $guest->displayName() }}" heading="" subheading="">
    @php $isSw = str_starts_with(app()->getLocale(), 'sw'); @endphp
    <div class="mb-4">
        <a href="{{ route('admin.customers.guests.index') }}" class="text-sm font-semibold text-brand hover:underline">← {{ $isSw ? 'Wageni' : 'Guests' }}</a>
    </div>

    <x-admin.letterhead
        kicker="{{ $isSw ? 'Mawasiliano ya mgeni' : 'Guest contact' }}"
        :title="$guest->displayName()"
        :subtitle="'+'.$guest->phone.' · '.str_replace('_', ' ', $guest->source)" />

    <div class="mt-6 grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 space-y-4">
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[11px] uppercase tracking-wider text-slate-500 font-semibold">{{ $isSw ? 'Utambulisho' : 'Identity' }}</p>
                    @if ($guest->isActiveGuest())
                        <a href="{{ route('admin.customers.guests.edit', $guest) }}" class="text-xs font-semibold text-brand hover:underline">{{ $isSw ? 'Hariri' : 'Edit' }}</a>
                    @endif
                </div>
                <p class="text-sm"><span class="text-gray-500">{{ $isSw ? 'Jina la kwanza' : 'First name' }}</span><br><span class="font-semibold">{{ $guest->first_name }}</span></p>
                <p class="text-sm"><span class="text-gray-500">{{ $isSw ? 'Jina la mwisho' : 'Last name' }}</span><br><span class="font-semibold">{{ $guest->last_name ?: '—' }}</span></p>
                <p class="text-sm"><span class="text-gray-500">{{ $isSw ? 'Simu' : 'Phone' }}</span><br><span class="font-semibold tabular-nums">+{{ $guest->phone }}</span></p>
                @if ($guest->name_mismatch_at)
                    <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-3 py-2.5 text-xs text-amber-900 leading-relaxed">
                        <p class="font-semibold">{{ $isSw ? 'Jina lililotolewa linatofautiana na wasifu wa Mgeni.' : 'Name provided in this interaction differs from Guest profile.' }}</p>
                        <p class="mt-1 text-amber-800">
                            {{ $isSw ? 'Lililotolewa:' : 'Presented as:' }}
                            <span class="font-semibold">{{ trim(($guest->presented_first_name ?? '').' '.($guest->presented_last_name ?? '')) ?: '—' }}</span>
                            <span class="text-amber-700">· {{ $guest->name_mismatch_at->format('d M Y H:i') }}</span>
                        </p>
                    </div>
                @endif
                <p class="text-sm"><span class="text-gray-500">{{ $isSw ? 'Hali' : 'Status' }}</span><br><span class="font-semibold capitalize">{{ $guest->registration_status }}</span></p>
                <p class="text-sm"><span class="text-gray-500">{{ $isSw ? 'Mawasiliano ya kwanza / ya mwisho' : 'First / last contact' }}</span><br>
                    <span class="text-gray-800">{{ $guest->first_contact_at?->format('d M Y H:i') ?? '—' }}</span>
                    ·
                    <span class="text-gray-800">{{ $guest->last_contact_at?->format('d M Y H:i') ?? '—' }}</span>
                </p>
            </div>

            <a href="{{ route('admin.support.interactions.new', [
                    'party' => 'non_member',
                    'channel' => 'phone',
                    'support_action' => 'interaction',
                    'guest_first_name' => $guest->first_name,
                    'guest_last_name' => $guest->last_name,
                    'guest_phone' => $guest->phone,
                ]) }}"
               class="inline-flex w-full justify-center rounded-xl bg-brand text-white font-bold text-sm px-4 py-3">
                {{ $isSw ? 'Rekodi simu' : 'Record phone call' }}
            </a>
            <a href="{{ route('admin.support.interactions.new', [
                    'party' => 'non_member',
                    'support_action' => 'ticket',
                    'guest_first_name' => $guest->first_name,
                    'guest_last_name' => $guest->last_name,
                    'guest_phone' => $guest->phone,
                ]) }}"
               class="inline-flex w-full justify-center rounded-xl bg-white text-brand font-bold text-sm px-4 py-3 ring-1 ring-brand/20 hover:bg-brand-muted/40">
                {{ $isSw ? 'Unda tiketi' : 'Create ticket' }}
            </a>
            <p class="text-[11px] text-gray-500 text-center leading-relaxed">
                {{ $isSw
                    ? 'Mgeni hawezi kupokea mazungumzo yanayoanzishwa na wafanyakazi. Gumzo huanzishwa na mgeni kupitia Usaidizi wa umma.'
                    : 'Staff cannot start an in-app chat with a Guest. Guests initiate chat through public Support.' }}
            </p>
        </div>

        <div class="lg:col-span-2 space-y-4">
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5">
                <p class="text-[11px] uppercase tracking-wider text-slate-500 font-semibold mb-3">{{ $isSw ? 'Mazungumzo yaliyoanzishwa na mgeni' : 'Guest-initiated conversations' }}</p>
                <div class="space-y-3">
                    @forelse ($conversations as $conversation)
                        <a href="{{ route('admin.support.inbox.show', $conversation) }}"
                           class="block rounded-xl ring-1 ring-slate-200 hover:ring-brand/30 px-4 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-semibold text-gray-900">{{ $conversation->publicNumber() }}</p>
                                <p class="text-xs text-gray-500 capitalize">{{ $conversation->status }}</p>
                            </div>
                            <p class="text-sm text-gray-600 mt-1 truncate">{{ $conversation->topic ?: ($isSw ? 'Gumzo la usaidizi' : 'Support chat') }}</p>
                        </a>
                    @empty
                        <p class="text-sm text-gray-500 py-4 text-center">{{ $isSw ? 'Hakuna mazungumzo bado — si lazima.' : 'No conversations yet — that is fine.' }}</p>
                    @endforelse
                </div>
            </div>

            @if ($interactions->isNotEmpty())
                <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5">
                    <p class="text-[11px] uppercase tracking-wider text-slate-500 font-semibold mb-3">{{ $isSw ? 'Simu / mwingiliano uliorekodiwa' : 'Recorded calls / interactions' }}</p>
                    <div class="space-y-3">
                        @foreach ($interactions as $interaction)
                            <a href="{{ route('admin.support.inbox.show', $interaction) }}"
                               class="block rounded-xl ring-1 ring-slate-200 hover:ring-brand/30 px-4 py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="font-semibold text-gray-900 capitalize">{{ str_replace('_', ' ', $interaction->channel) }}</p>
                                    <p class="text-xs text-gray-500">{{ $interaction->updated_at?->format('d M Y H:i') }}</p>
                                </div>
                                <p class="text-sm text-gray-600 mt-1 truncate">{{ $interaction->topic ?: ($interaction->resolution_note ?: '—') }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($tickets->isNotEmpty())
                <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5">
                    <p class="text-[11px] uppercase tracking-wider text-slate-500 font-semibold mb-3">{{ $isSw ? 'Tiketi' : 'Tickets' }}</p>
                    <div class="space-y-3">
                        @foreach ($tickets as $ticket)
                            <a href="{{ route('admin.support-tickets.show', $ticket) }}"
                               class="block rounded-xl ring-1 ring-slate-200 hover:ring-brand/30 px-4 py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="font-semibold text-gray-900">{{ $ticket->ticket_number ?? ('#'.$ticket->id) }}</p>
                                    <p class="text-xs text-gray-500 capitalize">{{ $ticket->status }}</p>
                                </div>
                                <p class="text-sm text-gray-600 mt-1 truncate">{{ $ticket->subject ?: '—' }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-admin.layout>
