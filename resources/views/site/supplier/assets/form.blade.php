<x-site.supplier-layout :title="$asset ? 'Edit asset' : 'Upload asset'" active="assets">
    @php
        $hasInsurance = filled($asset?->insurance_policy_number) || filled($asset?->insurance_expires_at);
        $insuranceAvailable = old('insurance_available', $hasInsurance ? '1' : '0');
        $maxPhotos = $maxAssetPhotos ?? 4;
    @endphp
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

            <div class="sm:col-span-2" x-data="{ insurance: @js($insuranceAvailable === '1') }">
                <p class="text-xs font-medium text-gray-600 mb-2">Insurance available</p>
                <div class="flex gap-4 mb-3">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="radio" name="insurance_available" value="1" @checked($insuranceAvailable === '1') x-model="insurance" class="text-amber-600">
                        Yes
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="radio" name="insurance_available" value="0" @checked($insuranceAvailable === '0') x-model="insurance" class="text-amber-600">
                        No
                    </label>
                </div>
                <div x-show="insurance" x-cloak class="grid sm:grid-cols-2 gap-4">
                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Insurance policy</label><input name="insurance_policy_number" value="{{ old('insurance_policy_number', $asset?->insurance_policy_number) }}" class="w-full rounded-lg border-gray-300 text-sm"></div>
                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Insurance expiry</label><input type="date" name="insurance_expires_at" value="{{ old('insurance_expires_at', optional($asset?->insurance_expires_at)->format('Y-m-d')) }}" class="w-full rounded-lg border-gray-300 text-sm"></div>
                </div>
            </div>

            <div><label class="block text-xs font-medium text-gray-600 mb-1">Asset value</label><input type="text" inputmode="decimal" data-money-input="2" name="asset_value" value="{{ \App\Support\MoneyFormat::forInput(old('asset_value', $asset?->asset_value ?? 0), 2) }}" required class="w-full rounded-lg border-gray-300 text-sm"></div>
            <div><label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.marketplace.deposit') }}</label><input type="text" inputmode="decimal" data-money-input="2" name="supplier_deposit" value="{{ \App\Support\MoneyFormat::forInput(old('supplier_deposit', $asset?->supplier_deposit ?? 0), 2) }}" required class="w-full rounded-lg border-gray-300 text-sm"></div>
            <p class="sm:col-span-2 text-xs text-gray-500 rounded-lg bg-slate-50 ring-1 ring-slate-200 px-3 py-2">
                Company markup is set under Settings → Asset lending ({{ rtrim(rtrim(number_format($defaultDepositMarkupPercent ?? 10, 2), '0'), '.') }}%).
                Customer deposit and weekly installment are calculated automatically when you save.
            </p>
            <div><label class="block text-xs font-medium text-gray-600 mb-1">Max tenure (months)</label><input type="number" name="max_tenure_months" value="{{ old('max_tenure_months', $asset?->max_tenure_months ?? 12) }}" required class="w-full rounded-lg border-gray-300 text-sm"></div>
            @if ($asset)
                <div class="flex items-center gap-2 pt-6"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $asset->is_active))><span class="text-sm">Active on marketplace</span></div>
            @endif
        </div>

        <x-admin.multi-image-upload :existing="$asset?->photos ?? []" :max="$maxPhotos" :min="1" />

        @if ($asset)
            <p class="text-sm rounded-lg bg-amber-50 px-4 py-3 text-amber-900">
                Customer deposit: <strong>{{ format_money($asset->customer_deposit ?: $asset->computeCustomerDeposit()) }}</strong>
            </p>
        @endif
        <button class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">{{ $asset ? 'Save changes' : 'Upload asset' }}</button>
    </form>
</x-site.supplier-layout>
