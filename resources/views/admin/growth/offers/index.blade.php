<x-admin.layout title="Offers" heading="Offers" subheading="Create Plus offers here. Eligibility dimensions come from Settings Hub.">
    <form method="post" action="{{ route('admin.growth.offers.store') }}" class="rounded-2xl bg-white ring-1 ring-brand/10 p-6 space-y-3 mb-6"
          onsubmit="event.preventDefault(); confirmForm(this, { title: 'Save this offer?' })">
        @csrf
        <h2 class="font-semibold text-gray-900">New offer</h2>
        <x-admin.input name="title" label="Title" required />
        <x-admin.textarea name="body" label="What the member gets" rows="4" />
        <x-admin.select name="tier" label="Offer tier" :options="['standard'=>'Standard','silver'=>'Silver','gold'=>'Gold','platinum'=>'Platinum']" />
        <x-admin.select name="country_code" label="Country" :options="['' => 'Every country'] + ($dimensions['country_code']['options'] ?? [])" />
        <p class="text-xs font-semibold text-gray-700">Visible to grades</p>
        <div class="flex flex-wrap gap-3 text-sm">
            @foreach (['bronze','silver','gold','platinum'] as $grade)
                <label class="inline-flex items-center gap-1.5">
                    <input type="checkbox" name="eligible_grades[]" value="{{ $grade }}" class="rounded border-gray-300 text-brand">
                    {{ ucfirst($grade) }}
                </label>
            @endforeach
        </div>
        <label class="text-sm inline-flex items-center gap-2">
            <input type="checkbox" name="plus_only" value="1" checked class="rounded border-gray-300 text-brand">
            Plus members only
        </label>
        <label class="text-sm inline-flex items-center gap-2">
            <input type="checkbox" name="active" value="1" checked class="rounded border-gray-300 text-brand">
            Publish
        </label>
        <button class="rounded-xl bg-brand text-white px-4 py-2 text-sm font-semibold">Save offer</button>
    </form>

    <div class="hidden md:block overflow-x-auto rounded-2xl bg-white ring-1 ring-brand/10 mb-4">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">Offer</th>
                    <th class="px-4 py-3">Tier</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($offers as $offer)
                    <tr class="border-t border-gray-100">
                        <td class="px-4 py-3">
                            <p class="font-semibold">{{ $offer->title }}</p>
                            <p class="text-xs text-gray-500">{{ \Illuminate\Support\Str::limit($offer->body, 80) }}</p>
                        </td>
                        <td class="px-4 py-3">{{ $offer->tier }}</td>
                        <td class="px-4 py-3">{{ $offer->active ? 'Active' : 'Off' }}{{ $offer->plus_only ? ' · Plus only' : '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-8 text-center text-gray-500">No offers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="md:hidden space-y-3">
        @forelse ($offers as $offer)
            <article class="rounded-2xl bg-white ring-1 ring-gray-200 p-4">
                <p class="text-[11px] uppercase tracking-wider text-brand font-semibold">{{ $offer->tier }} · {{ $offer->active ? 'Active' : 'Off' }}{{ $offer->plus_only ? ' · Plus only' : '' }}</p>
                <p class="font-semibold text-gray-900 mt-1">{{ $offer->title }}</p>
                <p class="text-sm text-gray-600 mt-1">{{ $offer->body }}</p>
            </article>
        @empty
            <p class="text-sm text-gray-500">No offers yet.</p>
        @endforelse
    </div>
</x-admin.layout>
