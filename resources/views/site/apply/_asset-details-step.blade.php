{{-- Asset-backed collateral step --}}
<div x-show="stepKey === 'asset_details'" class="p-6 sm:p-8">
    <x-site.wizard-step-header
        :eyebrow="__('borrower.apply.steps.asset_details')"
        :title="__('borrower.apply.asset_details.title')"
        :subtitle="__('borrower.apply.asset_details.subtitle')"
    />

    <template x-if="current">
        <div class="space-y-6">
            <div x-show="!customerAssets.length" class="rounded-2xl bg-brand-muted/50 ring-1 ring-brand/15 p-5 sm:p-6">
                <p class="text-sm font-semibold text-brand">{{ __('borrower.apply.asset_details.no_assets_title') }}</p>
                <p class="text-sm text-brand/80 mt-2">{{ __('borrower.apply.asset_details.no_assets_body') }}</p>
                <a href="{{ route('site.borrower.profile', ['section' => 'assets']) }}"
                   class="inline-flex mt-4 items-center gap-2 text-sm font-semibold text-brand hover:underline">
                    {{ __('borrower.apply.asset_details.add_asset_link') }} →
                </a>
            </div>

            <div x-show="customerAssets.length" class="space-y-4">
                <div class="glass-card p-5 ring-1 ring-brand/15">
                    <label class="block text-sm font-semibold text-gray-900 mb-1">
                        {{ __('borrower.apply.asset_details.choose_existing') }} <span class="text-rose-500">*</span>
                    </label>
                    <p class="text-xs text-gray-500 mb-3">{{ __('borrower.apply.asset_details.multi_asset_hint') }}</p>
                    <div class="space-y-2">
                        <template x-for="asset in customerAssets" :key="asset.id">
                            <label class="flex items-start gap-3 rounded-xl ring-1 ring-gray-200 px-4 py-3 cursor-pointer hover:bg-brand-muted/20"
                                   :class="isCustomerAssetSelected(asset.id) ? 'ring-brand/40 bg-brand-muted/30' : ''">
                                <input type="checkbox"
                                       class="mt-1 rounded border-gray-300 text-brand focus:ring-brand"
                                       :value="asset.id"
                                       :checked="isCustomerAssetSelected(asset.id)"
                                       @change="toggleCustomerAsset(asset.id)">
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-gray-900" x-text="asset.label"></span>
                                    <span class="block text-xs text-gray-500 mt-0.5"
                                          x-text="(assetTypeOptions[asset.asset_type] || asset.asset_type) + (asset.registration_number ? ' · ' + asset.registration_number : '')"></span>
                                    <span class="block text-[11px] text-amber-700 mt-1" x-show="asset.asset_type === 'vehicle' && !asset.has_insurance">
                                        {{ __('borrower.apply.asset_details.vehicle_needs_insurance') }}
                                    </span>
                                </span>
                            </label>
                        </template>
                    </div>
                    <a href="{{ route('site.borrower.profile', ['section' => 'assets', 'add' => 1]) }}"
                       class="inline-flex mt-3 text-sm font-semibold text-brand hover:underline">
                        {{ __('borrower.apply.asset_details.add_another_asset') }} →
                    </a>
                </div>
            </div>

            <div x-show="customerAssets.length && selectedCustomerAssetIds().length" class="glass-card overflow-hidden ring-1 ring-brand/15">
                <div class="bg-gradient-to-br from-brand-muted/50 to-white px-5 sm:px-6 py-4 border-b border-brand/10">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.asset_details.request_not_offer_title') }}</p>
                    <p class="text-sm text-gray-600 mt-2">{{ __('borrower.apply.asset_details.request_not_offer_body') }}</p>
                </div>
                <div class="p-5 sm:p-6 grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            {{ __('borrower.apply.asset_details.requested_amount') }} <span class="text-rose-500">*</span>
                        </label>
                        <input type="number" min="1000" step="1000" x-model.number="form.requested_amount" @input="updateQuote()"
                               class="w-full rounded-xl border-gray-300 ring-1 ring-gray-200 px-4 py-3 text-sm">
                        <p class="text-[11px] text-gray-500 mt-1" x-show="current"
                           x-text="'Min ' + formatTzs(current.min) + ' · Max ' + formatTzs(current.max)"></p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            {{ __('borrower.apply.asset_details.requested_tenure') }} <span class="text-rose-500">*</span>
                        </label>
                        <input type="number" min="1" step="1" x-model.number="form.requested_tenure_months" @input="updateQuote()"
                               class="w-full rounded-xl border-gray-300 ring-1 ring-gray-200 px-4 py-3 text-sm">
                        <p class="text-[11px] text-gray-500 mt-1" x-show="current"
                           x-text="(current.tmin || 3) + '–' + (current.tmax || 24) + ' {{ __('borrower.apply.browse.months_short') }}'"></p>
                    </div>
                </div>
            </div>

            <div x-show="customerAssets.length && selectedCustomerAssetIds().length" class="glass-card p-5 ring-1 ring-gray-200/80">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    {{ __('borrower.apply.quote.purpose') }} <span class="text-rose-500">*</span>
                </label>
                <select x-model="form.purpose" required class="w-full rounded-xl border-gray-300 ring-1 ring-gray-200 px-4 py-3 text-sm">
                    <option value="">{{ __('borrower.apply.quote.select_purpose') }}</option>
                    @foreach ($loanPurposes as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </template>
</div>
