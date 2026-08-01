<x-site.supplier-layout :title="$asset ? 'Edit asset' : 'Upload asset'" active="assets">
    @php
        $hasInsurance = filled($asset?->insurance_policy_number) || filled($asset?->insurance_expires_at);
        $insuranceAvailable = old('insurance_available', $hasInsurance ? '1' : '0');
        $maxPhotos = $maxAssetPhotos ?? 4;
        $markupPercent = (float) ($defaultDepositMarkupPercent ?? 10);
        $depositPercent = old('deposit_percent', $asset?->depositPercent() ?? 20);
        $assetValue = old('asset_value', $asset?->asset_value ?? 0);
    @endphp

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">{{ $asset ? 'Edit asset' : 'Upload asset' }}</h1>
        <p class="text-sm text-gray-500 mt-1">List stock on the marketplace. Photos and deposit preview help borrowers decide faster.</p>
    </div>

    <form method="POST" enctype="multipart/form-data" action="{{ $asset ? route('site.supplier.assets.update', $asset) : route('site.supplier.assets.store') }}"
          class="max-w-2xl glass-card rounded-2xl ring-1 ring-brand/15 p-5 sm:p-6 space-y-4"
          x-data="{ submitting: false }" @submit="submitting = true">
        @csrf
        @if ($asset) @method('PUT') @endif
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Category</label>
            <select name="category" required class="w-full rounded-xl border-gray-300 text-sm">
                @foreach ($categories as $key => $label)
                    <option value="{{ $key }}" @selected(old('category', $asset?->category) === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="block text-xs font-medium text-gray-600 mb-1">Title</label><input name="title" value="{{ old('title', $asset?->title) }}" required class="w-full rounded-xl border-gray-300 text-sm"></div>
        <div><label class="block text-xs font-medium text-gray-600 mb-1">Description</label><textarea name="description" rows="3" class="w-full rounded-xl border-gray-300 text-sm">{{ old('description', $asset?->description) }}</textarea></div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div><label class="block text-xs font-medium text-gray-600 mb-1">Serial / registration</label><input name="serial_number" value="{{ old('serial_number', $asset?->serial_number) }}" class="w-full rounded-xl border-gray-300 text-sm"></div>
            <div><label class="block text-xs font-medium text-gray-600 mb-1">Chassis number</label><input name="chassis_number" value="{{ old('chassis_number', $asset?->chassis_number) }}" class="w-full rounded-xl border-gray-300 text-sm"></div>
            <div><label class="block text-xs font-medium text-gray-600 mb-1">Engine number</label><input name="engine_number" value="{{ old('engine_number', $asset?->engine_number) }}" class="w-full rounded-xl border-gray-300 text-sm"></div>

            <div class="sm:col-span-2" x-data="{ insurance: @js($insuranceAvailable === '1') }">
                <p class="text-xs font-medium text-gray-600 mb-2">Insurance available</p>
                <div class="flex gap-4 mb-3">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="radio" name="insurance_available" value="1" @checked($insuranceAvailable === '1') x-model="insurance" class="text-brand">
                        Yes
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="radio" name="insurance_available" value="0" @checked($insuranceAvailable === '0') x-model="insurance" class="text-brand">
                        No
                    </label>
                </div>
                <div x-show="insurance" x-cloak class="grid sm:grid-cols-2 gap-4">
                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Insurance policy</label><input name="insurance_policy_number" value="{{ old('insurance_policy_number', $asset?->insurance_policy_number) }}" class="w-full rounded-xl border-gray-300 text-sm"></div>
                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Insurance expiry</label><input type="date" name="insurance_expires_at" value="{{ old('insurance_expires_at', optional($asset?->insurance_expires_at)->format('Y-m-d')) }}" class="w-full rounded-xl border-gray-300 text-sm"></div>
                </div>
            </div>

            <div x-data="marketplaceDepositPreview(@js($markupPercent))" x-init="bindMoneyInputs($el)" class="sm:col-span-2 grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Asset value</label>
                    <input type="text" inputmode="decimal" data-money-input="2" name="asset_value" data-preview-field="asset_value" @input="refresh($event)"
                           value="{{ \App\Support\MoneyFormat::forInput($assetValue, 2) }}" required class="w-full rounded-xl border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Deposit (% of asset value)</label>
                    <input type="number" step="0.01" min="0.01" max="100" name="deposit_percent" data-preview-field="deposit_percent" @input="refresh($event)"
                           value="{{ $depositPercent }}" required class="w-full rounded-xl border-gray-300 text-sm">
                </div>
                <p class="sm:col-span-2 text-xs text-gray-600 rounded-xl bg-brand-muted/40 ring-1 ring-brand/15 px-3 py-2">
                    Company markup: {{ rtrim(rtrim(number_format($markupPercent, 2), '0'), '.') }}%.
                    Customer deposit preview: <strong x-text="formattedCustomerDeposit()">{{ format_money(app(\App\Services\MarketplaceAssetService::class)->computeCustomerDepositPreview((float) \App\Support\MoneyFormat::toNumber($assetValue), (float) $depositPercent)) }}</strong>
                </p>
            </div>
            <div><label class="block text-xs font-medium text-gray-600 mb-1">Max tenure (months)</label><input type="number" name="max_tenure_months" value="{{ old('max_tenure_months', $asset?->max_tenure_months ?? 12) }}" required class="w-full rounded-xl border-gray-300 text-sm"></div>
            @if ($asset)
                <div class="flex items-center gap-2 pt-6"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $asset->is_active)) class="rounded text-brand"><span class="text-sm">Active on marketplace</span></div>
            @endif
        </div>

        <x-admin.multi-image-upload :existing="$asset?->photos ?? []" :max="$maxPhotos" :min="1" />

        <button type="submit" :disabled="submitting"
                class="inline-flex items-center justify-center gap-2 bg-brand-gold hover:brightness-95 disabled:opacity-70 disabled:cursor-wait text-brand font-bold px-5 py-2.5 rounded-xl text-sm">
            <svg x-show="submitting" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            <span x-text="submitting ? (@js($asset ? 'Saving…' : 'Uploading…')) : @js($asset ? 'Save changes' : 'Upload asset')"></span>
        </button>
    </form>
