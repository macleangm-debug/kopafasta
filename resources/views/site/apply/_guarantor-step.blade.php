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
        <ol x-show="guarantorProgressSteps().length" x-cloak class="grid sm:grid-cols-4 gap-2">
            <template x-for="step in guarantorProgressSteps()" :key="'g-' + step.key">
                <li class="rounded-xl bg-white/80 ring-1 px-3 py-2"
                    :class="step.complete ? 'ring-emerald-200' : (step.current ? 'ring-amber-300' : 'ring-gray-200')">
                    <p class="text-[10px] font-semibold"
                       :class="step.complete ? 'text-emerald-700' : (step.current ? 'text-amber-800' : 'text-gray-400')"
                       x-text="step.complete ? '✓' : (step.current ? '·' : '○')"></p>
                    <p class="text-xs font-semibold mt-0.5 text-gray-700" x-text="step.label"></p>
                </li>
            </template>
        </ol>
        <div x-show="form.guarantor_mode === 'external' && externalGuarantor?.invitation_url" x-cloak x-data="{ copied: false }"
             class="rounded-2xl bg-gradient-to-br from-brand via-brand to-brand-light text-white px-5 py-5 space-y-4 shadow-sm">
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
        </div>
        <div x-show="(form.guarantor_mode === 'internal' || form.guarantor_mode === 'previous') && internalGuarantor?.invitation_id" x-cloak
             class="rounded-2xl bg-gradient-to-br from-brand via-brand to-brand-light text-white px-5 py-4 shadow-sm">
            <p class="text-sm font-bold">{{ __('borrower.apply.guarantor_fields.member_notified_title') }}</p>
            <p class="mt-1 text-sm text-white/85 leading-relaxed">{{ __('borrower.apply.guarantor_fields.member_notified_body') }}</p>
        </div>
        <button type="button"
                @click="changeGuarantor()"
                :disabled="guarantorChanging"
                class="inline-flex bg-white ring-1 ring-brand/20 hover:bg-brand-muted/40 text-brand font-semibold px-4 py-2.5 rounded-xl text-sm disabled:opacity-60">
            {{ __('borrower.apply.change_guarantor') }}
        </button>
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
                        options: @js(array_keys(config('tanzania_locations'))),
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
                            @foreach (config('tanzania_locations') as $regionName => $districts)
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
                            <button type="button" @click="pickerOpen = true"
                                    class="w-full inline-flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-800"
                                    :class="guarantorErrors.external_district ? 'border-rose-400' : ''">
                                <span class="flex-1 text-left truncate" x-text="form.external_district || @js(__('borrower.profile.select_district'))"></span>
                                <svg class="w-4 h-4 text-gray-400 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                            </button>
                            <x-site.bottom-sheet :title="__('borrower.profile.fields.district')" open="pickerOpen">
                                <div class="space-y-1 max-h-[60vh] overflow-y-auto">
                                    <template x-for="d in districtsForRegion()" :key="d">
                                        <button type="button" @click="pick(d)"
                                                class="w-full text-left px-4 py-3 rounded-xl text-sm font-medium text-gray-800 hover:bg-gray-50"
                                                :class="form.external_district === d ? 'bg-brand-muted text-brand ring-1 ring-brand/20' : ''"
                                                x-text="d"></button>
                                    </template>
                                </div>
                            </x-site.bottom-sheet>
                        </div>
                        <select name="external_district" x-model="form.external_district" @change="delete guarantorErrors.external_district; invalidateExternalInvite()"
                                :class="guarantorErrors.external_district ? 'ring-rose-400' : 'ring-gray-200'"
                                class="w-full rounded-xl border-gray-300 ring-1 px-3 py-2.5 text-sm bg-white max-lg:absolute max-lg:opacity-0 max-lg:pointer-events-none max-lg:h-0 max-lg:overflow-hidden">
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
