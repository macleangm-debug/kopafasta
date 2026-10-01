<x-admin.layout title="{{ str_starts_with(app()->getLocale(), 'sw') ? 'Ongeza mgeni' : 'Add Guest' }}" heading="" subheading="">
    @php $isSw = str_starts_with(app()->getLocale(), 'sw'); @endphp
    <div class="mb-4">
        <a href="{{ route('admin.customers.guests.index') }}" class="text-sm font-semibold text-brand hover:underline">← {{ $isSw ? 'Wageni' : 'Guests' }}</a>
    </div>

    <x-admin.letterhead
        kicker="{{ $isSw ? 'Dawati la wateja' : 'Customer desk' }}"
        :title="$isSw ? 'Ongeza mgeni' : 'Add Guest'"
        :subtitle="$isSw
            ? 'Jina la kwanza, jina la mwisho na simu tu. Hakuna mazungumzo, mwingiliano wala tiketi inayoundwa.'
            : 'First name, last name and phone only. No conversation, interaction or ticket is created.'" />

    <form method="POST" action="{{ route('admin.customers.guests.store') }}" class="mt-6 max-w-xl rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 space-y-4" data-no-draft>
        @csrf
        <div class="grid sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">{{ $isSw ? 'Jina la kwanza' : 'First name' }}</label>
                <input type="text" name="first_name" value="{{ old('first_name') }}" required maxlength="80"
                       class="w-full rounded-xl border-gray-200 text-sm px-3 py-2.5 focus:border-brand focus:ring-brand/20">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">{{ $isSw ? 'Jina la mwisho' : 'Last name' }}</label>
                <input type="text" name="last_name" value="{{ old('last_name') }}" required maxlength="80"
                       class="w-full rounded-xl border-gray-200 text-sm px-3 py-2.5 focus:border-brand focus:ring-brand/20">
            </div>
        </div>
        <div>
            <x-site.phone-input
                name="guest_phone"
                :label="$isSw ? 'Simu' : 'Phone number'"
                :value="old('guest_phone', $phone)"
                variant="rounded"
                :allow-country-change="false"
                :required="true"
            />
            <p class="text-xs text-gray-500 mt-1">{{ $isSw
                ? 'Kitambulisho cha Mgeni. Simu ile ile haitoi Mgeni wa pili.'
                : 'Guest identifier. The same phone never creates a duplicate Guest.' }}</p>
        </div>
        <button type="submit" class="w-full rounded-xl bg-brand text-white font-bold text-sm px-4 py-3">
            {{ $isSw ? 'Hifadhi mgeni' : 'Save Guest' }}
        </button>
    </form>
</x-admin.layout>
