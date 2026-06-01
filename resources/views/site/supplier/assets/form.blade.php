<x-site.supplier-layout :title="$asset ? 'Edit asset' : 'Upload asset'" active="assets">
    <h1 class="text-2xl font-bold mb-6">{{ $asset ? 'Edit asset' : 'Upload asset' }}</h1>
    <form method="POST" action="{{ $asset ? route('site.supplier.assets.update', $asset) : route('site.supplier.assets.store') }}" class="max-w-2xl bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-4">
        @csrf
        @if ($asset) @method('PUT') @endif
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Category</label>
            <select name="category" required class="w-full rounded-lg border-gray-300 text-sm">
                @foreach ($categories as $key => $label)
                    <option value="{{ $key }}" @selected(old('category', $asset?->category) === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="block text-xs font-medium text-gray-600 mb-1">Title</label><input name="title" value="{{ old('title', $asset?->title) }}" required class="w-full rounded-lg border-gray-300 text-sm"></div>
        <div><label class="block text-xs font-medium text-gray-600 mb-1">Description</label><textarea name="description" rows="3" class="w-full rounded-lg border-gray-300 text-sm">{{ old('description', $asset?->description) }}</textarea></div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div><label class="block text-xs font-medium text-gray-600 mb-1">Asset value</label><input type="number" step="0.01" name="asset_value" value="{{ old('asset_value', $asset?->asset_value ?? 0) }}" required class="w-full rounded-lg border-gray-300 text-sm"></div>
            <div><label class="block text-xs font-medium text-gray-600 mb-1">Supplier deposit</label><input type="number" step="0.01" name="supplier_deposit" value="{{ old('supplier_deposit', $asset?->supplier_deposit ?? 0) }}" required class="w-full rounded-lg border-gray-300 text-sm"></div>
            <div><label class="block text-xs font-medium text-gray-600 mb-1">Deposit markup %</label><input type="number" step="0.01" name="deposit_markup_percent" value="{{ old('deposit_markup_percent', $asset?->deposit_markup_percent ?? $vendor->deposit_markup_percent ?? 10) }}" class="w-full rounded-lg border-gray-300 text-sm"></div>
            <div><label class="block text-xs font-medium text-gray-600 mb-1">Weekly installment</label><input type="number" step="0.01" name="weekly_installment" value="{{ old('weekly_installment', $asset?->weekly_installment ?? 0) }}" required class="w-full rounded-lg border-gray-300 text-sm"></div>
            <div><label class="block text-xs font-medium text-gray-600 mb-1">Max tenure (months)</label><input type="number" name="max_tenure_months" value="{{ old('max_tenure_months', $asset?->max_tenure_months ?? 12) }}" required class="w-full rounded-lg border-gray-300 text-sm"></div>
            @if ($asset)
                <div class="flex items-center gap-2 pt-6"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $asset->is_active))><span class="text-sm">Active</span></div>
            @endif
        </div>
        <button class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">{{ $asset ? 'Save changes' : 'Upload asset' }}</button>
    </form>
</x-site.supplier-layout>
