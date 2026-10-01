<x-admin.layout title="Guests" heading="" subheading="">
    @php
        $activeCount = \App\Models\SupportGuest::query()->where('registration_status', 'guest')->count();
    @endphp

    <section class="mb-6">
        <div class="rounded-2xl overflow-hidden ring-1 ring-brand/15 shadow-sm">
            <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-6 text-white">
                <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-brand-gold">Customer desk</p>
                <h1 class="text-2xl sm:text-3xl font-bold mt-1">Guests</h1>
                <p class="text-sm text-white/75 mt-2 max-w-2xl">
                    People who contacted Kopafasta before registering. Phone is the identity key — one Guest per number.
                    When they register, they leave this list and history stays on their Member/Partner record.
                </p>
            </div>
            <div class="bg-white px-6 py-5 flex flex-wrap items-center gap-3 justify-between">
                <p class="text-sm text-gray-600">
                    <span class="font-bold text-gray-900 tabular-nums">{{ number_format($activeCount) }}</span> active Guests
                </p>
                <a href="{{ route('admin.customers.guests.create') }}"
                   class="inline-flex items-center gap-2 bg-brand-gold hover:brightness-95 text-brand font-bold text-sm px-5 py-2.5 rounded-xl shadow-sm ring-1 ring-brand/15">
                    {{ str_starts_with(app()->getLocale(), 'sw') ? '+ Ongeza mgeni' : '+ Add Guest' }}
                </a>
            </div>
        </div>
    </section>

    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input type="search" name="q" value="{{ $q }}" placeholder="Name or phone…"
               class="rounded-xl border-gray-200 text-sm px-4 py-2.5 min-w-[16rem] focus:border-brand focus:ring-brand/20">
        <button class="rounded-xl bg-brand text-white text-sm font-semibold px-4 py-2.5">Search</button>
    </form>

    <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3 font-semibold">Name</th>
                    <th class="px-4 py-3 font-semibold">Phone</th>
                    <th class="px-4 py-3 font-semibold">Source</th>
                    <th class="px-4 py-3 font-semibold">Contacts</th>
                    <th class="px-4 py-3 font-semibold">Last contact</th>
                    <th class="px-4 py-3 font-semibold"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($guests as $guest)
                    <tr class="hover:bg-brand-muted/20">
                        <td class="px-4 py-3 font-semibold text-gray-900">{{ $guest->displayName() }}</td>
                        <td class="px-4 py-3 tabular-nums text-gray-700">+{{ $guest->phone }}</td>
                        <td class="px-4 py-3 text-gray-600 capitalize">{{ str_replace('_', ' ', $guest->source) }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ $guest->contact_count }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $guest->last_contact_at?->timezone(config('app.timezone'))->format('d M Y H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.customers.guests.show', $guest) }}" class="text-brand font-semibold hover:underline">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-gray-500">No active Guests yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $guests->links() }}</div>
</x-admin.layout>
