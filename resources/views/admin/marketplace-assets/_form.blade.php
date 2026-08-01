@php
    $r = $record ?? null;
    $prefill = $prefill ?? [];
    $hasInsurance = filled($r?->insurance_policy_number) || filled($r?->insurance_expires_at);
    $insuranceAvailable = old('insurance_available', $hasInsurance ? '1' : '0');
    $maxPhotos = $maxAssetPhotos ?? 4;
    $markupPercent = (float) ($defaultDepositMarkupPercent ?? 10);
    $depositPercent = old('deposit_percent', $r?->depositPercent() ?? 20);
    $assetValue = old('asset_value', $r?->asset_value ?? ($prefill['asset_value'] ?? 0));
@endphp
<x-admin.step title="Asset details">
    <div class="md:col-span-2 flex flex-col sm:flex-row sm:items-end gap-3">
        <div class="flex-1">
            <x-admin.select name="vendor_id" label="Supplier" :options="$suppliers" :value="$r?->vendor_id ?? ($prefill['vendor_id'] ?? null)" placeholder="— select supplier —" required />
        </div>
        <a href="{{ route('admin.partners.create', ['category' => 'supplier']) }}"
           class="inline-flex items-center justify-center rounded-lg bg-white ring-1 ring-amber-300 text-amber-800 font-semibold px-4 py-2 text-sm hover:bg-amber-50 whitespace-nowrap">
            + Add new supplier
        </a>
    </div>
    <x-admin.select name="category" label="Category" :options="$categories" :value="$r?->category" required />
    <x-admin.input name="title" label="Title" :value="$r?->title ?? ($prefill['title'] ?? null)" required />
    <div class="md:col-span-2"><x-admin.textarea name="description" label="Description" :value="$r?->description" rows="3" /></div>
    <x-admin.input name="serial_number" label="Serial / registration" :value="$r?->serial_number" />
    <x-admin.input name="chassis_number" label="Chassis number" :value="$r?->chassis_number" />
    <x-admin.input name="engine_number" label="Engine number" :value="$r?->engine_number" />

    <div class="md:col-span-2" x-data="{ insurance: @js($insuranceAvailable === '1') }">
        <p class="text-xs font-semibold text-gray-700 mb-2">Insurance available <span class="text-red-500">*</span></p>
        <div class="flex gap-4 mb-3">
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="radio" name="insurance_available" value="1" @checked($insuranceAvailable === '1') x-model="insurance" class="text-brand focus:ring-brand">
                Yes
            </label>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="radio" name="insurance_available" value="0" @checked($insuranceAvailable === '0') x-model="insurance" class="text-brand focus:ring-brand">
                No
            </label>
        </div>
        <div x-show="insurance" x-cloak class="grid md:grid-cols-2 gap-4">
            <x-admin.input name="insurance_policy_number" label="Insurance policy number" :value="$r?->insurance_policy_number" />
            <x-admin.input name="insurance_expires_at" label="Insurance expiry date" type="date"
                           :value="old('insurance_expires_at', optional($r?->insurance_expires_at)->format('Y-m-d'))" />
        </div>
    </div>
</x-admin.step>
<x-admin.step title="Pricing"
              x-data="marketplaceDepositPreview(@js((float) $markupPercent))"
              x-init="bindMoneyInputs($el)"
              data-markup-percent="{{ $markupPercent }}">
    <x-admin.input name="asset_value" label="Asset value" money :decimals="2" :value="$assetValue" required
                   data-preview-field="asset_value" @input="refresh($event)" />
    <x-admin.input name="deposit_percent" label="Deposit (% of asset value)" type="number" step="0.01" min="0.01" max="100"
                   :value="$depositPercent" required data-preview-field="deposit_percent" @input="refresh($event)" />
    <p class="md:col-span-2 text-xs text-gray-500">
        Platform markup from Settings → Asset lending
        (<strong>{{ rtrim(rtrim(number_format($markupPercent, 2), '0'), '.') }}%</strong>)
        is added on top of the supplier deposit. Weekly installment is calculated during loan processing.
    </p>
    <x-admin.input name="max_tenure_months" label="Max tenure (months)" type="number" :value="$r?->max_tenure_months ?? ($prefill['max_tenure_months'] ?? 12)" required />
    <x-admin.select name="is_active" label="Status" :options="['1' => 'Active', '0' => 'Inactive']" :value="($r?->is_active ?? true) ? '1' : '0'" />
    <div class="md:col-span-2 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">
        Customer deposit preview:
        <strong x-text="formattedCustomerDeposit()">{{ format_money(app(\App\Services\MarketplaceAssetService::class)->computeCustomerDepositPreview((float) \App\Support\MoneyFormat::toNumber($assetValue), (float) $depositPercent)) }}</strong>
        @if ($r)
            · Company markup: <strong>{{ format_money(app(\App\Services\AssetLendingService::class)->depositMarkupAmount($r)) }}</strong>
            · Suggested weekly instalment:
            <strong>{{ format_money(app(\App\Services\MarketplaceAssetService::class)->suggestWeeklyInstallment($r)) }}</strong>
        @endif
    </div>
</x-admin.step>
<x-admin.step title="Photos">
    <x-admin.multi-image-upload :existing="$r?->photos ?? []" :max="$maxPhotos" :min="1" />
</x-admin.step>

@once
    <script>
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
    </script>
@endonce
