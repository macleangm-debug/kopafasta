@php $r = $ward ?? null; @endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">District</label>
        <select name="district_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">Select district…</option>
            @foreach ($regions as $region)
                <optgroup label="{{ $region->name }}">
                    @foreach ($region->districts as $district)
                        <option value="{{ $district->id }}" @selected(old('district_id', $r?->district_id) == $district->id)>
                            {{ $district->name }}
                        </option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        @error('district_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>

    <x-admin.input name="name" label="Ward name" :value="old('name', $r?->name)" required maxlength="100" />

    <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2 self-end">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $r?->is_active ?? true)) class="rounded border-gray-300 text-brand">
        <span>Active (shown in borrower address forms)</span>
    </label>
</div>
