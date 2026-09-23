<div x-show="stepKey === 'guarantor' && ! $data.feeGateOpen" class="p-6 sm:p-8">
    <x-site.wizard-step-header
        :title="__('borrower.apply.guarantor')"
        :subtitle="null"
    />

    <div x-show="requiresGuarantor() && !isGuarantorLocked() && !addGuarantorOpen" x-cloak class="mb-5">
        <button type="button"
                @click="addGuarantorOpen = true; if (!form.guarantor_mode || form.guarantor_mode === 'none' || form.guarantor_mode === 'previous') form.guarantor_mode = 'internal'"
                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-2xl bg-brand hover:bg-brand-light text-white font-semibold px-6 py-3.5 text-sm shadow-sm">
            <span class="text-lg leading-none">+</span>
            {{ __('borrower.apply.guarantor_fields.add_cta') }}
        </button>
    </div>

    <div x-show="!requiresGuarantor() && !isGuarantorLocked() && !addGuarantorOpen" x-cloak class="mb-5">
        <button type="button"
                @click="addGuarantorOpen = true; if (!form.guarantor_mode || form.guarantor_mode === 'none' || form.guarantor_mode === 'previous') form.guarantor_mode = 'internal'"
                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-2xl bg-brand hover:bg-brand-light text-white font-semibold px-6 py-3.5 text-sm shadow-sm">
            <span class="text-lg leading-none">+</span>
            {{ __('borrower.apply.guarantor_fields.add_cta') }}
        </button>
    </div>

    {{-- Field-level errors stay inline; summary feedback opens as modal via setGuarantorFieldErrors() --}}

    <div x-show="isGuarantorLocked()" x-cloak class="mb-5">
        <x-site.invitee-card>
            <x-slot:title>
                <p class="text-base font-bold text-gray-900 truncate" x-text="guarantorSummaryText()"></p>
            </x-slot:title>
            <x-slot:meta>
                <span x-text="guarantorMembershipLabel()"></span>
                <span x-show="guarantorRelationshipText() && guarantorRelationshipText() !== '—'" x-cloak>
                    · <span x-text="guarantorRelationshipText()"></span>
                </span>
            </x-slot:meta>
            <x-slot:badges>
                <span class="inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide ring-1"
                      :class="guarantorStatusBadgeClass()"
                      x-text="guarantorStatusLabel()"></span>
                <span x-show="form.guarantor_mode === 'internal' || form.guarantor_mode === 'previous'" x-cloak
                      class="inline-flex rounded-full bg-gray-50 text-gray-700 ring-1 ring-gray-200 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide">
                    {{ __('borrower.apply.group_members.mode_internal') }}
                </span>
                <span x-show="form.guarantor_mode === 'external'" x-cloak
                      class="inline-flex rounded-full bg-gray-50 text-gray-600 ring-1 ring-gray-200 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide">
                    {{ __('borrower.apply.group_members.mode_external') }}
                </span>
            </x-slot:badges>
            <x-slot:details>
                <div class="rounded-xl bg-gray-50/80 ring-1 ring-gray-100 px-3.5 py-3">
                    <dt class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold">{{ __('borrower.profile.fields.phone') }}</dt>
                    <dd class="mt-1 font-semibold text-gray-900 tabular-nums" x-text="guarantorPhoneText()"></dd>
                </div>
                <div class="rounded-xl bg-gray-50/80 ring-1 ring-gray-100 px-3.5 py-3" x-show="form.guarantor_mode === 'external'" x-cloak>
                    <dt class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold">{{ __('borrower.apply.guarantor_fields.relationship') }}</dt>
                    <dd class="mt-1 font-semibold text-gray-900" x-text="guarantorRelationshipText()"></dd>
                </div>
            </x-slot:details>
            <x-slot:actions>
                <button type="button"
                        @click="changeGuarantor()"
                        :disabled="guarantorChanging"
                        class="inline-flex items-center justify-center bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-800 font-semibold px-4 py-2.5 rounded-xl text-sm disabled:opacity-60">
                    {{ __('borrower.apply.change_guarantor') }}
                </button>
                <button type="button"
                        x-show="form.guarantor_mode === 'external' && (externalGuarantor?.invitation_url || externalGuarantor?.short_url || externalGuarantor?.whatsapp_url)"
                        x-cloak
                        @click="openGuarantorShare()"
                        class="inline-flex items-center justify-center bg-brand hover:bg-brand-light text-white font-semibold px-4 py-2.5 rounded-xl text-sm shadow-sm shadow-brand/15">
                    {{ __('borrower.apply.guarantor_fields.share_invitation') }}
                </button>
            </x-slot:actions>
        </x-site.invitee-card>

        <x-site.kopafasta-share-sheet
            :title="__('borrower.apply.guarantor_fields.share_invitation')"
            :hint="null"
            :show-facebook="false"
            open="guarantorShareOpen"
            :whatsapp-label="__('borrower.membership.share_whatsapp')"
            :messages-label="__('borrower.membership.share_messages')"
            :email-label="__('borrower.membership.share_email')"
            :copy-label="__('borrower.membership.share_copy')"
            :copied-label="__('borrower.membership.share_copied_short')"
            :more-label="__('borrower.membership.share_more')"
        />
    </div>

    <div x-show="addGuarantorOpen && !isGuarantorLocked()" x-cloak
         class="rounded-2xl ring-1 ring-brand/15 bg-white p-5 sm:p-6 space-y-5 mb-5">
            <div class="flex items-start justify-between gap-3">
                <h3 class="text-lg font-bold text-gray-900">{{ __('borrower.apply.guarantor_fields.add_cta') }}</h3>
                <button type="button" @click="addGuarantorOpen = false" class="text-gray-400 hover:text-gray-700 text-2xl leading-none px-1" aria-label="{{ __('borrower.profile.cancel') }}">×</button>
            </div>

            <div class="rounded-2xl ring-1 ring-gray-200 p-1.5 flex flex-wrap gap-1 bg-gray-50">
                <button type="button"
                        @click="form.guarantor_mode = 'internal'"
                        class="flex-1 min-w-[9rem] text-center text-sm font-semibold px-3 py-2.5 rounded-xl transition"
                        :class="form.guarantor_mode === 'internal' ? 'bg-brand text-white' : 'text-gray-600 hover:bg-white'">
                    {{ __('borrower.apply.group_members.mode_internal') }}
                </button>
                <button type="button"
                        @click="form.guarantor_mode = 'external'"
                        class="flex-1 min-w-[9rem] text-center text-sm font-semibold px-3 py-2.5 rounded-xl transition"
                        :class="form.guarantor_mode === 'external' ? 'bg-brand text-white' : 'text-gray-600 hover:bg-white'">
                    {{ __('borrower.apply.group_members.mode_external') }}
                </button>
            </div>

            <div x-show="form.guarantor_mode === 'internal'" class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">{{ __('borrower.apply.guarantor_fields.membership_no') }}</label>
                    <div class="flex rounded-xl ring-1 overflow-hidden bg-white" :class="guarantorErrors.internal_member_no ? 'ring-rose-400' : 'ring-gray-200'">
                        <span class="inline-flex items-center px-3 bg-gray-100 text-sm font-mono text-gray-600 border-r border-gray-200">KPF-TZ-</span>
                        <input name="internal_member_no" x-model="form.internal_member_no" @input="delete guarantorErrors.internal_member_no; guarantorLookup.ok = false" placeholder="{{ __('borrower.apply.guarantor_fields.membership_placeholder') }}" autocomplete="off" class="flex-1 border-0 px-3 py-2.5 text-sm font-mono focus:ring-0">
                    </div>
                    <p x-show="guarantorErrors.internal_member_no" class="mt-1 text-xs text-rose-600" x-text="guarantorErrors.internal_member_no"></p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">{{ __('borrower.profile.fields.phone') }}</label>
                    <div class="flex rounded-xl ring-1 overflow-hidden bg-white" :class="guarantorErrors.internal_guarantor_phone ? 'ring-rose-400' : 'ring-gray-200'">
                        <span class="inline-flex items-center px-3 bg-gray-100 text-sm text-gray-600 border-r border-gray-200">+255</span>
                        <input name="internal_guarantor_phone" x-model="form.internal_guarantor_phone" data-digits-only inputmode="numeric" pattern="[0-9]*" placeholder="{{ __('borrower.apply.guarantor_fields.phone_placeholder') }}" autocomplete="off"
                               @input="form.internal_guarantor_phone = String(form.internal_guarantor_phone || '').replace(/\D/g, ''); delete guarantorErrors.internal_guarantor_phone; guarantorLookup.ok = false"
                               class="flex-1 border-0 px-3 py-2.5 text-sm focus:ring-0">
                    </div>
                    <p x-show="guarantorErrors.internal_guarantor_phone" class="mt-1 text-xs text-rose-600" x-text="guarantorErrors.internal_guarantor_phone"></p>
                </div>
                <input type="hidden" name="internal_guarantor_name" :value="form.internal_guarantor_name || guarantorLookup.label || ''">
                <div x-show="guarantorValidating" x-cloak class="rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-3 text-sm text-sky-900">
                    <p class="font-semibold">{{ __('borrower.apply.guarantor_fields.validating') }}</p>
                    <p class="mt-1 text-sky-800">{{ __('borrower.apply.guarantor_fields.validating_hint') }}</p>
                </div>
                <p class="text-xs text-gray-500">{{ __('borrower.apply.guarantor_fields.membership_hint_short') }}</p>
                <button type="button"
                        @click="validateInternalGuarantor()"
                        :disabled="guarantorValidating"
                        class="w-full inline-flex justify-center bg-brand hover:bg-brand-light disabled:opacity-60 text-white font-semibold px-5 py-3 rounded-xl text-sm">
                    <span x-text="guarantorValidating ? @js(__('borrower.apply.guarantor_fields.validating')) : @js(__('borrower.apply.guarantor_fields.validate'))"></span>
                </button>
            </div>

            <input type="hidden" name="external_invitation_id" :value="externalGuarantor?.invitation_id || ''">
            <div x-show="form.guarantor_mode === 'external'" class="grid sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.first_name') }} <span class="text-rose-500">*</span></label>
                    <input name="external_first_name" x-model="form.external_first_name" @input="delete guarantorErrors.external_first_name; invalidateExternalInvite()"
                           :class="guarantorErrors.external_first_name ? 'ring-rose-400' : 'ring-gray-200'"
                           class="w-full rounded-xl border-gray-300 ring-1 px-3 py-2.5 text-sm bg-white" placeholder="{{ __('borrower.apply.guarantor_fields.first_name_placeholder') }}" autocomplete="off">
                    <p x-show="guarantorErrors.external_first_name" class="mt-1 text-xs text-rose-600" x-text="guarantorErrors.external_first_name"></p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.middle_name') }}</label>
                    <input name="external_middle_name" x-model="form.external_middle_name" class="w-full rounded-xl border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm bg-white" placeholder="{{ __('borrower.apply.guarantor_fields.middle_name_placeholder') }}" autocomplete="off">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.last_name') }} <span class="text-rose-500">*</span></label>
                    <input name="external_last_name" x-model="form.external_last_name" @input="delete guarantorErrors.external_last_name; invalidateExternalInvite()"
                           :class="guarantorErrors.external_last_name ? 'ring-rose-400' : 'ring-gray-200'"
                           class="w-full rounded-xl border-gray-300 ring-1 px-3 py-2.5 text-sm bg-white" placeholder="{{ __('borrower.apply.guarantor_fields.last_name_placeholder') }}" autocomplete="off">
                    <p x-show="guarantorErrors.external_last_name" class="mt-1 text-xs text-rose-600" x-text="guarantorErrors.external_last_name"></p>
                </div>
                <div x-data="{
                    pickerOpen: false,
                    options: @js(trans('borrower.profile.guarantor_relationship_options')),
                    labelFor(val) {
                        if (!val) return @js(__('borrower.profile.select'));
                        return this.options[val] || val;
                    },
                    pick(val) {
                        form.external_relationship = val;
                        delete guarantorErrors.external_relationship;
                        invalidateExternalInvite();
                        this.pickerOpen = false;
                    }
                }">
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.apply.guarantor_fields.relationship') }} <span class="text-rose-500">*</span></label>
                    <div class="lg:hidden">
                        <button type="button" @click="pickerOpen = true"
                                class="w-full inline-flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-800"
                                :class="guarantorErrors.external_relationship ? 'border-rose-400' : ''">
                            <span class="flex-1 text-left truncate" x-text="labelFor(form.external_relationship)"></span>
                            <svg class="w-4 h-4 text-gray-400 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                        </button>
                        <x-site.bottom-sheet :title="__('borrower.apply.guarantor_fields.relationship')" open="pickerOpen">
                            <div class="space-y-1 max-h-[60vh] overflow-y-auto">
                                <template x-for="(label, key) in options" :key="key">
                                    <button type="button" @click="pick(key)"
                                            class="w-full text-left px-4 py-3 rounded-xl text-sm font-medium text-gray-800 hover:bg-gray-50"
                                            :class="form.external_relationship === key ? 'bg-brand-muted text-brand ring-1 ring-brand/20' : ''"
                                            x-text="label"></button>
                                </template>
                            </div>
                        </x-site.bottom-sheet>
                    </div>
                    <select name="external_relationship" x-model="form.external_relationship" @change="delete guarantorErrors.external_relationship; invalidateExternalInvite()"
                            :class="guarantorErrors.external_relationship ? 'ring-rose-400' : 'ring-gray-200'"
                            class="w-full rounded-xl border-gray-300 ring-1 px-3 py-2.5 text-sm bg-white max-lg:absolute max-lg:opacity-0 max-lg:pointer-events-none max-lg:h-0 max-lg:overflow-hidden">
                        <option value="">{{ __('borrower.profile.select') }}</option>
                        @foreach (trans('borrower.profile.guarantor_relationship_options') as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <p x-show="guarantorErrors.external_relationship" class="mt-1 text-xs text-rose-600" x-text="guarantorErrors.external_relationship"></p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.phone') }} <span class="text-rose-500">*</span></label>
                    <div class="flex rounded-xl ring-1 overflow-hidden bg-white" :class="guarantorErrors.external_phone ? 'ring-rose-400' : 'ring-gray-200'">
                        <span class="inline-flex items-center px-3 bg-gray-100 text-sm text-gray-600 border-r border-gray-200">+255</span>
                        <input name="external_phone" x-model="form.external_phone" data-digits-only inputmode="numeric" pattern="[0-9]*" placeholder="{{ __('borrower.apply.guarantor_fields.phone_placeholder') }}" autocomplete="off"
                               @input="form.external_phone = String(form.external_phone || '').replace(/\D/g, ''); delete guarantorErrors.external_phone; invalidateExternalInvite()"
                               class="flex-1 border-0 px-3 py-2.5 text-sm focus:ring-0">
                    </div>
                    <p x-show="guarantorErrors.external_phone" class="mt-1 text-xs text-rose-600" x-text="guarantorErrors.external_phone"></p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.email') }} {{ __('borrower.profile.optional') }}</label>
                    <input name="external_email" x-model="form.external_email" type="email" class="w-full rounded-xl border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm bg-white" placeholder="{{ __('borrower.apply.guarantor_fields.email_placeholder') }}" autocomplete="off">
                </div>
                <div class="sm:col-span-2 grid sm:grid-cols-2 gap-3">
                    <div x-data="{
                        pickerOpen: false,
                        options: @js(array_keys(location_tree('TZ'))),
                        pick(val) {
                            form.external_region = val;
                            onExternalRegionChange();
                            delete guarantorErrors.external_region;
                            invalidateExternalInvite();
                            this.pickerOpen = false;
                        }
                    }">
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.region') }} <span class="text-rose-500">*</span></label>
                        <div class="lg:hidden">
                            <button type="button" @click="pickerOpen = true"
                                    class="w-full inline-flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-800"
                                    :class="guarantorErrors.external_region ? 'border-rose-400' : ''">
                                <span class="flex-1 text-left truncate" x-text="form.external_region || @js(__('borrower.profile.select_region'))"></span>
                                <svg class="w-4 h-4 text-gray-400 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                            </button>
                            <x-site.bottom-sheet :title="__('borrower.profile.fields.region')" open="pickerOpen">
                                <div class="space-y-1 max-h-[60vh] overflow-y-auto">
                                    <template x-for="region in options" :key="region">
                                        <button type="button" @click="pick(region)"
                                                class="w-full text-left px-4 py-3 rounded-xl text-sm font-medium text-gray-800 hover:bg-gray-50"
                                                :class="form.external_region === region ? 'bg-brand-muted text-brand ring-1 ring-brand/20' : ''"
                                                x-text="region"></button>
                                    </template>
                                </div>
                            </x-site.bottom-sheet>
                        </div>
                        <select name="external_region" x-model="form.external_region" @change="onExternalRegionChange(); delete guarantorErrors.external_region; invalidateExternalInvite()"
                                :class="guarantorErrors.external_region ? 'ring-rose-400' : 'ring-gray-200'"
                                class="w-full rounded-xl border-gray-300 ring-1 px-3 py-2.5 text-sm bg-white max-lg:absolute max-lg:opacity-0 max-lg:pointer-events-none max-lg:h-0 max-lg:overflow-hidden">
                            <option value="">{{ __('borrower.profile.select_region') }}</option>
                            @foreach (location_tree('TZ') as $regionName => $districts)
                                <option value="{{ $regionName }}">{{ $regionName }}</option>
                            @endforeach
                        </select>
                        <p x-show="guarantorErrors.external_region" class="mt-1 text-xs text-rose-600" x-text="guarantorErrors.external_region"></p>
                    </div>
                    <div x-data="{
                        pickerOpen: false,
                        pick(val) {
                            form.external_district = val;
                            delete guarantorErrors.external_district;
                            invalidateExternalInvite();
                            this.pickerOpen = false;
                        }
                    }">
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.district') }} <span class="text-rose-500">*</span></label>
                        <div class="lg:hidden">
                            <button type="button" @click="pickerOpen = true" :disabled="!form.external_region || externalDistrictStatus === 'loading'"
                                    class="w-full inline-flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-800 disabled:opacity-50"
                                    :class="guarantorErrors.external_district ? 'border-rose-400' : ''">
                                <span class="flex-1 text-left truncate" x-text="form.external_district || (externalDistrictStatus === 'loading' ? @js(__('borrower.profile.loading_districts')) : @js(__('borrower.profile.select_district')))"></span>
                                <svg class="w-4 h-4 text-gray-400 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                            </button>
                            <x-site.bottom-sheet :title="__('borrower.profile.fields.district')" open="pickerOpen">
                                <div class="space-y-1 max-h-[60vh] overflow-y-auto">
                                    <p x-show="externalDistrictStatus === 'loading'" class="px-1 py-3 text-sm text-gray-500">{{ __('borrower.profile.loading_districts') }}</p>
                                    <div x-show="form.external_region && (externalDistrictStatus === 'empty' || externalDistrictStatus === 'error')" class="px-1 py-3 space-y-2">
                                        <p class="text-sm text-rose-600">{{ __('borrower.profile.districts_unavailable') }}</p>
                                        <button type="button" class="text-sm font-semibold text-brand underline" @click="refreshExternalDistricts(true)">{{ __('borrower.profile.retry_districts') }}</button>
                                    </div>
                                    <template x-for="d in externalDistrictOptions" :key="d">
                                        <button type="button" @click="pick(d)"
                                                class="w-full text-left px-4 py-3 rounded-xl text-sm font-medium text-gray-800 hover:bg-gray-50"
                                                :class="form.external_district === d ? 'bg-brand-muted text-brand ring-1 ring-brand/20' : ''"
                                                x-text="d"></button>
                                    </template>
                                </div>
                            </x-site.bottom-sheet>
                        </div>
                        <select name="external_district" x-model="form.external_district"
                                :key="'external-district-' + (form.external_region || '')"
                                :disabled="!form.external_region || externalDistrictStatus === 'loading'"
                                @change="delete guarantorErrors.external_district; invalidateExternalInvite()"
                                :class="guarantorErrors.external_district ? 'ring-rose-400' : 'ring-gray-200'"
                                class="w-full rounded-xl border-gray-300 ring-1 px-3 py-2.5 text-sm bg-white max-lg:absolute max-lg:opacity-0 max-lg:pointer-events-none max-lg:h-0 max-lg:overflow-hidden">
                            <option value="" x-text="externalDistrictStatus === 'loading' ? @js(__('borrower.profile.loading_districts')) : @js(__('borrower.profile.select_district'))"></option>
                            <template x-for="d in externalDistrictOptions" :key="d">
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
                <div class="sm:col-span-2" x-show="form.guarantor_mode === 'external' && (!externalGuarantor || !externalGuarantor.invitation_url)">
                    <button type="button"
                            @click="generateExternalInvite()"
                            :disabled="guarantorInvitePreparing || !isExternalGuarantorComplete()"
                            class="w-full inline-flex justify-center bg-brand hover:bg-brand-light disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold px-5 py-3.5 rounded-xl text-sm shadow-sm">
                        <span x-text="guarantorInvitePreparing ? @js(__('borrower.apply.guarantor_fields.generating_link')) : @js(__('borrower.apply.guarantor_fields.generate_link'))"></span>
                    </button>
                    <p class="mt-2 text-xs text-gray-500" x-show="!isExternalGuarantorComplete()" x-cloak>
                        {{ __('borrower.apply.guarantor_fields.complete_fields_first') }}
                    </p>
                </div>
            </div>
    </div>
</div>
