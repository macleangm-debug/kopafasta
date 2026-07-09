{{-- Asset-backed collateral step --}}
<div x-show="stepKey === 'asset_details'" class="p-6 sm:p-8">
    <x-site.wizard-step-header
        :eyebrow="__('borrower.apply.steps.asset_details')"
        :title="__('borrower.apply.asset_details.title')"
        :subtitle="__('borrower.apply.asset_details.subtitle')"
    />

    <template x-if="current">
        <div class="space-y-6">
            <div x-show="!customerAssets.length" class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 p-5 sm:p-6">
                <p class="text-sm font-semibold text-amber-900">{{ __('borrower.apply.asset_details.no_assets_title') }}</p>
                <p class="text-sm text-amber-800 mt-2">{{ __('borrower.apply.asset_details.no_assets_body') }}</p>
                <a href="{{ route('site.borrower.profile', ['section' => 'assets']) }}"
                   class="inline-flex mt-4 items-center gap-2 text-sm font-semibold text-brand hover:underline">
                    {{ __('borrower.apply.asset_details.add_asset_link') }} →
                </a>
            </div>

            <div x-show="customerAssets.length" class="space-y-4">
                <div class="glass-card p-5 ring-1 ring-brand/15">
                    <label class="block text-sm font-semibold text-gray-900 mb-2">
                        {{ __('borrower.apply.asset_details.choose_existing') }} <span class="text-rose-500">*</span>
                    </label>
                    <select x-model="form.customer_asset_id" @change="applyExistingAsset()" required
                            class="w-full rounded-xl border-gray-300 ring-1 ring-gray-200 px-4 py-3 text-sm focus:ring-brand">
                        <option value="">{{ __('borrower.profile.select') }}</option>
                        <template x-for="asset in customerAssets" :key="asset.id">
                            <option :value="asset.id" x-text="asset.label + (asset.registration_number ? ' · ' + asset.registration_number : '')"></option>
                        </template>
                    </select>
                </div>

                <div x-show="selectedCustomerAsset()" class="glass-card p-5 ring-1 ring-gray-200/80 text-sm space-y-2">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.asset_details.selected_asset') }}</p>
                    <div class="flex justify-between gap-3 py-1">
                        <span class="text-gray-500">{{ __('borrower.apply.asset_details.asset_type') }}</span>
                        <span class="font-semibold" x-text="assetTypeOptions[form.asset_type] || form.asset_type || '—'"></span>
                    </div>
                    <div class="flex justify-between gap-3 py-1" x-show="selectedCustomerAsset()?.registration_number">
                        <span class="text-gray-500">{{ __('borrower.profile.registration_number') }}</span>
                        <span class="font-semibold" x-text="selectedCustomerAsset()?.registration_number"></span>
                    </div>
                    <div x-show="selectedCustomerAsset()?.description" class="pt-1">
                        <span class="text-gray-500 block text-xs mb-1">{{ __('borrower.profile.description') }}</span>
                        <span x-text="selectedCustomerAsset()?.description"></span>
                    </div>
                </div>
            </div>

            <div x-show="customerAssets.length && form.customer_asset_id" class="glass-card overflow-hidden ring-1 ring-brand/15">
                <div class="bg-gradient-to-br from-brand-muted/50 to-white px-5 sm:px-6 py-4 border-b border-brand/10">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.quote.configure') }}</p>
                </div>
                <div class="p-5 sm:p-6 space-y-6">
                    <div>
                        <div class="flex items-end justify-between gap-3 mb-3">
                            <label class="text-sm font-semibold text-gray-700">{{ __('borrower.apply.quote.loan_amount') }}</label>
                            <span class="text-lg font-extrabold text-brand tabular-nums" x-text="formatTzs(form.requested_amount)"></span>
                        </div>
                        <input type="range" :min="current.min" :max="current.max" step="50000"
                               x-model.number="form.requested_amount" @input="updateQuote()"
                               class="w-full accent-brand h-2 rounded-full">
                    </div>
                    <div>
                        <div class="flex items-end justify-between gap-3 mb-3">
                            <label class="text-sm font-semibold text-gray-700">{{ __('borrower.apply.quote.tenure') }}</label>
                            <span class="text-lg font-extrabold text-brand tabular-nums">
                                <span x-text="form.requested_tenure_months"></span> {{ __('borrower.apply.quote.months') }}
                            </span>
                        </div>
                        <input type="range" :min="current.tmin" :max="current.tmax" step="1"
                               x-model.number="form.requested_tenure_months" @input="updateQuote()"
                               class="w-full accent-brand h-2 rounded-full">
                    </div>
                    <p class="text-xs text-brand">{{ __('borrower.apply.asset_details.ltv_note') }}</p>
                </div>
            </div>

            <div x-show="customerAssets.length && form.customer_asset_id" class="glass-card p-5 ring-1 ring-gray-200/80">
                <label class="block text-sm font-semibold text-gray-700 mb-2">{{ __('borrower.apply.quote.purpose') }}</label>
                <select x-model="form.purpose" class="w-full rounded-xl border-gray-300 ring-1 ring-gray-200 px-4 py-3 text-sm">
                    <option value="">{{ __('borrower.apply.quote.select_purpose') }}</option>
                    @foreach ($loanPurposes as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </template>
</div>
