<x-admin.layout title="New Guest / Record Call" heading="" subheading="">
    <div class="mb-4">
        <a href="{{ route('admin.customers.guests.index') }}" class="text-sm font-semibold text-brand hover:underline">← Guests</a>
    </div>

    <x-admin.letterhead
        kicker="Customer Support"
        title="New Guest / Record Call"
        subtitle="Search or enter the phone first. Existing Members/Partners are opened instead of creating a Guest." />

    <form method="POST" action="{{ route('admin.customers.guests.store') }}" class="mt-6 max-w-xl rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 space-y-4" data-no-draft>
        @csrf
        <div>
            <x-site.phone-input
                name="guest_phone"
                label="Phone number"
                :value="old('guest_phone', $phone)"
                variant="rounded"
                :allow-country-change="false"
                :required="true"
            />
            <p class="text-xs text-gray-500 mt-1">Primary Guest identifier. Same phone never creates a duplicate Guest.</p>
        </div>
        <div class="grid sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">First name</label>
                <input type="text" name="first_name" value="{{ old('first_name') }}" required maxlength="80"
                       class="w-full rounded-xl border-gray-200 text-sm px-3 py-2.5 focus:border-brand focus:ring-brand/20">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Last name</label>
                <input type="text" name="last_name" value="{{ old('last_name') }}" required maxlength="80"
                       class="w-full rounded-xl border-gray-200 text-sm px-3 py-2.5 focus:border-brand focus:ring-brand/20">
            </div>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">Channel</label>
            <select name="channel" class="w-full rounded-xl border-gray-200 text-sm px-3 py-2.5 focus:border-brand focus:ring-brand/20">
                <option value="phone" @selected(old('channel', $channel) === 'phone')>Phone call</option>
                <option value="walk_in" @selected(old('channel', $channel) === 'walk_in')>Walk-in</option>
                <option value="other" @selected(old('channel', $channel) === 'other')>Other</option>
            </select>
        </div>
        <button type="submit" class="w-full rounded-xl bg-brand text-white font-bold text-sm px-4 py-3">
            Continue
        </button>
    </form>
</x-admin.layout>
