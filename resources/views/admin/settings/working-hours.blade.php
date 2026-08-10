<x-admin.layout title="Working hours" heading="Working hours" subheading="Office hours and Tanzania public holidays used for SLAs">
    @include('admin.settings._tabs', ['active' => 'working-hours'])

@if ($errors->any())
        <div class="mb-4 rounded-lg bg-rose-50 ring-1 ring-rose-200 px-4 py-3 text-sm text-rose-800">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.working-hours.save') }}" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-5 mb-6">
        @csrf @method('PUT')
        <div>
            <p class="text-xs uppercase tracking-widest text-brand font-semibold">Office hours</p>
            <p class="text-sm text-gray-600 mt-1">
                Affiliate and disbursement SLAs count <strong>working hours</strong> only — Mon–Fri within these hours, closed on weekends and public holidays.
                Example: “48 working hours” is about 6 office days (8 hours/day).
            </p>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <x-admin.input name="working_hours_start" label="Opens (HH:MM)" :value="$values['working_hours_start']" required />
            <x-admin.input name="working_hours_end" label="Closes (HH:MM)" :value="$values['working_hours_end']" required />
        </div>
        <div>
            <p class="text-sm font-medium text-gray-700 mb-2">Working days</p>
            <div class="flex flex-wrap gap-3">
                @foreach (['mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun'] as $key => $label)
                    <label class="inline-flex items-center gap-2 rounded-lg bg-gray-50 ring-1 ring-gray-200 px-3 py-2 text-sm">
                        <input type="checkbox" name="working_weekdays[]" value="{{ $key }}"
                               @checked(in_array($key, $values['working_weekdays'] ?? [], true))
                               class="rounded border-gray-300 text-brand">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>
        <div class="flex justify-end">
            <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-5 py-2 rounded-lg shadow-sm">Save working hours</button>
        </div>
    </form>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-5">
        <div>
            <p class="text-xs uppercase tracking-widest text-brand font-semibold">Public holidays (Tanzania)</p>
            <p class="text-sm text-gray-600 mt-1">Fixed national days are seeded through 2030. Add or remove variable dates (Eid, Easter, Maulid) as announced.</p>
        </div>

        <form method="POST" action="{{ route('admin.settings.working-hours.holidays.store') }}" class="grid sm:grid-cols-4 gap-3 items-end">
            @csrf
            <x-admin.input name="date" label="Date" type="date" :value="old('date')" required />
            <x-admin.input name="name" label="Name (EN)" :value="old('name')" required />
            <x-admin.input name="name_sw" label="Name (SW)" :value="old('name_sw')" />
            <button type="submit" class="bg-brand text-white font-semibold text-sm px-4 py-2.5 rounded-lg">Add holiday</button>
        </form>

        <div class="overflow-x-auto rounded-xl ring-1 ring-gray-100">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-4 py-2.5">Date</th>
                        <th class="px-4 py-2.5">Name</th>
                        <th class="px-4 py-2.5">Swahili</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($holidays as $holiday)
                        <tr>
                            <td class="px-4 py-2.5 font-medium text-gray-900 whitespace-nowrap">{{ $holiday->date?->format('D, d M Y') }}</td>
                            <td class="px-4 py-2.5 text-gray-800">{{ $holiday->name }}</td>
                            <td class="px-4 py-2.5 text-gray-600">{{ $holiday->name_sw ?: '—' }}</td>
                            <td class="px-4 py-2.5 text-right">
                                <form method="POST" action="{{ route('admin.settings.working-hours.holidays.destroy', $holiday) }}"
                                      @submit.prevent="window.confirmForm($el, {
                                          title: @js('Remove this holiday?'),
                                          message: @js('Remove this holiday?'),
                                          confirmLabel: @js('Remove'),
                                          confirmClass: 'bg-red-600 hover:bg-red-700 text-white',
                                          tone: 'warning',
                                      })">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold text-rose-700 hover:underline">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-500">No holidays yet. Run <code class="text-xs bg-gray-100 px-1 rounded">php artisan db:seed --class=PublicHolidaySeeder</code>.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin.layout>
