<x-admin.layout title="{{ str_starts_with(app()->getLocale(), 'sw') ? 'Hariri mgeni' : 'Edit Guest' }}" heading="" subheading="">
    @php $isSw = str_starts_with(app()->getLocale(), 'sw'); @endphp
    <div class="mb-4">
        <a href="{{ route('admin.customers.guests.show', $guest) }}" class="text-sm font-semibold text-brand hover:underline">← {{ $guest->displayName() }}</a>
    </div>

    <x-admin.letterhead
        kicker="{{ $isSw ? 'Mawasiliano ya mgeni' : 'Guest contact' }}"
        :title="$isSw ? 'Hariri mgeni' : 'Edit Guest'"
        :subtitle="'+'.$guest->phone" />

    <form method="POST" action="{{ route('admin.customers.guests.update', $guest) }}" class="mt-6 max-w-xl rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 space-y-4" data-no-draft>
        @csrf
        @method('PUT')
        <div class="grid sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">{{ $isSw ? 'Jina la kwanza' : 'First name' }}</label>
                <input type="text" name="first_name" value="{{ old('first_name', $guest->first_name) }}" required maxlength="80"
                       class="w-full rounded-xl border-gray-200 text-sm px-3 py-2.5 focus:border-brand focus:ring-brand/20">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">{{ $isSw ? 'Jina la mwisho' : 'Last name' }}</label>
                <input type="text" name="last_name" value="{{ old('last_name', $guest->last_name) }}" required maxlength="80"
                       class="w-full rounded-xl border-gray-200 text-sm px-3 py-2.5 focus:border-brand focus:ring-brand/20">
            </div>
        </div>
        <div>
            <x-site.phone-input
                name="guest_phone"
                :label="$isSw ? 'Simu' : 'Phone number'"
                :value="old('guest_phone', $guest->phone)"
                variant="rounded"
                :allow-country-change="false"
                :required="true"
            />
        </div>
        <button type="submit" class="w-full rounded-xl bg-brand text-white font-bold text-sm px-4 py-3">
            {{ $isSw ? 'Hifadhi mabadiliko' : 'Save changes' }}
        </button>
    </form>
</x-admin.layout>
