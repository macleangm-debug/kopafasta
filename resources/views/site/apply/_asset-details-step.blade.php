{{-- Asset-backed collateral step — hide while fee gate is open (same as IL quote). --}}
@php
    $assetCardService = app(\App\Services\CollateralCardService::class);
    $assetTypeIcons = \App\Models\CustomerAsset::typeIcons();
    $assetService = app(\App\Services\CustomerAssetService::class);
@endphp
<div x-show="stepKey === 'asset_details' && ! $data.feeGateOpen" class="p-6 sm:p-8" data-wizard-step="asset_details">
    <x-site.wizard-step-header
        :eyebrow="__('borrower.apply.steps.asset_details')"
        :title="__('borrower.apply.asset_details.title')"
        :subtitle="__('borrower.apply.asset_details.subtitle')"
    />

    <template x-if="current">
        <div class="space-y-6">
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500">
                <template x-for="n in 3" :key="'as'+n">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="size-6 rounded-full grid place-items-center text-[11px]"
                              :class="assetSubstep >= n ? 'bg-brand text-white' : 'bg-gray-100 text-gray-500'"
                              x-text="n"></span>
                        <span x-show="n < 3" class="text-gray-300" aria-hidden="true">·</span>
                    </span>
                </template>
            </div>

            <div x-show="!customerAssets.length" class="rounded-2xl bg-brand-muted/50 ring-1 ring-brand/15 p-5 sm:p-6">
                <p class="text-sm font-semibold text-brand">{{ __('borrower.apply.asset_details.no_assets_title') }}</p>
                <p class="text-sm text-brand/80 mt-2">{{ __('borrower.apply.asset_details.no_assets_body') }}</p>
                <a href="{{ route('site.borrower.profile', ['section' => 'assets']) }}"
                   class="inline-flex mt-4 items-center gap-2 text-sm font-semibold text-brand hover:underline">
                    {{ __('borrower.apply.asset_details.add_asset_link') }} →
                </a>
            </div>

            <div x-show="customerAssets.length && assetSubstep === 1" class="space-y-4">
                <div class="glass-card p-5 ring-1 ring-brand/15 space-y-3">
                    <div>
                        <label class="block text-sm font-semibold text-gray-900 mb-1">
                            {{ __('borrower.apply.asset_details.choose_existing') }} <span class="text-rose-500">*</span>
                        </label>
                        <p class="text-xs text-gray-500">{{ __('borrower.apply.asset_details.multi_asset_hint') }}</p>
                    </div>

                    @foreach (($customerAssets ?? collect()) as $asset)
                        @php
                            $incomplete = $assetService->incompleteForApply($asset);
                            $pledged = $assetService->isPledgedToAnotherApplication($asset);
                            $selectable = $incomplete === null && ! $pledged;
                            $card = $assetCardService->forAsset(
                                $asset,
                                null,
                                \App\Services\CollateralCardService::VIEWER_BORROWER,
                                [
                                    'status_label' => $incomplete
                                        ? __('borrower.assets.collateral_incomplete')
                                        : ($pledged ? __('borrower.apply.asset_details.asset_already_pledged_short') : null),
                                ]
                            );
                        @endphp
                        <div class="rounded-2xl ring-1 p-1 transition"
                             :class="isCustomerAssetSelected({{ (int) $asset->id }}) ? 'ring-brand/40 bg-brand-muted/20' : 'ring-transparent'">
                            <div class="flex items-start gap-3">
                                <input type="checkbox"
                                       class="mt-4 ml-2 rounded border-gray-300 text-brand focus:ring-brand shrink-0"
                                       value="{{ (int) $asset->id }}"
                                       :checked="isCustomerAssetSelected({{ (int) $asset->id }})"
                                       @if (! $selectable) disabled @endif
                                       @change="toggleCustomerAsset({{ (int) $asset->id }})">
                                <div class="min-w-0 flex-1">
                                    <x-site.collateral-card :selected="$card" :type-icons="$assetTypeIcons">
                                        @if ($incomplete)
                                            <p class="text-xs font-medium text-amber-900 mt-1">
                                                @if ($incomplete === 'ownership')
                                                    {{ __('borrower.assets.collateral_incomplete_ownership') }}
                                                @else
                                                    {{ __('borrower.assets.collateral_incomplete_photos') }}
                                                @endif
                                            </p>
                                            <a href="{{ route('site.borrower.profile', ['section' => 'assets', 'edit' => $asset->id]) }}"
                                               class="inline-flex mt-1 text-xs font-semibold text-brand hover:underline"
                                               @click.stop>
                                                {{ __('borrower.apply.asset_details.complete_in_profile_cta') }} →
                                            </a>
                                        @elseif ($pledged)
                                            <p class="text-xs font-medium text-amber-900 mt-1">{{ __('borrower.apply.asset_details.asset_already_pledged_short') }}</p>
                                        @endif
                                    </x-site.collateral-card>
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <a href="{{ route('site.borrower.profile', ['section' => 'assets', 'add' => 1]) }}"
                       class="inline-flex mt-1 text-sm font-semibold text-brand hover:underline">
                        {{ __('borrower.apply.asset_details.add_another_asset') }} →
                    </a>
                </div>
            </div>

            <div x-show="customerAssets.length && selectedCustomerAssetIds().length && assetSubstep === 2" class="glass-card p-5 sm:p-6 ring-1 ring-brand/15 space-y-6">
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
                           @input="updateQuote(); scheduleDraftSave()"
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
                           @input="updateQuote(); scheduleDraftSave()"
                           class="w-full accent-brand h-2 rounded-full">
                    <div class="flex justify-between text-xs text-gray-500 mt-2 tabular-nums">
                        <span><span x-text="current.tmin"></span> {{ __('borrower.apply.browse.months_short') }}</span>
                        <span><span x-text="current.tmax"></span> {{ __('borrower.apply.browse.months_short') }}</span>
                    </div>
                </div>
            </div>

            <div x-show="customerAssets.length && selectedCustomerAssetIds().length && assetSubstep === 3" class="glass-card p-5 ring-1 ring-gray-200/80">
                <div x-show="form.purpose && !purposeEditing && !purposeNeedsDetail()" x-cloak class="space-y-2">
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
                    <p x-show="isOtherPurpose() && form.purpose_other"
                       class="text-sm text-gray-600"
                       x-text="form.purpose_other"></p>
                    <p x-show="purposeNeedsDetail()"
                       class="text-xs font-semibold text-amber-700"
                       x-cloak>{{ __('borrower.apply.alerts.purpose_other_required') }}</p>
                </div>
                <div x-show="!form.purpose || purposeEditing || purposeNeedsDetail()" x-cloak>
                    <x-site.sheet-select
                        model="form.purpose"
                        setter="setLoanPurpose"
                        :label="__('borrower.apply.quote.purpose')"
                        :options="$loanPurposes"
                        :required="true"
                        :placeholder="__('borrower.apply.quote.select_purpose')"
                    />
                    <div x-show="isOtherPurpose()" x-cloak class="mt-4">
                        <label class="block text-sm font-semibold text-gray-800 mb-1.5">{{ __('borrower.apply.quote.purpose_other_label') }} <span class="text-red-500">*</span></label>
                        <input type="text"
                               x-model="form.purpose_other"
                               @input="syncPurposeHidden(); scheduleDraftSave()"
                               maxlength="120"
                               class="kf-field"
                               :required="isOtherPurpose()"
                               placeholder="{{ __('borrower.apply.quote.purpose_other_placeholder') }}">
                        <button type="button"
                                x-show="form.purpose_other && String(form.purpose_other).trim()"
                                x-cloak
                                @click="purposeEditing = false; scheduleDraftSave()"
                                class="mt-3 inline-flex text-xs font-semibold text-brand hover:underline">
                            {{ __('borrower.apply.quote.purpose_other_done') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
