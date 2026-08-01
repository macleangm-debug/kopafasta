<x-admin.layout title="Location master" heading="Location master" subheading="Manage wards used in borrower residence and next-of-kin address forms">
    @include('admin.settings._tabs', ['active' => 'locations'])

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <div class="mb-5 grid grid-cols-3 gap-3 max-w-xl">
        <div class="bg-white rounded-lg ring-1 ring-gray-200 px-4 py-3">
            <div class="text-xs uppercase tracking-widest text-gray-500">Regions</div>
            <div class="text-xl font-semibold text-gray-900">{{ number_format($stats['regions']) }}</div>
        </div>
        <div class="bg-white rounded-lg ring-1 ring-gray-200 px-4 py-3">
            <div class="text-xs uppercase tracking-widest text-gray-500">Districts</div>
            <div class="text-xl font-semibold text-gray-900">{{ number_format($stats['districts']) }}</div>
        </div>
        <div class="bg-white rounded-lg ring-1 ring-gray-200 px-4 py-3">
            <div class="text-xs uppercase tracking-widest text-gray-500">Wards</div>
            <div class="text-xl font-semibold text-gray-900">{{ number_format($stats['wards']) }}</div>
        </div>
    </div>

    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <form method="GET" action="{{ route('admin.settings.locations.index') }}" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Country</label>
                <select name="country_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @foreach ($countries as $country)
                        <option value="{{ $country->id }}" @selected($countryId == $country->id)>{{ $country->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Region</label>
                <select name="region_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">All regions</option>
                    @foreach ($regions as $region)
                        <option value="{{ $region->id }}" @selected($regionId == $region->id)>{{ $region->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">District</label>
                <select name="district_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">All districts</option>
                    @foreach ($districts as $district)
                        <option value="{{ $district->id }}" @selected($districtId == $district->id)>{{ $district->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Search</label>
                <input type="search" name="q" value="{{ $search }}" placeholder="Ward name…"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-44">
            </div>
            <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand text-sm font-semibold px-4 py-2 rounded-lg">Filter</button>
        </form>

        <a href="{{ route('admin.settings.locations.create') }}"
           class="inline-flex bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-4 py-2 rounded-lg">
            + Add ward
        </a>
    </div>

    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-5 py-3">Ward</th>
                    <th class="px-5 py-3">District</th>
                    <th class="px-5 py-3">Region</th>
                    <th class="px-5 py-3">Country</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($wards as $ward)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 font-medium">{{ $ward->name }}</td>
                        <td class="px-5 py-3 text-gray-600">{{ $ward->district?->name ?? '—' }}</td>
                        <td class="px-5 py-3 text-gray-600">{{ $ward->district?->region?->name ?? '—' }}</td>
                        <td class="px-5 py-3 text-gray-600">{{ $ward->district?->region?->country?->name ?? '—' }}</td>
                        <td class="px-5 py-3">
                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $ward->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600' }}">
                                {{ $ward->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right space-x-2">
                            <a href="{{ route('admin.settings.locations.edit', $ward) }}" class="text-brand hover:underline text-xs">Edit</a>
                            <form method="POST" action="{{ route('admin.settings.locations.destroy', $ward) }}" class="inline"
                                  onsubmit="return confirm('Remove this ward?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline text-xs">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-10 text-center text-gray-500">
                            No wards found. Run the location seeder or add wards for districts that need them.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($wards->hasPages())
        <div class="mt-4">{{ $wards->links() }}</div>
    @endif
</x-admin.layout>