</x-site.supplier-layout>

@once
    <script>
        if (typeof marketplaceDepositPreview !== 'function') {
            function marketplaceDepositPreview(markupPercent) {
                return {
                    markupPercent,
                    assetValue: 0,
                    depositPercent: 0,
                    bindMoneyInputs(root) {
                        const assetInput = root.querySelector('[data-preview-field="asset_value"]');
                        const percentInput = root.querySelector('[data-preview-field="deposit_percent"]');
                        this.assetValue = this.parseNumber(assetInput?.value);
                        this.depositPercent = this.parseNumber(percentInput?.value);
                    },
                    refresh(event) {
                        const field = event?.target?.dataset?.previewField;
                        const value = this.parseNumber(event?.target?.value);
                        if (field === 'asset_value') this.assetValue = value;
                        if (field === 'deposit_percent') this.depositPercent = value;
                    },
                    parseNumber(value) {
                        if (value === null || value === undefined || value === '') return 0;
                        return parseFloat(String(value).replace(/,/g, '')) || 0;
                    },
                    customerDeposit() {
                        const supplier = this.assetValue * (this.depositPercent / 100);
                        return supplier + (supplier * (this.markupPercent / 100));
                    },
                    formattedCustomerDeposit() {
                        return new Intl.NumberFormat('en-TZ', { style: 'currency', currency: 'TZS', maximumFractionDigits: 0 }).format(this.customerDeposit() || 0);
                    },
                };
            }
        }
    </script>
@endonce
