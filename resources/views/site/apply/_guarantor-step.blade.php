<div x-show="stepKey === 'guarantor'" class="p-6 sm:p-8">
    <x-site.wizard-step-header
        :eyebrow="__('borrower.apply.steps.guarantor')"
        :title="__('borrower.apply.guarantor')"
        :subtitle="__('borrower.apply.guarantor_required')"
    />

    <div x-show="Object.keys(guarantorErrors).length" x-cloak class="mb-5 rounded-2xl bg-rose-50 ring-1 ring-rose-200 px-4 py-4 text-sm text-rose-800">
        <p class="font-semibold mb-1">{{ __('borrower.apply.guarantor_fields.missing_fields_title') }}</p>
        <ul class="list-disc list-inside space-y-0.5">
            <template x-for="(msg, key) in guarantorErrors" :key="key">
                <li x-text="msg"></li>
            </template>
        </ul>
    </div>

    <div x-show="isGuarantorLocked()" x-cloak class="glass-card rounded-2xl px-5 py-5 space-y-4 mb-5 ring-1"
         :class="guarantorLockedCardClass()">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-base font-semibold text-gray-900 truncate" x-text="guarantorSummaryText()"></p>
                <p class="text-[10px] uppercase tracking-widest mt-1" :class="guarantorLockedCardMutedClass()">{{ __('borrower.apply.guarantor_locked_status') }}</p>
            </div>
            <span class="inline-flex shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1"
                  :class="guarantorStatusBadgeClass()"
                  x-text="guarantorStatusLabel()"></span>
        </div>
        <div x-show="form.guarantor_mode === 'external' && externalGuarantor?.invitation_url" x-cloak x-data="{ copied: false }"
             class="rounded-2xl bg-gradient-to-br from-brand via-brand to-brand-light text-white px-5 py-5 space-y-4 shadow-sm">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.apply.guarantor_fields.share_via') }}</p>
                <p class="text-sm text-white/90 mt-1">{{ __('borrower.apply.guarantor_fields.share_ready') }}</p>
            </div>
            <p class="text-xs font-mono text-brand bg-brand-gold/90 rounded-xl px-3 py-2.5 break-all" x-text="externalGuarantor.short_url || externalGuarantor.invitation_url"></p>
            <div class="flex flex-wrap gap-2">
                <a :href="externalGuarantor.whatsapp_url || '#'" :class="!externalGuarantor.whatsapp_url && 'pointer-events-none opacity-50'" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-2 bg-emerald-500 hover:bg-emerald-400 text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                    {{ __('borrower.apply.guarantor_fields.share_whatsapp') }}
                </a>
                <a :href="externalGuarantor.sms_url || '#'" :class="!externalGuarantor.sms_url && 'pointer-events-none opacity-50'"
                   class="inline-flex items-center gap-2 bg-white/15 hover:bg-white/25 ring-1 ring-white/30 text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                    {{ __('borrower.apply.guarantor_fields.share_sms') }}
                </a>
                <a :href="externalGuarantor.email_url || '#'" :class="!externalGuarantor.email_url && 'pointer-events-none opacity-50'"
                   class="inline-flex items-center gap-2 bg-white/15 hover:bg-white/25 ring-1 ring-white/30 text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                    {{ __('borrower.apply.guarantor_fields.share_email') }}
                </a>
                <button type="button"
                        @click="navigator.clipboard.writeText(externalGuarantor.short_url || externalGuarantor.invitation_url); copied = true; setTimeout(() => copied = false, 2000)"
                        class="inline-flex items-center gap-2 bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-4 py-2.5 rounded-xl text-sm">
                    <span x-text="copied ? @js(__('borrower.apply.guarantor_fields.link_copied')) : @js(__('borrower.apply.guarantor_fields.share_copy'))"></span>
                </button>
            </div>
            <p class="text-xs text-brand-gold/90">{{ __('borrower.apply.guarantor_fields.share_ready_continue') }}</p>
        </div>
        <button type="button"
                @click="changeGuarantor()"
                :disabled="guarantorChanging"
                class="inline-flex bg-white ring-1 ring-brand/20 hover:bg-brand-muted/40 text-brand font-semibold px-4 py-2.5 rounded-xl text-sm disabled:opacity-60">
            {{ __('borrower.apply.change_guarantor') }}
        </button>
    </div>

    <div class="space-y-5" x-show="!isGuarantorLocked()">
        <div x-show="previousGuarantors.length" x-cloak class="glass-card rounded-2xl ring-1 ring-brand/10 px-5 py-5 space-y-3">
            <p class="text-sm font-semibold text-sky-900">{{ __('borrower.apply.previous_guarantor.title') }}</p>
            <div class="flex flex-wrap gap-3">
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="radio" name="guarantor_choice" value="previous" @change="form.guarantor_mode = 'previous'" :checked="form.guarantor_mode === 'previous'" class="text-brand">
                    {{ __('borrower.apply.previous_guarantor.use_previous') }}
                </label>
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="radio" name="guarantor_choice" value="new" @change="form.guarantor_mode = ''" :checked="form.guarantor_mode !== 'previous'" class="text-brand">
                    {{ __('borrower.apply.previous_guarantor.select_new') }}
                </label>
            </div>
            <div x-show="form.guarantor_mode === 'previous'" class="space-y-2">
                <template x-for="item in previousGuarantors" :key="item.id">
                    <button type="button"
                            @click="selectPreviousGuarantor(item.id)"
                            class="w-full text-left rounded-lg bg-white ring-1 ring-sky-200 px-3 py-2 text-sm hover:bg-sky-100/60">
                        <span class="font-semibold text-gray-900" x-text="item.label"></span>
                        <span class="block text-xs text-gray-500 mt-0.5" x-text="item.kyc_fresh ? @js(__('borrower.apply.previous_guarantor.kyc_fresh')) : @js(__('borrower.apply.previous_guarantor.new_request'))"></span>
                    </button>
                </template>
            </div>
        </div>

        <div x-show="form.guarantor_mode !== 'previous'" class="space-y-4">
            <div>
                <p class="text-sm font-semibold text-gray-900 mb-1">{{ __('borrower.apply.guarantor_fields.choose_type_title') }}</p>
                <p class="text-xs text-gray-500 mb-3">{{ __('borrower.apply.guarantor_fields.choose_type_hint') }}</p>
                <div class="glass-card rounded-2xl ring-1 ring-gray-200/80 p-1.5 flex flex-wrap gap-1">
                    <button type="button"
                            @click="form.guarantor_mode = 'internal'"
                            class="flex-1 min-w-[10rem] text-center text-sm font-semibold px-4 py-2.5 rounded-xl transition"
                            :class="form.guarantor_mode === 'internal' ? 'bg-brand text-white' : 'text-gray-600 hover:bg-gray-50'">
                        {{ __('borrower.apply.internal_guarantor') }}
                    </button>
                    <button type="button"
                            @click="form.guarantor_mode = 'external'"
                            class="flex-1 min-w-[10rem] text-center text-sm font-semibold px-4 py-2.5 rounded-xl transition"
                            :class="form.guarantor_mode === 'external' ? 'bg-brand text-white' : 'text-gray-600 hover:bg-gray-50'">
                        {{ __('borrower.apply.external_guarantor') }}
                    </button>
                </div>
            </div>

            <div x-show="!form.guarantor_mode || form.guarantor_mode === 'none'" x-cloak
                 class="rounded-2xl border border-dashed border-gray-200 bg-gray-50/80 px-5 py-8 text-center">
                <p class="text-sm text-gray-600">{{ __('borrower.apply.guarantor_fields.choose_type_empty') }}</p>
            </div>

            <div x-show="form.guarantor_mode === 'internal'" class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.apply.guarantor_fields.membership_no') }}</label>
                    <div class="flex rounded-lg ring-1 overflow-hidden" :class="guarantorErrors.internal_member_no ? 'ring-rose-400' : 'ring-gray-200'">
                        <span class="inline-flex items-center px-3 bg-gray-100 text-sm font-mono text-gray-600 border-r border-gray-200">KPF-TZ-</span>
                        <input name="internal_member_no" x-model="form.internal_member_no" @input="delete guarantorErrors.internal_member_no; guarantorLookup.ok = false" placeholder="{{ __('borrower.apply.guarantor_fields.membership_placeholder') }}" autocomplete="off" class="flex-1 border-0 px-3 py-2.5 text-sm font-mono focus:ring-0">
                    </div>
                    <p x-show="guarantorErrors.internal_member_no" class="mt-1 text-xs text-rose-600" x-text="guarantorErrors.internal_member_no"></p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.phone') }}</label>
                    <div class="flex rounded-lg ring-1 overflow-hidden" :class="guarantorErrors.internal_guarantor_phone ? 'ring-rose-400' : 'ring-gray-200'">
                        <span class="inline-flex items-center px-3 bg-gray-100 text-sm text-gray-600 border-r border-gray-200">+255</span>
                        <input name="internal_guarantor_phone" x-model="form.internal_guarantor_phone" @input="delete guarantorErrors.internal_guarantor_phone; guarantorLookup.ok = false" inputmode="numeric" placeholder="{{ __('borrower.apply.guarantor_fields.phone_placeholder') }}" autocomplete="off" class="flex-1 border-0 px-3 py-2.5 text-sm focus:ring-0">
                    </div>
                    <p x-show="guarantorErrors.internal_guarantor_phone" class="mt-1 text-xs text-rose-600" x-text="guarantorErrors.internal_guarantor_phone"></p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.apply.guarantor_fields.guarantor_name') }}</label>
                    <input name="internal_guarantor_name" x-model="form.internal_guarantor_name" @input="delete guarantorErrors.internal_guarantor_name; guarantorLookup.ok = false"
                           :class="guarantorErrors.internal_guarantor_name ? 'ring-rose-400' : 'ring-gray-200'"
                           class="w-full rounded-lg border-gray-300 ring-1 px-3 py-2.5 text-sm" placeholder="{{ __('borrower.apply.guarantor_fields.name_placeholder') }}" autocomplete="off">
                    <p x-show="guarantorErrors.internal_guarantor_name" class="mt-1 text-xs text-rose-600" x-text="guarantorErrors.internal_guarantor_name"></p>
                    <p class="mt-1 text-xs text-gray-500">{{ __('borrower.apply.guarantor_fields.guarantor_name_hint') }}</p>
                </div>
                <div x-show="guarantorLookup.ok" x-cloak class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-900">
                    <p class="font-semibold">{{ __('borrower.apply.alerts.guarantor_verified') }}</p>
                    <p class="mt-1" x-text="guarantorLookup.label"></p>
                </div>
                <div x-show="guarantorValidating" x-cloak class="rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-3 text-sm text-sky-900">
                    <p class="font-semibold">{{ __('borrower.apply.guarantor_fields.validating') }}</p>
                    <p class="mt-1 text-sky-800">{{ __('borrower.apply.guarantor_fields.validating_hint') }}</p>
                </div>
                <div x-show="guarantorLookup.error && !guarantorLookup.ok && !guarantorValidating" x-cloak
                     class="rounded-xl bg-rose-50 ring-1 ring-rose-200 px-4 py-3 text-sm text-rose-900">
                    <p class="font-semibold">{{ __('borrower.apply.guarantor_fields.validation_failed_title') }}</p>
                    <p class="mt-1" x-text="guarantorLookup.error"></p>
                </div>
                <p class="text-xs text-gray-500">{{ __('borrower.apply.guarantor_fields.membership_hint_short') }}</p>
                <button type="button"
                        @click="validateInternalGuarantor()"
                        :disabled="guarantorValidating"
                        class="inline-flex bg-brand hover:bg-brand-light disabled:opacity-60 text-white font-semibold px-5 py-2.5 rounded-xl text-sm">
                    <span x-text="guarantorValidating ? @js(__('borrower.apply.guarantor_fields.validating')) : @js(__('borrower.apply.guarantor_fields.validate'))"></span>
                </button>
            </div>

            <input type="hidden" name="external_invitation_id" :value="externalGuarantor?.invitation_id || ''">
            <div x-show="form.guarantor_mode === 'external'" class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.first_name') }} <span class="text-rose-500">*</span></label>
                    <input name="external_first_name" x-model="form.external_first_name" @input="delete guarantorErrors.external_first_name; invalidateExternalInvite()"
                           :class="guarantorErrors.external_first_name ? 'ring-rose-400' : 'ring-gray-200'"
                           class="w-full rounded-lg border-gray-300 ring-1 px-3 py-2.5 text-sm" placeholder="{{ __('borrower.apply.guarantor_fields.first_name_placeholder') }}" autocomplete="off">
                    <p x-show="guarantorErrors.external_first_name" class="mt-1 text-xs text-rose-600" x-text="guarantorErrors.external_first_name"></p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.middle_name') }}</label>
                    <input name="external_middle_name" x-model="form.external_middle_name" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm" placeholder="{{ __('borrower.apply.guarantor_fields.middle_name_placeholder') }}" autocomplete="off">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.last_name') }} <span class="text-rose-500">*</span></label>
                    <input name="external_last_name" x-model="form.external_last_name" @input="delete guarantorErrors.external_last_name; invalidateExternalInvite()"
                           :class="guarantorErrors.external_last_name ? 'ring-rose-400' : 'ring-gray-200'"
                           class="w-full rounded-lg border-gray-300 ring-1 px-3 py-2.5 text-sm" placeholder="{{ __('borrower.apply.guarantor_fields.last_name_placeholder') }}" autocomplete="off">
                    <p x-show="guarantorErrors.external_last_name" class="mt-1 text-xs text-rose-600" x-text="guarantorErrors.external_last_name"></p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.apply.guarantor_fields.relationship') }} <span class="text-rose-500">*</span></label>
                    <select name="external_relationship" x-model="form.external_relationship" @change="delete guarantorErrors.external_relationship; invalidateExternalInvite()"
                            :class="guarantorErrors.external_relationship ? 'ring-rose-400' : 'ring-gray-200'"
                            class="w-full rounded-lg border-gray-300 ring-1 px-3 py-2.5 text-sm">
                        <option value="">{{ __('borrower.profile.select') }}</option>
                        @foreach (trans('borrower.profile.guarantor_relationship_options') as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <p x-show="guarantorErrors.external_relationship" class="mt-1 text-xs text-rose-600" x-text="guarantorErrors.external_relationship"></p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.phone') }} <span class="text-rose-500">*</span></label>
                    <div class="flex rounded-lg ring-1 overflow-hidden" :class="guarantorErrors.external_phone ? 'ring-rose-400' : 'ring-gray-200'">
                        <span class="inline-flex items-center px-3 bg-gray-100 text-sm text-gray-600 border-r border-gray-200">+255</span>
                        <input name="external_phone" x-model="form.external_phone" @input="delete guarantorErrors.external_phone; invalidateExternalInvite()" inputmode="numeric" placeholder="{{ __('borrower.apply.guarantor_fields.phone_placeholder') }}" autocomplete="off" class="flex-1 border-0 px-3 py-2.5 text-sm focus:ring-0">
                    </div>
                    <p x-show="guarantorErrors.external_phone" class="mt-1 text-xs text-rose-600" x-text="guarantorErrors.external_phone"></p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.email') }} {{ __('borrower.profile.optional') }}</label>
                    <input name="external_email" x-model="form.external_email" type="email" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm" placeholder="{{ __('borrower.apply.guarantor_fields.email_placeholder') }}" autocomplete="off">
                </div>
                <div class="sm:col-span-2 grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.region') }} <span class="text-rose-500">*</span></label>
                        <select name="external_region" x-model="form.external_region" @change="onExternalRegionChange(); delete guarantorErrors.external_region; invalidateExternalInvite()"
                                :class="guarantorErrors.external_region ? 'ring-rose-400' : 'ring-gray-200'"
                                class="w-full rounded-lg border-gray-300 ring-1 px-3 py-2.5 text-sm">
                            <option value="">{{ __('borrower.profile.select_region') }}</option>
                            @foreach (config('tanzania_locations') as $regionName => $districts)
                                <option value="{{ $regionName }}">{{ $regionName }}</option>
                            @endforeach
                        </select>
                        <p x-show="guarantorErrors.external_region" class="mt-1 text-xs text-rose-600" x-text="guarantorErrors.external_region"></p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.district') }} <span class="text-rose-500">*</span></label>
                        <select name="external_district" x-model="form.external_district" @change="delete guarantorErrors.external_district; invalidateExternalInvite()"
                                :class="guarantorErrors.external_district ? 'ring-rose-400' : 'ring-gray-200'"
                                class="w-full rounded-lg border-gray-300 ring-1 px-3 py-2.5 text-sm">
                            <option value="">{{ __('borrower.profile.select_district') }}</option>
                            <template x-for="d in districtsForRegion()" :key="d">
                                <option :value="d" x-text="d"></option>
                            </template>
                        </select>
                        <p x-show="guarantorErrors.external_district" class="mt-1 text-xs text-rose-600" x-text="guarantorErrors.external_district"></p>
                    </div>
                </div>
                <div class="sm:col-span-2" x-show="guarantorInvitePreparing" x-cloak>
                    <div class="rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-3 text-sm text-sky-900">
                        <p class="font-semibold">{{ __('borrower.apply.guarantor_fields.generating_link') }}</p>
                        <p class="mt-1 text-sky-800">{{ __('borrower.apply.guarantor_fields.generating_link_hint') }}</p>
                    </div>
                </div>
                <div class="sm:col-span-2" x-show="guarantorInviteError && !guarantorInvitePreparing" x-cloak>
                    <div class="rounded-xl bg-rose-50 ring-1 ring-rose-200 px-4 py-3 text-sm text-rose-900">
                        <p class="font-semibold">{{ __('borrower.apply.guarantor_fields.invite_failed_title') }}</p>
                        <p class="mt-1" x-text="guarantorInviteError"></p>
                    </div>
                </div>
                <div class="sm:col-span-2" x-show="isExternalGuarantorComplete() && !externalGuarantor?.invitation_url">
                    <button type="button"
                            @click="generateExternalInvite()"
                            :disabled="guarantorInvitePreparing"
                            class="inline-flex bg-brand hover:bg-brand-light disabled:opacity-60 text-white font-semibold px-5 py-2.5 rounded-xl text-sm">
                        <span x-text="guarantorInvitePreparing ? @js(__('borrower.apply.guarantor_fields.generating_link')) : @js(__('borrower.apply.guarantor_fields.generate_link'))"></span>
                    </button>
                </div>
                <div class="sm:col-span-2 rounded-2xl bg-gradient-to-br from-brand-muted/50 to-white ring-1 ring-brand/15 px-5 py-4"
                     x-show="!isExternalGuarantorComplete()">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.guarantor_fields.share_via') }}</p>
                    <p class="text-sm text-gray-700 mt-1">{{ __('borrower.apply.guarantor_fields.share_generate') }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
