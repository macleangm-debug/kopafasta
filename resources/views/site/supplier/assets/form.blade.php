<x-site.supplier-layout :title="$asset ? 'Edit asset' : 'Upload asset'" active="assets">
    <h1 class="text-2xl font-bold mb-6">{{ $asset ? 'Edit asset' : 'Upload asset' }}</h1>
    <form method="POST" enctype="multipart/form-data" action="{{ $asset ? route('site.supplier.assets.update', $asset) : route('site.supplier.assets.store') }}" class="max-w-2xl bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-4">
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
            <div><label class="block text-xs font-medium text-gray-600 mb-1">Serial / registration</label><input name="serial_number" value="{{ old('serial_number', $asset?->serial_number) }}" class="w-full rounded-lg border-gray-300 text-sm"></div>
            <div><label class="block text-xs font-medium text-gray-600 mb-1">Chassis number</label><input name="chassis_number" value="{{ old('chassis_number', $asset?->chassis_number) }}" class="w-full rounded-lg border-gray-300 text-sm"></div>
            <div><label class="block text-xs font-medium text-gray-600 mb-1">Engine number</label><input name="engine_number" value="{{ old('engine_number', $asset?->engine_number) }}" class="w-full rounded-lg border-gray-300 text-sm"></div>
            <div><label class="block text-xs font-medium text-gray-600 mb-1">Insurance policy</label><input name="insurance_policy_number" value="{{ old('insurance_policy_number', $asset?->insurance_policy_number) }}" class="w-full rounded-lg border-gray-300 text-sm"></div>
            <div><label class="block text-xs font-medium text-gray-600 mb-1">Insurance expiry</label><input type="date" name="insurance_expires_at" value="{{ old('insurance_expires_at', optional($asset?->insurance_expires_at)->format('Y-m-d')) }}" class="w-full rounded-lg border-gray-300 text-sm"></div>
            <div><label class="block text-xs font-medium text-gray-600 mb-1">Asset value</label><input type="text" inputmode="decimal" data-money-input="2" name="asset_value" value="{{ \App\Support\MoneyFormat::forInput(old('asset_value', $asset?->asset_value ?? 0), 2) }}" required class="w-full rounded-lg border-gray-300 text-sm"></div>
            <div><label class="block text-xs font-medium text-gray-600 mb-1">Supplier deposit</label><input type="text" inputmode="decimal" data-money-input="2" name="supplier_deposit" value="{{ \App\Support\MoneyFormat::forInput(old('supplier_deposit', $asset?->supplier_deposit ?? 0), 2) }}" required class="w-full rounded-lg border-gray-300 text-sm"></div>
            <div><label class="block text-xs font-medium text-gray-600 mb-1">Deposit markup %</label><input type="number" step="0.01" name="deposit_markup_percent" value="{{ old('deposit_markup_percent', $asset?->deposit_markup_percent ?? $vendor->deposit_markup_percent ?? $defaultDepositMarkupPercent ?? 10) }}" class="w-full rounded-lg border-gray-300 text-sm"></div>
            <div><label class="block text-xs font-medium text-gray-600 mb-1">Weekly installment</label><input type="text" inputmode="decimal" data-money-input="2" name="weekly_installment" value="{{ \App\Support\MoneyFormat::forInput(old('weekly_installment', $asset?->weekly_installment ?? 0), 2) }}" required class="w-full rounded-lg border-gray-300 text-sm"></div>
            <div><label class="block text-xs font-medium text-gray-600 mb-1">Max tenure (months)</label><input type="number" name="max_tenure_months" value="{{ old('max_tenure_months', $asset?->max_tenure_months ?? 12) }}" required class="w-full rounded-lg border-gray-300 text-sm"></div>
            <div><label class="block text-xs font-medium text-gray-600 mb-1">Waiting period (days)</label><input type="number" name="waiting_period_days" value="{{ old('waiting_period_days', $asset?->waiting_period_days ?? $defaultWaitingPeriodDays ?? 7) }}" class="w-full rounded-lg border-gray-300 text-sm"></div>
            @if ($asset)
                <div class="flex items-center gap-2 pt-6"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $asset->is_active))><span class="text-sm">Active on marketplace</span></div>
            @endif
        </div>
        @if ($asset && ! empty($asset->photos))
            <div>
                <p class="text-xs font-medium text-gray-600 mb-2">Current photos</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @foreach ($asset->photos as $photo)
                        <label class="relative block rounded-lg overflow-hidden ring-1 ring-gray-200">
                            <img src="{{ Storage::url($photo) }}" alt="" class="aspect-square object-cover w-full">
                            <span class="absolute inset-x-0 bottom-0 bg-black/60 text-white text-[10px] px-2 py-1 flex items-center gap-1">
                                <input type="checkbox" name="remove_photos[]" value="{{ $photo }}" class="rounded border-gray-300"> Remove
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endif
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Photos (up to 4)</label>
            <input type="file" name="photos[]" accept="image/*" multiple class="w-full text-sm text-gray-600">
        </div>
        @if ($asset)
            <p class="text-sm rounded-lg bg-amber-50 px-4 py-3 text-amber-900">
                Customer deposit: <strong>{{ format_money($asset->customer_deposit ?: $asset->computeCustomerDeposit()) }}</strong>
            </p>
        @endif
        <button class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">{{ $asset ? 'Save changes' : 'Upload asset' }}</button>
    </form>
</x-site.supplier-layout>
