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
                            <label class="flex items-center gap-3 rounded-xl ring-1 ring-gray-200 px-3 py-3 cursor-pointer hover:bg-brand-muted/20"
                                   :class="isCustomerAssetSelected(asset.id) ? 'ring-brand/40 bg-brand-muted/30' : ''">
                                <input type="checkbox"
                                       class="rounded border-gray-300 text-brand focus:ring-brand shrink-0"
                                       :value="asset.id"
                                       :checked="isCustomerAssetSelected(asset.id)"
                                       @change="toggleCustomerAsset(asset.id)">
                                <span class="size-14 rounded-xl overflow-hidden bg-brand-muted/40 ring-1 ring-brand/10 shrink-0 grid place-items-center">
                                    <template x-if="asset.thumbnail_url">
                                        <img :src="asset.thumbnail_url" alt="" class="size-full object-cover">
                                    </template>
                                    <template x-if="!asset.thumbnail_url">
                                        <span class="text-lg" x-text="(assetTypeOptions[asset.asset_type] || '📦').charAt(0)"></span>
                                    </template>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold text-gray-900" x-text="asset.label"></span>
                                    <span class="block text-xs text-gray-500 mt-0.5"
                                          x-text="(assetTypeOptions[asset.asset_type] || asset.asset_type) + (asset.registration_number ? ' · ' + asset.registration_number : '')"></span>
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

            <div x-show="customerAssets.length && selectedCustomerAssetIds().length" class="glass-card p-5 sm:p-6 ring-1 ring-brand/15 space-y-6">
                <div>
                    <div class="flex items-end justify-between gap-3 mb-3">
                        <label class="text-sm font-semibold text-gray-700">{{ __('borrower.apply.quote.loan_amount') }} <span class="text-rose-500">*</span></label>
                        <span class="text-lg font-extrabold text-brand tabular-nums" x-text="formatTzs(form.requested_amount)"></span>
                    </div>
                    <input type="range"
                           :min="current.min"
                           :max="current.max"
                           step="50000"
                           x-model.number="form.requested_amount"
                           @input="updateQuote()"
                           class="w-full accent-brand h-2 rounded-full">
                    <div class="flex justify-between text-xs text-gray-500 mt-2 tabular-nums">
                        <span x-text="formatTzs(current.min)"></span>
                        <span x-text="formatTzs(current.max)"></span>
                    </div>
                </div>
                <div>
                    <div class="flex items-end justify-between gap-3 mb-3">
                        <label class="text-sm font-semibold text-gray-700">{{ __('borrower.apply.quote.tenure') }} <span class="text-rose-500">*</span></label>
                        <span class="text-lg font-extrabold text-brand tabular-nums">
                            <span x-text="form.requested_tenure_months"></span> {{ __('borrower.apply.quote.months') }}
                        </span>
                    </div>
                    <input type="range"
                           :min="current.tmin"
                           :max="current.tmax"
                           step="1"
                           x-model.number="form.requested_tenure_months"
                           @input="updateQuote()"
                           class="w-full accent-brand h-2 rounded-full">
                    <div class="flex justify-between text-xs text-gray-500 mt-2 tabular-nums">
                        <span><span x-text="current.tmin"></span> {{ __('borrower.apply.browse.months_short') }}</span>
                        <span><span x-text="current.tmax"></span> {{ __('borrower.apply.browse.months_short') }}</span>
                    </div>
                </div>
            </div>

            <div x-show="customerAssets.length && selectedCustomerAssetIds().length" class="glass-card p-5 ring-1 ring-gray-200/80">
                <div x-show="form.purpose && !purposeEditing" x-cloak class="space-y-2">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-gray-700">{{ __('borrower.apply.quote.purpose') }}</p>
                        <button type="button"
                                @click="purposeEditing = true"
                                class="text-xs font-semibold text-brand hover:underline shrink-0">
                            {{ __('borrower.apply.quote.change_purpose') }}
                        </button>
                    </div>
                    <p class="text-base font-bold text-gray-900"
                       x-text="purposeLabels[form.purpose] || form.purpose"></p>
                </div>
                <template x-if="!form.purpose || purposeEditing">
                    <div>
                        <x-site.sheet-select
                            model="form.purpose"
                            :label="__('borrower.apply.quote.purpose')"
                            :options="$loanPurposes"
                            :required="true"
                            :placeholder="__('borrower.apply.quote.select_purpose')"
                            on-pick="purposeEditing = false; scheduleDraftSave()"
                        />
                    </div>
                </template>
            </div>
        </div>
    </template>
</div>
