{{-- Document requirements from canonical DocumentType catalog. --}}
@php
    $groupDocNames = ['Group constitution', 'Group member roster'];
    $catalog = collect($documentTypes ?? []);
    $existing = collect(old('requirements', ($requirements ?? collect())->map(fn ($r) => [
        'id'          => $r->id ?? null,
        'name'        => $r->name ?? '',
        'description' => $r->description ?? '',
        'is_required' => (bool) ($r->is_required ?? true),
    ])->all()))
        ->reject(fn ($row) => in_array((string) ($row['name'] ?? ''), $groupDocNames, true))
        ->values();
@endphp

<div class="md:col-span-2 space-y-3">
    <div>
        <h3 class="text-sm font-semibold text-gray-900">Required documents</h3>
        <p class="text-xs text-gray-500 mt-1">
            Choices come from the KYC document type catalog. Templates stay in
            <a href="{{ route('admin.document-templates.index') }}" class="font-semibold text-amber-700 hover:underline">Documents / Templates</a>.
        </p>
    </div>

    <div class="grid sm:grid-cols-2 gap-2">
        @foreach ($catalog as $type)
            @php
                $match = $existing->first(fn ($row) => strcasecmp((string) ($row['name'] ?? ''), (string) $type->name) === 0
                    || strcasecmp((string) ($row['name'] ?? ''), (string) $type->code) === 0);
                $checked = $match !== null && (bool) ($match['is_required'] ?? true);
            @endphp
            <label class="flex items-start gap-2 rounded-lg bg-white ring-1 ring-gray-200 px-3 py-2 text-sm cursor-pointer"
                   x-data="{ on: @js($checked) }">
                <input type="checkbox" x-model="on" class="mt-0.5 rounded border-gray-300 text-brand focus:ring-brand">
                <template x-if="on">
                    <span>
                        @if (! empty($match['id']))
                            <input type="hidden" name="requirements[{{ $loop->index }}][id]" value="{{ $match['id'] }}">
                        @endif
                        <input type="hidden" name="requirements[{{ $loop->index }}][name]" value="{{ $type->name }}">
                        <input type="hidden" name="requirements[{{ $loop->index }}][description]" value="{{ $match['description'] ?? $type->name }}">
                        <input type="hidden" name="requirements[{{ $loop->index }}][is_required]" value="1">
                    </span>
                </template>
                <span>
                    <span class="font-medium text-gray-900">{{ $type->name }}</span>
                    <span class="block text-[11px] text-gray-500">{{ $type->code }}</span>
                </span>
            </label>
        @endforeach
    </div>

    @php $extraStart = $catalog->count(); @endphp
    @foreach ($existing as $row)
        @php
            $name = (string) ($row['name'] ?? '');
            $inCatalog = $catalog->contains(fn ($type) => strcasecmp((string) $type->name, $name) === 0 || strcasecmp((string) $type->code, $name) === 0);
        @endphp
        @if ($name === '' || $inCatalog)
            @continue
        @endif
        <div class="rounded-lg bg-amber-50 ring-1 ring-amber-100 px-3 py-2 text-sm" x-data="{ on: true }">
            <label class="flex items-start gap-2 cursor-pointer">
                <input type="checkbox" x-model="on" class="mt-0.5 rounded border-gray-300 text-brand focus:ring-brand">
                <template x-if="on">
                    <span>
                        <input type="hidden" name="requirements[{{ $extraStart }}][id]" value="{{ $row['id'] }}">
                        <input type="hidden" name="requirements[{{ $extraStart }}][name]" value="{{ $name }}">
                        <input type="hidden" name="requirements[{{ $extraStart }}][description]" value="{{ $row['description'] ?? '' }}">
                        <input type="hidden" name="requirements[{{ $extraStart }}][is_required]" value="1">
                    </span>
                </template>
                <span>
                    <span class="font-medium text-amber-950">{{ $name }}</span>
                    <span class="block text-[11px] text-amber-800">Existing product requirement — add new types in the document catalog, not as free text here.</span>
                </span>
            </label>
        </div>
        @php $extraStart++; @endphp
    @endforeach
</div>
