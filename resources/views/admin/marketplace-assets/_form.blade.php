@php
    $r = $record ?? null;
    $prefill = $prefill ?? [];
    $hasInsurance = filled($r?->insurance_policy_number) || filled($r?->insurance_expires_at);
    $insuranceAvailable = old('insurance_available', $hasInsurance ? '1' : '0');
    $maxPhotos = $maxAssetPhotos ?? 4;
@endphp
<x-admin.step title="Asset details">
    <div class="md:col-span-2 flex flex-col sm:flex-row sm:items-end gap-3">
        <div class="flex-1">
            <x-admin.select name="vendor_id" label="Supplier" :options="$suppliers" :value="$r?->vendor_id" placeholder="— select supplier —" required />
        </div>
        <a href="{{ route('admin.partners.create', ['category' => 'supplier']) }}"
           class="inline-flex items-center justify-center rounded-lg bg-white ring-1 ring-amber-300 text-amber-800 font-semibold px-4 py-2 text-sm hover:bg-amber-50 whitespace-nowrap">
            + Add new supplier
        </a>
    </div>
    <x-admin.select name="category" label="Category" :options="$categories" :value="$r?->category" required />
    <x-admin.input name="title" label="Title" :value="$r?->title ?? ($prefill['title'] ?? null)" required />
    <x-admin.input name="slug" label="Slug" :value="$r?->slug" placeholder="Auto-generated if blank"
                   help="Auto-generated if left blank." />
    <div class="md:col-span-2"><x-admin.textarea name="description" label="Description" :value="$r?->description" rows="3" /></div>
    <x-admin.input name="supplier_name" label="Supplier display name" :value="$r?->supplier_name" />
    <x-admin.input name="serial_number" label="Serial / registration" :value="$r?->serial_number" />
    <x-admin.input name="chassis_number" label="Chassis number" :value="$r?->chassis_number" />
    <x-admin.input name="engine_number" label="Engine number" :value="$r?->engine_number" />

    <div class="md:col-span-2" x-data="{ insurance: @js($insuranceAvailable === '1') }">
        <p class="text-xs font-semibold text-gray-700 mb-2">Insurance available <span class="text-red-500">*</span></p>
        <div class="flex gap-4 mb-3">
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="radio" name="insurance_available" value="1" @checked($insuranceAvailable === '1') x-model="insurance" class="text-amber-600 focus:ring-amber-500">
                Yes
            </label>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="radio" name="insurance_available" value="0" @checked($insuranceAvailable === '0') x-model="insurance" class="text-amber-600 focus:ring-amber-500">
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
<x-admin.step title="Pricing">
    <x-admin.input name="asset_value" label="Asset value" money :decimals="2" :value="$r?->asset_value ?? ($prefill['asset_value'] ?? 0)" required />
    <x-admin.input name="supplier_deposit" label="Supplier deposit" money :decimals="2" :value="$r?->supplier_deposit ?? 0" required />
    <p class="md:col-span-2 text-xs text-gray-500">
        Deposit markup uses the platform default from Settings → Asset lending
        (<strong>{{ rtrim(rtrim(number_format($defaultDepositMarkupPercent ?? 10, 2), '0'), '.') }}%</strong>).
        Customer deposit is calculated automatically. Weekly installment is calculated during loan processing.
    </p>
    <x-admin.input name="max_tenure_months" label="Max tenure (months)" type="number" :value="$r?->max_tenure_months ?? ($prefill['max_tenure_months'] ?? 12)" required />
    <x-admin.select name="is_active" label="Status" :options="['1' => 'Active', '0' => 'Inactive']" :value="($r?->is_active ?? true) ? '1' : '0'" />
    <div class="md:col-span-2 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">
        @php
            $previewAsset = $r ?? new \App\Models\MarketplaceAsset([
                'asset_value' => 0,
                'supplier_deposit' => 0,
                'deposit_markup_percent' => $defaultDepositMarkupPercent ?? 10,
            ]);
        @endphp
        Customer deposit preview:
        <strong>{{ format_money($r ? ($r->customer_deposit ?: $r->computeCustomerDeposit()) : $previewAsset->computeCustomerDeposit()) }}</strong>
        @if ($r)
            · Company markup: <strong>{{ format_money(app(\App\Services\AssetLendingService::class)->depositMarkupAmount($r)) }}</strong>
            · Suggested weekly instalment (at loan processing):
            <strong>{{ format_money(app(\App\Services\MarketplaceAssetService::class)->suggestWeeklyInstallment($r)) }}</strong>
        @endif
    </div>
</x-admin.step>
<x-admin.step title="Photos">
    <x-admin.multi-image-upload :existing="$r?->photos ?? []" :max="$maxPhotos" :min="1" />
</x-admin.step>
