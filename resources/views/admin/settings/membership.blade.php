<x-admin.layout title="Membership" heading="Membership Settings" subheading="Configure validity, renewals, grace period & reminders">
    @include('admin.settings._tabs', ['active' => 'membership'])
    @if (session('status'))<div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif

    <form method="POST" action="{{ route('admin.settings.membership.save') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Validity</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-admin.input name="duration_days" label="Membership duration (days)" type="number" :value="$values['duration_days'] ?? 365" required />
                <x-admin.input name="max_expiry_years" label="Maximum expiry (years from issue)" type="number" :value="$values['max_expiry_years'] ?? 1" required />
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Renewal</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-admin.input name="renewal_fee"       label="Renewal fee" type="number" step="0.01" :value="$values['renewal_fee'] ?? 10000" required />
                <x-admin.input name="currency"          label="Currency (ISO 4217)" :value="$values['currency'] ?? 'TZS'" required />
                <x-admin.input name="grace_period_days" label="Grace period (days)" type="number" :value="$values['grace_period_days'] ?? 14" required />
            </div>
            <p class="mt-3 text-xs text-gray-500">During grace, members can still view dashboard & history but cannot apply for new loans.</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Reminder channels</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                @php $selected = $values['reminder_channels'] ?? ['sms','email']; @endphp
                @foreach (['sms' => 'SMS', 'email' => 'Email', 'push' => 'Push', 'whatsapp' => 'WhatsApp'] as $key => $label)
                    <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2">
                        <input type="checkbox" name="reminder_channels[]" value="{{ $key }}" @checked(in_array($key, $selected)) class="size-4 rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                        <span class="text-gray-800">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            <p class="mt-3 text-xs text-gray-500">Reminders are sent at 30, 14, 7 and 1 days before expiry.</p>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-5 py-2 rounded-lg shadow-sm">Save membership settings</button>
        </div>
    </form>
</x-admin.layout>
