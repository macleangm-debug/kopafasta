{{-- Shared partner form. Expects $record, $statuses, $categories, $regionOptions --}}
@php
    $r = $record ?? null;
    $category = old('category', $r?->category ?? ($defaultCategory ?? 'supplier'));
    $selectedRegions = old('regions', $r?->regions ?? []);
    $regionRequiredCategories = ['valuer', 'gps_installer', 'insurance', 'debt_collector', 'towing', 'auctioneer', 'legal_partner', 'supplier'];
@endphp

<div x-data="{ category: @js($category) }">
    <x-admin.step title="Partner type">
        <p class="md:col-span-2 text-sm text-gray-600">
            Selected type: <span class="font-semibold text-gray-900 capitalize" x-text="category.replace(/_/g, ' ')"></span>
            @if (! $r)
                · <a href="{{ route('admin.partners.create') }}" class="text-amber-700 hover:underline">Change type</a>
            @endif
        </p>
    </x-admin.step>

    <x-admin.step title="Basic info">
        @if ($r)
            <div>
                <p class="text-xs font-semibold text-gray-700 mb-1">Partner code</p>
                <p class="text-sm font-mono text-gray-900">{{ $r->vendor_number }}</p>
            </div>
        @endif
        <x-admin.input  name="name"          label="Trading / display name" :value="$r?->name" required />
        <x-admin.input  name="legal_name"    label="Legal business name" :value="$r?->legal_name" />
        <x-admin.input  name="registration_number" label="BRELA / registration no." :value="$r?->registration_number" />
        <x-admin.input  name="tin"           label="TIN" :value="$r?->tin" />
        <x-admin.select name="category"      label="Category"      :options="$categories"      :value="$category" required x-model="category"
                        @if (! $r && ($defaultCategory ?? null)) disabled @endif />
        @if (! $r && ($defaultCategory ?? null))
            <input type="hidden" name="category" value="{{ $category }}">
        @endif
        <x-admin.select name="status"        label="Status"        :options="$statuses"        :value="$r?->status ?? 'active'" required />
    </x-admin.step>

    <div x-show="['valuer','gps_installer','insurance','debt_collector','towing','auctioneer','legal_partner','supplier'].includes(category)" x-cloak>
        <x-admin.step title="Coverage regions">
            <div class="md:col-span-2 space-y-3">
                <p class="text-xs text-gray-500">Select regions, or mark the partner as nationwide for all regions.</p>
                <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input type="radio" name="coverage_type" value="regions" @checked(old('coverage_type', $r?->coverage_type ?? 'regions') !== 'nationwide') class="text-amber-600 focus:ring-amber-500">
                    Specific regions
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input type="radio" name="coverage_type" value="nationwide" @checked(old('coverage_type', $r?->coverage_type ?? 'regions') === 'nationwide') class="text-amber-600 focus:ring-amber-500">
                    Nationwide
                </label>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2 max-h-64 overflow-y-auto rounded-lg border border-gray-200 p-3">
                    @foreach ($regionOptions ?? [] as $region)
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="regions[]" value="{{ $region }}"
                                   @checked(in_array($region, $selectedRegions, true))
                                   class="rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                            <span>{{ $region }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </x-admin.step>
    </div>

    <x-admin.step title="Contact">
        <x-admin.phone-input name="phone" label="Phone" :value="$r?->phone" />
        <x-admin.input  name="email"         label="Email"         :value="$r?->email"         type="email" />
        <div class="md:col-span-2" x-show="category !== 'valuer'" x-cloak>
            <x-admin.textarea name="address" label="Address / coverage area" :value="$r?->address" rows="2"
                              placeholder="Office address or service coverage notes" />
        </div>
    </x-admin.step>

    <div x-show="category === 'valuer'" x-cloak>
        <x-admin.step title="Valuer rates">
            <x-admin.input name="partner_cost" label="Base cost (TZS)" type="number" step="0.01" :value="$r?->partner_cost" />
            <x-admin.input name="markup_percent" label="Company markup (%)" type="number" step="0.01" :value="$r?->markup_percent" />
        </x-admin.step>
    </div>

    <div x-show="category === 'supplier'" x-cloak>
        <x-admin.step title="Supplier settings">
            <p class="md:col-span-2 text-xs text-gray-500 mb-2">Deposit markup is controlled under Settings → Asset lending (not per supplier).</p>
            <x-admin.select name="supplier_type" label="Supplier payment mode" :options="config('asset_lending.supplier_types')" :value="$r?->supplier_type ?? config('asset_lending.default_supplier_type')" />
            <p class="md:col-span-2 text-xs text-gray-500">
                <strong>Direct repayment</strong> — supplier receives principal from customer repayments.
                <strong>Full upfront payment</strong> — entire asset value is paid to supplier on loan approval; future repayments are managed under capital financing.
            </p>
        </x-admin.step>
    </div>

    <div x-show="category === 'affiliate'" x-cloak>
        <x-admin.step title="Affiliate program">
            <x-admin.input name="affiliate_code" label="Promo / affiliate code" :value="$r?->affiliate_code" placeholder="Auto-generated for affiliates" />
            <x-admin.input name="registration_discount_percent" label="Registration discount (%)" type="number" step="0.01" :value="$r?->registration_discount_percent ?? config('affiliates.default_registration_discount_percent')" />
            <x-admin.input name="application_discount_percent" label="Application discount (%)" type="number" step="0.01" :value="$r?->application_discount_percent ?? config('affiliates.default_application_discount_percent')" />
            <x-admin.input name="affiliate_commission_percent" label="Commission (%)" type="number" step="0.01" :value="$r?->affiliate_commission_percent ?? config('affiliates.default_commission_percent')" />
        </x-admin.step>
    </div>

    <div x-show="['debt_collector','towing','auctioneer','legal_partner','gps_installer','insurance'].includes(category)" x-cloak>
        <x-admin.step title="Service rates">
            <x-admin.input name="partner_cost" label="Default partner cost (optional)" type="number" step="0.01" :value="$r?->partner_cost" />
            <x-admin.input name="markup_percent" label="Markup (%)" type="number" step="0.01" :value="$r?->markup_percent" />
            <x-admin.input name="recovery_commission_percent" label="Recovery commission (%)" type="number" step="0.01" :value="$r?->recovery_commission_percent" />
            <x-admin.input name="recovery_markup_percent" label="Company markup (%)" type="number" step="0.01" :value="$r?->recovery_markup_percent" />
        </x-admin.step>
    </div>
</div>
