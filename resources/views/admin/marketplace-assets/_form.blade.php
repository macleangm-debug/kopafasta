@php($r = $record ?? null)
@php($prefill = $prefill ?? [])
<x-admin.step title="Asset details">
    <x-admin.select name="vendor_id" label="Supplier" :options="$suppliers" :value="$r?->vendor_id" placeholder="— optional —" />
    <x-admin.select name="category" label="Category" :options="$categories" :value="$r?->category" required />
    <x-admin.input name="title" label="Title" :value="$r?->title ?? ($prefill['title'] ?? null)" required />
    <x-admin.input name="slug" label="Slug" :value="$r?->slug" placeholder="Auto-generated if blank" />
    <div class="md:col-span-2"><x-admin.textarea name="description" label="Description" :value="$r?->description" rows="3" /></div>
    <x-admin.input name="supplier_name" label="Supplier display name" :value="$r?->supplier_name" />
    <x-admin.input name="serial_number" label="Serial / registration" :value="$r?->serial_number" />
    <x-admin.input name="chassis_number" label="Chassis number" :value="$r?->chassis_number" />
    <x-admin.input name="engine_number" label="Engine number" :value="$r?->engine_number" />
    <x-admin.input name="insurance_policy_number" label="Insurance policy number" :value="$r?->insurance_policy_number" />
    <x-admin.input name="insurance_expires_at" label="Insurance expiry date" type="date"
                   :value="old('insurance_expires_at', optional($r?->insurance_expires_at)->format('Y-m-d'))" />
</x-admin.step>
<x-admin.step title="Pricing">
    <x-admin.input name="asset_value" label="Asset value" money :decimals="2" :value="$r?->asset_value ?? ($prefill['asset_value'] ?? 0)" required />
    <x-admin.input name="supplier_deposit" label="Supplier deposit" money :decimals="2" :value="$r?->supplier_deposit ?? 0" required />
    <x-admin.input name="deposit_markup_percent" label="Deposit markup (%)" type="number" step="0.01"
                   :value="$r?->deposit_markup_percent ?? ($defaultDepositMarkupPercent ?? 10)" />
    <x-admin.input name="weekly_installment" label="Weekly installment" money :decimals="2" :value="$r?->weekly_installment ?? 0" required />
    <x-admin.input name="max_tenure_months" label="Max tenure (months)" type="number" :value="$r?->max_tenure_months ?? ($prefill['max_tenure_months'] ?? 12)" required />
    <x-admin.input name="waiting_period_days" label="Waiting period (days after deposit)" type="number"
                   :value="$r?->waiting_period_days ?? ($defaultWaitingPeriodDays ?? 7)" />
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
            · Suggested weekly instalment:
            <strong>{{ format_money(app(\App\Services\MarketplaceAssetService::class)->suggestWeeklyInstallment($r)) }}</strong>
        @endif
    </div>
</x-admin.step>
<x-admin.step title="Photos">
    <div class="md:col-span-2">
        <p class="text-xs text-gray-500 mb-3">Upload up to 4 images (first image is the marketplace cover).</p>
        @if ($r && ! empty($r->photos))
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                @foreach ($r->photos as $photo)
                    <label class="relative block rounded-lg overflow-hidden ring-1 ring-gray-200">
                        <img src="{{ Storage::url($photo) }}" alt="" class="aspect-square object-cover w-full">
                        <span class="absolute inset-x-0 bottom-0 bg-black/60 text-white text-[10px] px-2 py-1 flex items-center gap-1">
                            <input type="checkbox" name="remove_photos[]" value="{{ $photo }}" class="rounded border-gray-300">
                            Remove
                        </span>
                    </label>
                @endforeach
            </div>
        @endif
        <input type="file" name="photos[]" accept="image/*" multiple class="block w-full text-sm text-gray-600">
    </div>
</x-admin.step>
