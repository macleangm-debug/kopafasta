<x-site.supplier-layout :title="$asset ? __('site.supplier_portal.wizard_save') : __('site.supplier_portal.cta_upload')" active="assets" :hero="false">
    @php
        $maxPhotos = $maxAssetPhotos ?? 7;
        $quote = $quote ?? ['deposit_percent' => 0, 'deposit_amount' => 0, 'deposit_markup_percent' => 10, 'deposit_markup_amount' => 0, 'customer_deposit_due' => 0, 'financed_amount' => 0, 'pre_financing_total' => 0];
        $specs = is_array($asset?->specs) ? $asset->specs : [];
        $assetValue = old('asset_value', $asset?->asset_value ?? 0);
        $productMaxTenure = (int) ($productMaxTenureMonths ?? app(\App\Services\AssetLendingService::class)->productMaxTenureMonths());
        $maxTenure = (int) old('max_tenure_months', $asset?->max_tenure_months ?? $productMaxTenure);
        $vehicleLike = ['vehicle', 'motorcycle', 'truck'];
        $selectedCategory = old('category', $asset?->category);
        $cover = marketplace_photo_urls($asset?->photos ?? [])[0] ?? null;
    @endphp

    <div class="mx-auto w-full max-w-2xl sm:max-w-3xl px-1 sm:px-0">
        <form method="POST" enctype="multipart/form-data"
              action="{{ $asset ? route('site.supplier.assets.update', $asset) : route('site.supplier.assets.store') }}"
              class="glass-card rounded-3xl ring-1 ring-brand/10 p-4 sm:p-8"
              x-data="supplierAssetWizard(@js([
                  'category' => $selectedCategory,
                  'title' => old('title', $asset?->title ?? ''),
                  'description' => old('description', $asset?->description ?? ''),
                  'condition' => old('condition', $specs['condition'] ?? ''),
                  'city' => old('city', $specs['city'] ?? ''),
                  'make' => old('make', $specs['make'] ?? ''),
                  'model' => old('model', $specs['model'] ?? ''),
                  'year' => old('year', $specs['year'] ?? ''),
                  'serial' => old('serial_number', $asset?->serial_number ?? ''),
                  'assetValue' => \App\Support\MoneyFormat::toNumber($assetValue),
                  'maxTenure' => $maxTenure,
                  'productMax' => $productMaxTenure,
                  'isActive' => (bool) old('is_active', $asset?->is_active ?? true),
                  'cover' => $cover,
                  'supplier' => $vendor->name,
                  'vehicleLike' => $vehicleLike,
                  'tiers' => $depositTiers ?? [],
                  'markupPercent' => (float) ($markupPercent ?? 10),
                  'markupBase' => $markupBase ?? 'deposit',
              ]))"
              @submit="submitting = true">
            @csrf
            @if ($asset) @method('PUT') @endif
            <input type="hidden" name="_submit_token" value="{{ $submitToken }}">
            <input type="hidden" name="is_active" :value="isActive ? 1 : 0">

            <x-admin.wizard :submit-label="$asset ? __('site.supplier_portal.wizard_save') : __('site.supplier_portal.wizard_publish')" :cancel-url="route('site.supplier.assets')">
                <x-admin.step :title="__('site.supplier_portal.wizard_type')">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.supplier_portal.wizard_type') }}</label>
                        <select name="category" required x-model="category" class="w-full rounded-xl border-gray-300 text-sm">
                            @foreach ($categories as $key => $label)
                                <option value="{{ $key }}" @selected($selectedCategory === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </x-admin.step>

                <x-admin.step :title="__('site.supplier_portal.wizard_details')">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Title</label>
                        <input name="title" x-model="title" required class="w-full rounded-xl border-gray-300 text-sm">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Description</label>
                        <textarea name="description" x-model="description" rows="3" class="w-full rounded-xl border-gray-300 text-sm"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.supplier_portal.wizard_condition') }}</label>
                        <select name="condition" x-model="condition" class="w-full rounded-xl border-gray-300 text-sm">
                            <option value="">—</option>
                            <option value="new">New</option>
                            <option value="used">Used</option>
                            <option value="refurbished">Refurbished</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.supplier_portal.wizard_city') }}</label>
                        <input name="city" x-model="city" class="w-full rounded-xl border-gray-300 text-sm">
                    </div>
                    <div x-show="isVehicle" x-cloak>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.supplier_portal.wizard_make') }}</label>
                        <input name="make" x-model="make" class="w-full rounded-xl border-gray-300 text-sm">
                    </div>
                    <div x-show="isVehicle" x-cloak>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.supplier_portal.wizard_model') }}</label>
                        <input name="model" x-model="model" class="w-full rounded-xl border-gray-300 text-sm">
                    </div>
                    <div x-show="isVehicle" x-cloak>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.supplier_portal.wizard_year') }}</label>
                        <input type="number" name="year" x-model="year" min="1950" max="2100" class="w-full rounded-xl border-gray-300 text-sm">
                    </div>
                    <div x-show="isVehicle" x-cloak>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Serial / registration</label>
                        <input name="serial_number" x-model="serial" class="w-full rounded-xl border-gray-300 text-sm">
                    </div>
                    <div x-show="isVehicle" x-cloak>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Chassis number</label>
                        <input name="chassis_number" value="{{ old('chassis_number', $asset?->chassis_number) }}" class="w-full rounded-xl border-gray-300 text-sm">
                    </div>
                    <div x-show="isVehicle" x-cloak>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Engine number</label>
                        <input name="engine_number" value="{{ old('engine_number', $asset?->engine_number) }}" class="w-full rounded-xl border-gray-300 text-sm">
                    </div>
                </x-admin.step>

                <x-admin.step :title="__('site.supplier_portal.wizard_price')">
                    <div class="md:col-span-2 space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-start">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.supplier_portal.wizard_amount') }}</label>
                                <input type="text" inputmode="decimal" data-money-input="2" name="asset_value"
                                       x-model="assetValueInput" @input="refreshPrice($event)"
                                       value="{{ \App\Support\MoneyFormat::forInput($assetValue, 2) }}"
                                       required class="w-full rounded-xl border-gray-300 text-sm tabular-nums">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.supplier_portal.wizard_deposit_rate') }}</label>
                                <div class="flex items-center gap-2">
                                    <input type="text" readonly tabindex="-1" :value="(quote.percent || 0) + '%'"
                                           class="w-16 rounded-xl border-gray-300 bg-gray-50 text-sm text-center tabular-nums">
                                </div>
                                <p class="text-xs text-gray-500 mt-1.5">{{ __('site.supplier_portal.wizard_deposit_rate_helper') }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.supplier_portal.wizard_max_tenure') }}</label>
                                <div class="flex items-center gap-2">
                                    <input type="text" inputmode="numeric" pattern="[0-9]*" name="max_tenure_months"
                                           x-model="maxTenure"
                                           class="w-16 rounded-xl border-gray-300 text-sm text-center tabular-nums [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                    <span class="text-sm text-gray-600">{{ __('site.supplier_portal.wizard_max_tenure_months') }}</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-1.5">{{ __('site.supplier_portal.wizard_max_tenure_helper', ['months' => $productMaxTenure]) }}</p>
                                @error('max_tenure_months')
                                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        <p class="text-xs text-gray-500">{{ __('site.supplier_portal.wizard_tenure_later') }}</p>
                        <div class="rounded-2xl bg-brand-muted/40 ring-1 ring-brand/15 px-4 py-3 text-sm space-y-1.5">
                            <div class="flex justify-between gap-3"><span class="text-gray-600">{{ __('site.supplier_portal.wizard_base_deposit') }}</span><strong class="tabular-nums" x-text="money(quote.base)"></strong></div>
                            <div class="flex justify-between gap-3"><span class="text-gray-600">{{ __('site.supplier_portal.wizard_deposit_markup') }}</span><strong class="tabular-nums" x-text="money(quote.markup)"></strong></div>
                            <div class="flex justify-between gap-3"><span class="text-gray-600">{{ __('site.supplier_portal.wizard_deposit_due') }}</span><strong class="tabular-nums text-brand" x-text="money(quote.due)"></strong></div>
                            <div class="flex justify-between gap-3"><span class="text-gray-600">{{ __('site.supplier_portal.wizard_financed') }}</span><strong class="tabular-nums" x-text="money(quote.financed)"></strong></div>
                            <div class="flex justify-between gap-3 pt-1 border-t border-brand/10"><span class="text-gray-600">{{ __('site.supplier_portal.wizard_pre_financing') }}</span><strong class="tabular-nums" x-text="money(quote.pre)"></strong></div>
                        </div>
                        @if ($asset)
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="checkbox" x-model="isActive" class="rounded text-brand">
                                <span>Active on marketplace</span>
                            </label>
                        @endif
                    </div>
                </x-admin.step>

                <x-admin.step :title="__('site.supplier_portal.wizard_photos')">
                    <div class="md:col-span-2">
                        <x-admin.multi-image-upload :existing="$asset?->photos ?? []" :max="$maxPhotos" :min="1" />
                    </div>
                </x-admin.step>

                <x-admin.step :title="__('site.supplier_portal.wizard_review')">
                    <div class="md:col-span-2 space-y-2" x-data="{ open: 'preview' }">
                        @foreach ([
                            'asset' => __('site.supplier_portal.wizard_type'),
                            'details' => __('site.supplier_portal.wizard_details'),
                            'price' => __('site.supplier_portal.wizard_price'),
                            'photos' => __('site.supplier_portal.wizard_photos'),
                            'preview' => __('site.supplier_portal.wizard_preview'),
                        ] as $key => $label)
                            <div class="rounded-2xl ring-1 ring-gray-200 overflow-hidden bg-white">
                                <button type="button" class="w-full flex items-center justify-between px-4 py-3 text-sm font-semibold"
                                        @click="open = open === '{{ $key }}' ? '' : '{{ $key }}'">
                                    <span>{{ $label }}</span>
                                    <span class="text-xs text-brand">{{ __('site.supplier_portal.wizard_edit_section') }}</span>
                                </button>
                                <div x-show="open === '{{ $key }}'" x-cloak class="px-4 pb-4 text-sm text-gray-700 space-y-1">
                                    @if ($key === 'asset')
                                        <p x-text="category || '—'"></p>
                                    @elseif ($key === 'details')
                                        <p><span class="text-gray-500">Title:</span> <span x-text="title || '—'"></span></p>
                                        <p><span class="text-gray-500">{{ __('site.supplier_portal.wizard_city') }}:</span> <span x-text="city || '—'"></span></p>
                                        <p x-show="isVehicle"><span class="text-gray-500">{{ __('site.supplier_portal.wizard_make') }}:</span> <span x-text="[make, model, year].filter(Boolean).join(' ') || '—'"></span></p>
                                    @elseif ($key === 'price')
                                        <p>{{ __('site.supplier_portal.wizard_selling_price') }}: <strong class="tabular-nums" x-text="money(assetValue)"></strong></p>
                                        <p>{{ __('site.supplier_portal.wizard_deposit_due') }}: <strong class="tabular-nums" x-text="money(quote.due)"></strong></p>
                                        <p>{{ __('site.supplier_portal.wizard_financed') }}: <strong class="tabular-nums" x-text="money(quote.financed)"></strong></p>
                                        <p>{{ __('site.supplier_portal.wizard_max_tenure') }}: <strong class="tabular-nums" x-text="maxTenure + ' {{ __('site.supplier_portal.wizard_max_tenure_months') }}'"></strong></p>
                                    @elseif ($key === 'photos')
                                        <p class="text-xs text-gray-500">{{ $maxPhotos }} photos maximum · 1 cover + 6 additional</p>
                                    @else
                                        <article class="rounded-2xl ring-1 ring-brand/10 overflow-hidden">
                                            <div class="aspect-[4/3] bg-slate-100">
                                                <template x-if="cover">
                                                    <img :src="cover" alt="" class="w-full h-full object-cover">
                                                </template>
                                            </div>
                                            <div class="p-3 space-y-1">
                                                <p class="text-[10px] uppercase tracking-widest text-brand font-bold" x-text="category"></p>
                                                <p class="font-bold text-gray-900" x-text="title || '—'"></p>
                                                <p class="text-xs text-gray-500"><span x-text="supplier"></span><span x-show="city"> · <span x-text="city"></span></span></p>
                                                <div class="grid grid-cols-3 gap-2 pt-2 text-[11px]">
                                                    <div><p class="text-gray-500">{{ __('borrower.marketplace.asset_value') }}</p><p class="font-bold tabular-nums" x-text="money(assetValue)"></p></div>
                                                    <div><p class="text-gray-500">{{ __('borrower.marketplace.deposit') }}</p><p class="font-bold tabular-nums text-brand" x-text="money(quote.due)"></p></div>
                                                    <div><p class="text-gray-500">{{ __('borrower.marketplace.loan_amount') }}</p><p class="font-bold tabular-nums" x-text="money(quote.financed)"></p></div>
                                                </div>
                                                <p class="text-[11px] text-gray-500 pt-1" x-text="'{{ __('borrower.marketplace.up_to_months', ['months' => ':months']) }}'.replace(':months', maxTenure)"></p>
                                            </div>
                                        </article>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-admin.step>
            </x-admin.wizard>
        </form>
    </div>
</x-site.supplier-layout>

@once
    <script>
        function supplierAssetWizard(seed) {
            return {
                submitting: false,
                category: seed.category || '',
                title: seed.title || '',
                description: seed.description || '',
                condition: seed.condition || '',
                city: seed.city || '',
                make: seed.make || '',
                model: seed.model || '',
                year: seed.year || '',
                serial: seed.serial || '',
                assetValue: Number(seed.assetValue || 0),
                assetValueInput: '',
                maxTenure: Number(seed.maxTenure || seed.productMax || 6),
                productMax: Number(seed.productMax || 6),
                isActive: !!seed.isActive,
                cover: seed.cover || '',
                supplier: seed.supplier || '',
                vehicleLike: seed.vehicleLike || [],
                tiers: seed.tiers || [],
                markupPercent: Number(seed.markupPercent || 10),
                markupBase: seed.markupBase || 'deposit',
                get isVehicle() { return this.vehicleLike.includes(this.category); },
                get quote() {
                    const price = this.assetValue;
                    const tier = this.matchTier(price);
                    const percent = Number(tier?.percent || 0);
                    const base = Math.round(price * (percent / 100));
                    const markup = this.markupBase === 'asset_price'
                        ? Math.round(price * (this.markupPercent / 100))
                        : Math.round(base * (this.markupPercent / 100));
                    return {
                        percent,
                        base,
                        markup,
                        due: base + markup,
                        financed: Math.max(0, price - base),
                        pre: price + markup,
                    };
                },
                matchTier(amount) {
                    for (const tier of this.tiers) {
                        if (tier.active === false) continue;
                        const from = Number(tier.from || 0);
                        const to = tier.to;
                        if (amount + 0.00001 < from) continue;
                        if (to === null || to === '' || amount <= Number(to) + 0.00001) return tier;
                    }
                    return this.tiers[this.tiers.length - 1] || null;
                },
                refreshPrice(event) {
                    const raw = String(event?.target?.value || '').replace(/,/g, '');
                    this.assetValue = parseFloat(raw) || 0;
                },
                money(value) {
                    return new Intl.NumberFormat('en-TZ', { style: 'currency', currency: 'TZS', maximumFractionDigits: 0 }).format(value || 0);
                },
            };
        }
    </script>
@endonce
