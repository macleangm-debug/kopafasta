<x-admin.layout title="Personas" heading="Personas" subheading="Representative customer types for planning, previews and demos. Personas never alter real customers.">
    <form method="post" action="{{ route('admin.growth.personas.store') }}" class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 mb-6 grid sm:grid-cols-2 gap-4">
        @csrf
        <h2 class="sm:col-span-2 font-semibold">New persona</h2>
        <x-admin.input name="name" label="Name" required placeholder="Small Business Builder" />
        <x-admin.select name="role" label="Used for" :options="['borrower' => 'Borrower', 'plus' => 'Plus member', 'affiliate' => 'Affiliate']" required />
        <x-admin.textarea name="summary" label="Summary" rows="2" class="sm:col-span-2" />
        <x-admin.input name="traits" label="Traits (comma-separated)" placeholder="Gold, Plus member, Uses Business" />
        <x-admin.select name="grade" label="Typical grade" :options="['' => '—', 'bronze' => 'Bronze', 'silver' => 'Silver', 'gold' => 'Gold', 'platinum' => 'Platinum']" />
        <x-admin.input name="trust" label="Typical Trust" type="number" />
        <div class="sm:col-span-2">
            <button class="rounded-xl bg-brand text-white px-4 py-2 text-sm font-semibold">Save persona</button>
        </div>
    </form>

    <div class="hidden md:block overflow-x-auto rounded-2xl bg-white ring-1 ring-brand/10">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">Persona</th>
                    <th class="px-4 py-3">Used for</th>
                    <th class="px-4 py-3">Summary</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($personas as $persona)
                    <tr class="border-t border-gray-100">
                        <td class="px-4 py-3">
                            <p class="font-semibold">{{ $persona->name }}</p>
                            <p class="text-xs text-gray-500">{{ $persona->is_system ? 'system' : 'custom' }}</p>
                        </td>
                        <td class="px-4 py-3">{{ $persona->role }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ \Illuminate\Support\Str::limit($persona->summary, 80) }}</td>
                        <td class="px-4 py-3 text-right">
                            @unless ($persona->is_system)
                                <form method="post" action="{{ route('admin.growth.personas.destroy', $persona) }}" onsubmit="event.preventDefault(); confirmForm(this, { title: 'Remove persona?', tone: 'warning' })">
                                    @csrf @method('DELETE')
                                    <button class="text-xs font-semibold text-red-600">Remove</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No personas yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="md:hidden space-y-3">
        @forelse ($personas as $persona)
            <article class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ $persona->role }}{{ $persona->is_system ? ' · system' : '' }}</p>
                <h3 class="font-semibold mt-1">{{ $persona->name }}</h3>
                <p class="text-sm text-gray-600 mt-1">{{ $persona->summary }}</p>
                <div class="mt-3 flex flex-wrap gap-1">
                    @foreach ($persona->traits ?? [] as $trait)
                        <span class="text-[11px] rounded-full bg-brand-muted text-brand px-2 py-0.5">{{ $trait }}</span>
                    @endforeach
                </div>
                @unless ($persona->is_system)
                    <form method="post" action="{{ route('admin.growth.personas.destroy', $persona) }}" class="mt-3" onsubmit="event.preventDefault(); confirmForm(this, { title: 'Remove persona?', tone: 'warning' })">
                        @csrf @method('DELETE')
                        <button class="text-xs font-semibold text-red-600">Remove</button>
                    </form>
                @endunless
            </article>
        @empty
            <p class="text-sm text-gray-500">No personas yet.</p>
        @endforelse
    </div>
</x-admin.layout>
