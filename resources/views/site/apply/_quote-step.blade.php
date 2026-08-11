{{-- Quote / loan amount step. Keep simple for lay borrowers across all products. --}}
{{-- Use $data.feeGateOpen so a stale JS bundle without feeGateOpen cannot ReferenceError-blank this step. --}}
<div x-show="stepKey === 'quote' && ! $data.feeGateOpen" class="p-6 sm:p-8" data-wizard-step="quote">
    <x-site.wizard-step-header
        :eyebrow="__('borrower.apply.quote.eyebrow')"
        :title="__('borrower.apply.quote.title')"
        :subtitle="__('borrower.apply.quote.subtitle')"
    />

    <template x-if="current">
        <div class="space-y-5">
            <div class="rounded-2xl bg-white ring-1 ring-brand/15 p-5 sm:p-6 space-y-6">
                {{-- Individual: total loan amount. Group: amount per member (only spine difference). --}}
                <div x-show="!isGroupProduct(current)">
                    <div class="flex items-end justify-between gap-3 mb-3">
                        <label class="text-sm font-semibold text-gray-700">{{ __('borrower.apply.quote.loan_amount') }}</label>
                        <span class="text-lg font-extrabold text-brand tabular-nums" x-text="formatAmount(form.requested_amount)"></span>
                    </div>
                    <input type="range"
                           :min="current.min"
                           :max="current.max"
                           step="50000"
                           x-model.number="form.requested_amount"
                           @input="updateQuote(); scheduleDraftSave()"
                           class="w-full accent-brand h-2 rounded-full">
                    <div class="flex justify-between text-xs text-gray-500 mt-2 tabular-nums">
                        <span x-text="formatAmount(current.min)"></span>
                        <span x-text="formatAmount(current.max)"></span>
                    </div>
                </div>

                <div x-show="isGroupProduct(current)" x-cloak>
                    <div class="flex items-end justify-between gap-3 mb-3">
                        <label class="text-sm font-semibold text-gray-700">{{ __('borrower.apply.group_setup.amount_per_member') }}</label>
                        <span class="text-lg font-extrabold text-brand tabular-nums" x-text="formatTzs(group.amount_per_member || 0)"></span>
                    </div>
                    <input type="range"
                           :min="groupAmountPerMemberMin()" :max="groupAmountPerMemberMax()" step="1000"
                           x-model.number="group.amount_per_member"
                           @input="syncGroupAmounts(); scheduleDraftSave()"
                           class="w-full accent-brand h-2 rounded-full">
                    <div class="flex justify-between text-xs text-gray-500 mt-2 tabular-nums">
                        <span x-text="formatTzs(groupAmountPerMemberMin())"></span>
                        <span x-text="formatTzs(groupAmountPerMemberMax())"></span>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">{{ __('borrower.apply.group_setup.amount_per_member_hint') }}</p>
                    <p class="mt-3 text-sm font-semibold text-gray-800">
                        {{ __('borrower.apply.group_setup.total_preview') }}:
                        <span class="text-brand tabular-nums" x-text="formatTzs(groupTotalAmount())"></span>
                    </p>
                </div>

                {{-- Purpose: locked after selection; Change to edit (x-show keeps select mounted) --}}
                <div class="rounded-2xl bg-brand-muted/40 ring-1 ring-brand/15 p-4 sm:p-5">
                    <div x-show="(isGroupProduct(current) ? group.purpose : form.purpose) && !purposeEditing && !purposeNeedsDetail()" x-cloak class="space-y-2">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-gray-700">{{ __('borrower.apply.quote.purpose') }}</p>
                            <button type="button"
                                    @click="purposeEditing = true"
                                    class="text-xs font-semibold text-brand hover:underline shrink-0">
                                {{ __('borrower.apply.quote.change_purpose') }}
                            </button>
                        </div>
                        <p class="text-base font-bold text-gray-900"
                           x-text="purposeLabels[isGroupProduct(current) ? group.purpose : form.purpose] || (isGroupProduct(current) ? group.purpose : form.purpose)"></p>
                        <p x-show="isOtherPurpose(isGroupProduct(current) ? group.purpose : form.purpose) && form.purpose_other"
                           class="text-sm text-gray-600"
                           x-text="form.purpose_other"></p>
                        <p x-show="purposeNeedsDetail()"
                           class="text-xs font-semibold text-amber-700"
                           x-cloak>{{ __('borrower.apply.alerts.purpose_other_required') }}</p>
                    </div>
                    <div x-show="!(isGroupProduct(current) ? group.purpose : form.purpose) || purposeEditing || purposeNeedsDetail()" x-cloak>
                        <template x-if="!isGroupProduct(current)">
                            <div>
                                <x-site.sheet-select
                                    model="form.purpose"
                                    setter="setLoanPurpose"
                                    :label="__('borrower.apply.quote.purpose')"
                                    :options="$loanPurposes"
                                    :required="true"
                                    :placeholder="__('borrower.apply.quote.select_purpose')"
                                />
                            </div>
                        </template>
                        <template x-if="isGroupProduct(current)">
                            <div>
                                <x-site.sheet-select
                                    model="group.purpose"
                                    setter="setGroupPurpose"
                                    :label="__('borrower.apply.quote.purpose')"
                                    :options="$loanPurposes"
                                    :required="true"
                                    :placeholder="__('borrower.apply.quote.select_purpose')"
                                />
                            </div>
                        </template>
                        <p class="mt-2 text-xs text-brand/80">{{ __('borrower.apply.quote.purpose_hint') }}</p>
                        <div x-show="isOtherPurpose(isGroupProduct(current) ? group.purpose : form.purpose)" x-cloak class="mt-4">
                            <label class="block text-sm font-semibold text-gray-800 mb-1.5">{{ __('borrower.apply.quote.purpose_other_label') }} <span class="text-red-500">*</span></label>
                            <input type="text"
                                   x-model="form.purpose_other"
                                   @input="syncPurposeHidden(); scheduleDraftSave()"
                                   maxlength="120"
                                   class="kf-field"
                                   :required="isOtherPurpose(isGroupProduct(current) ? group.purpose : form.purpose)"
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

                <div>
                    <div class="flex items-end justify-between gap-3 mb-3">
                        <label class="text-sm font-semibold text-gray-700">{{ __('borrower.apply.quote.tenure') }}</label>
                        <span class="text-lg font-extrabold text-brand tabular-nums">
                            <span x-text="form.requested_tenure_months"></span> {{ __('borrower.apply.quote.months') }}
                        </span>
                    </div>
                    <div x-show="isGroupProduct(current) && (current.tenure_options || []).length" x-cloak class="flex flex-wrap gap-2">
                        <template x-for="months in (current.tenure_options || [])" :key="'gq-' + months">
                            <button type="button"
                                    @click="selectGroupTenure(months); scheduleDraftSave()"
                                    class="rounded-full px-4 py-2 text-sm font-semibold ring-1 transition"
                                    :class="Number(form.requested_tenure_months) === Number(months) ? 'bg-brand text-white ring-brand' : 'bg-white text-gray-700 ring-gray-200 hover:ring-brand/40'">
                                <span x-text="months"></span> {{ __('borrower.apply.quote.months') }}
                            </button>
                        </template>
                    </div>
                    <div x-show="!isGroupProduct(current) || !(current.tenure_options || []).length">
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
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="rounded-2xl bg-white ring-1 ring-brand/20 p-5 min-h-[7.5rem] flex flex-col justify-between">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold"
                       x-text="repaymentCadence() === 'monthly'
                           ? @js(__('borrower.apply.quote.monthly_installment_tzs'))
                           : @js(__('borrower.apply.quote.weekly_installment_tzs'))"></p>
                    <p class="text-2xl font-extrabold mt-3 text-gray-900 tabular-nums" x-text="formatAmount(quote.primary ?? quote.monthly)"></p>
                    <p x-show="isGroupProduct(current)" x-cloak class="text-[11px] text-gray-500 mt-1">{{ __('borrower.apply.group_setup.per_member_installment_hint') }}</p>
                </div>
                <div class="rounded-2xl bg-white ring-1 ring-gray-200/80 p-5 min-h-[7.5rem] flex flex-col justify-between">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold"
                       x-text="isGroupProduct(current)
                           ? @js(__('borrower.apply.group_setup.total_preview'))
                           : @js(__('borrower.apply.quote.total_repayment_tzs'))"></p>
                    <p class="text-2xl font-extrabold mt-3 text-gray-900 tabular-nums"
                       x-text="isGroupProduct(current) ? formatTzs(groupTotalAmount()) : formatAmount(quote.total)"></p>
                </div>
            </div>

            <div x-show="canShowQuoteRewards()" x-cloak
                 class="rounded-2xl bg-emerald-50 ring-1 ring-emerald-200 px-5 py-4 flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0 space-y-1">
                    <p class="text-[10px] uppercase tracking-widest text-emerald-800 font-semibold">{{ __('borrower.apply.quote.engagement_title') }}</p>
                    <p class="text-sm text-emerald-900" x-show="hasActiveLoanReward()">
                        {{ __('borrower.apply.quote.active_reward') }}
                    </p>
                    <p class="text-sm text-emerald-900" x-show="!hasActiveLoanReward() && feeLoyaltyOption?.can_redeem">
                        {{ __('borrower.apply.quote.redeem_hint') }}
                    </p>
                </div>
                <a href="{{ route('site.borrower.engagement', ['tab' => 'rewards']) }}"
                   class="shrink-0 text-xs font-semibold text-emerald-900 underline">
                    {{ __('borrower.apply.quote.rewards_cta') }}
                </a>
            </div>
        </div>
    </template>

    @foreach (($productQuestions ?? []) as $code => $block)
        <div x-show="stepKey === 'quote' && ! $data.feeGateOpen && current && current.code === @js($code)" x-cloak
             class="mt-5" data-wizard-step="quote">
            <div class="glass-card p-5 sm:p-6 ring-1 ring-brand/10">
                <h3 class="text-sm font-bold text-gray-900 mb-4">{{ $block['title'] ?? __('borrower.apply.product_questions.additional') }}</h3>
                <div class="grid sm:grid-cols-2 gap-4">
                    @foreach ($block['fields'] as $field)
                        @if (($field['type'] ?? 'text') === 'tz_address')
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">{{ $field['label'] }}</label>
                                <x-site.address-fields
                                    form-key="product_question"
                                    :prefix="$field['prefix'] ?? ''"
                                    :required="$field['required'] ?? true"
                                />
                            </div>
                        @else
                            <div class="{{ ($field['type'] ?? 'text') === 'textarea' ? 'sm:col-span-2' : '' }}">
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">{{ $field['label'] }}</label>
                                @if (($field['type'] ?? 'text') === 'select')
                                    <x-site.profile-select
                                        :name="'product_question['.$field['key'].']'"
                                        :options="$field['options'] ?? []"
                                        :required="$field['required'] ?? false"
                                        :placeholder="__('borrower.profile.select')"
                                        select-class="w-full rounded-xl border-gray-300 ring-1 ring-gray-200 px-4 py-3 text-sm focus:ring-brand"
                                    />
                                @elseif (($field['type'] ?? 'text') === 'textarea')
                                    <textarea name="product_question[{{ $field['key'] }}]" rows="3" placeholder="{{ $field['placeholder'] ?? '' }}"
                                              @if (! empty($field['required'])) required @endif
                                              class="w-full rounded-xl border-gray-300 ring-1 ring-gray-200 px-4 py-3 text-sm focus:ring-brand"></textarea>
                                @else
                                    <input type="text" name="product_question[{{ $field['key'] }}]" placeholder="{{ $field['placeholder'] ?? '' }}"
                                           @if (! empty($field['required'])) required @endif
                                           class="w-full rounded-xl border-gray-300 ring-1 ring-gray-200 px-4 py-3 text-sm focus:ring-brand">
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
</div>
