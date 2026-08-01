@php($idx = $index)
<div data-requirement-row class="rounded-lg ring-1 ring-gray-200 bg-gray-50 p-4 grid grid-cols-1 md:grid-cols-12 gap-3 items-start">
    @if (! empty($row['id']))
        <input type="hidden" name="requirements[{{ $idx }}][id]" value="{{ $row['id'] }}">
    @endif
    <div class="md:col-span-4">
        <label class="block text-xs font-medium text-gray-600 mb-1">Document name</label>
        <input type="text" name="requirements[{{ $idx }}][name]" value="{{ $row['name'] ?? '' }}"
               placeholder="e.g. National ID (front)"
               class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm">
    </div>
    <div class="md:col-span-5">
        <label class="block text-xs font-medium text-gray-600 mb-1">Instructions</label>
        <input type="text" name="requirements[{{ $idx }}][description]" value="{{ $row['description'] ?? '' }}"
               placeholder="What the borrower should upload"
               class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm">
    </div>
    <div class="md:col-span-2 flex items-end pb-2">
        <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
            <input type="hidden" name="requirements[{{ $idx }}][is_required]" value="0">
            <input type="checkbox" name="requirements[{{ $idx }}][is_required]" value="1"
                   @checked($row['is_required'] ?? true)
                   class="rounded border-gray-300 text-brand focus:ring-brand">
            Required
        </label>
    </div>
    <div class="md:col-span-1 flex items-end justify-end pb-1">
        <button type="button" data-remove-requirement
                class="text-xs font-medium text-red-600 hover:text-red-700">Remove</button>
    </div>
</div>
