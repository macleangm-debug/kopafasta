{{-- Group setup (pre-spine): name, size, and purpose. Amount / tenure live on the quote spine step. --}}
<div x-show="stepKey === 'group_setup' && ! $data.feeGateOpen" class="p-6 sm:p-8" data-wizard-step="group_setup">
    <x-site.wizard-step-header
        :eyebrow="__('borrower.apply.steps.group_setup')"
        :title="__('borrower.apply.group_setup.title')"
        :subtitle="__('borrower.apply.group_setup.subtitle')"
    />

    <template x-if="current">
        <div class="space-y-5">
            <div class="rounded-3xl bg-gradient-to-br from-brand via-brand to-brand-light text-white px-5 py-5 sm:px-6 sm:py-6 shadow-[0_18px_40px_rgba(0,77,64,0.18)]">
                <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-brand-gold">{{ __('borrower.apply.group.loan_label') }}</p>
                <p class="mt-2 text-lg sm:text-xl font-extrabold tracking-tight">{{ __('borrower.apply.group_setup.setup_hero') }}</p>
                <p class="mt-2 text-sm text-white/75 max-w-xl">{{ __('borrower.apply.group_setup.setup_hero_hint') }}</p>
            </div>

            <div class="rounded-3xl bg-white ring-1 ring-brand/12 p-5 sm:p-6 space-y-6 shadow-sm">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">{{ __('borrower.apply.group_setup.name') }}</label>
                    <input type="text" x-model="group.name" maxlength="150"
                           placeholder="{{ __('borrower.apply.group_setup.name_placeholder') }}"
                           @input="scheduleDraftSave()"
                           class="w-full rounded-xl border-0 ring-1 ring-gray-200 px-4 py-3 text-sm focus:ring-2 focus:ring-brand">
                </div>

                <div>
                    <div class="flex items-end justify-between gap-3 mb-3">
                        <label class="text-sm font-semibold text-gray-700">{{ __('borrower.apply.group_setup.member_count') }}</label>
                        <span class="text-lg font-extrabold text-brand tabular-nums" x-text="group.target_member_count || groupLimits.min"></span>
                    </div>
                    <input type="range"
                           x-model.number="group.target_member_count"
                           :min="groupLimits.min" :max="groupLimits.max" step="1"
                           @change="refreshApplicationFeeQuote(); scheduleDraftSave()"
                           class="w-full accent-brand h-2 rounded-full">
                    <div class="flex justify-between text-xs text-gray-500 mt-2 tabular-nums">
                        <span x-text="groupLimits.min"></span>
                        <span x-text="groupLimits.max"></span>
                    </div>
                </div>

                <div class="rounded-2xl bg-brand-muted/40 ring-1 ring-brand/15 p-4 sm:p-5">
                    <div x-show="group.purpose && !purposeEditing && !purposeNeedsDetail()" x-cloak class="space-y-2">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-gray-700">{{ __('borrower.apply.group_setup.purpose') }}</p>
                            <button type="button" @click="purposeEditing = true"
                                    class="text-xs font-semibold text-brand hover:underline shrink-0">
                                {{ __('borrower.apply.quote.change_purpose') }}
                            </button>
                        </div>
                        <p class="text-base font-bold text-gray-900" x-text="purposeLabels[group.purpose] || group.purpose"></p>
                        <p x-show="isOtherPurpose(group.purpose) && form.purpose_other" class="text-sm text-gray-600" x-text="form.purpose_other"></p>
                    </div>
                    <div x-show="!group.purpose || purposeEditing || purposeNeedsDetail()" x-cloak>
                        <x-site.sheet-select
                            model="group.purpose"
                            setter="setGroupPurpose"
                            :label="__('borrower.apply.group_setup.purpose')"
                            :options="$loanPurposes"
                            :required="true"
                            :placeholder="__('borrower.apply.quote.select_purpose')"
                        />
                        <p class="mt-2 text-xs text-brand/80">{{ __('borrower.apply.group_setup.purpose_hint') }}</p>
                        <div x-show="isOtherPurpose(group.purpose)" x-cloak class="mt-4">
                            <label class="block text-sm font-semibold text-gray-800 mb-1.5">{{ __('borrower.apply.quote.purpose_other_label') }} <span class="text-red-500">*</span></label>
                            <input type="text"
                                   x-model="form.purpose_other"
                                   @input="syncPurposeHidden(); scheduleDraftSave()"
                                   maxlength="120"
                                   class="kf-field"
                                   :required="isOtherPurpose(group.purpose)"
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
        </div>
    </template>
</div>

<div x-show="stepKey === 'group_members' && ! $data.feeGateOpen" class="p-6 sm:p-8">
    <div class="mb-6">
        <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-1">{{ __('borrower.apply.steps.group_members') }}</p>
        <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-900"
            x-text="group.name || @js(__('borrower.apply.group_members.title'))"></h2>
        <p class="mt-1 text-sm text-gray-600">{{ __('borrower.apply.group_members.subtitle') }}</p>
    </div>

    <div class="rounded-3xl overflow-hidden ring-1 ring-brand/12 bg-white shadow-sm mb-6">
        <div class="bg-gradient-to-br from-brand via-brand to-brand-light text-white px-5 py-5 sm:px-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-brand-gold">{{ __('borrower.apply.group_members.your_team') }}</p>
                    <p class="mt-1 text-xl font-extrabold truncate" x-text="group.name || @js(__('borrower.apply.group.loan_label'))"></p>
                    <p class="mt-1 text-sm text-white/75"
                       x-show="group.purpose"
                       x-text="purposeLabels[group.purpose] || group.purpose"></p>
                </div>
                <div class="rounded-2xl bg-white/10 ring-1 ring-white/15 px-4 py-3 text-center shrink-0">
                    <p class="text-2xl font-extrabold tabular-nums" x-text="groupProgress().added + '/' + groupProgress().target"></p>
                    <p class="text-[10px] uppercase tracking-widest text-white/70 mt-1">{{ __('borrower.apply.group.progress.added_label') }}</p>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex items-center justify-between gap-3 text-xs text-white/80 mb-1.5">
                    <span>{{ __('borrower.apply.group.progress.avg_completion_label') }}</span>
                    <span class="font-bold tabular-nums" x-text="(groupProgress().avg_profile_percent ?? 0) + '%'"></span>
                </div>
                <div class="h-2 rounded-full bg-white/15 overflow-hidden">
                    <div class="h-full rounded-full bg-brand-gold transition-all duration-500"
                         :style="'width:' + Math.max(0, Math.min(100, groupProgress().avg_profile_percent ?? 0)) + '%'"></div>
                </div>
            </div>
        </div>
        <div class="grid grid-cols-2 divide-x divide-brand/10">
            <div class="px-3 py-3.5 text-center">
                <p class="text-lg font-extrabold text-brand tabular-nums" x-text="(groupProgress().profiles_complete ?? 0) + '/' + groupProgress().target"></p>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 mt-1">{{ __('borrower.apply.group.progress.profiles_label') }}</p>
            </div>
            <div class="px-3 py-3.5 text-center">
                <p class="text-lg font-extrabold text-amber-600 tabular-nums" x-text="groupProgress().invitations_pending ?? 0"></p>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 mt-1">{{ __('borrower.apply.group.progress.pending_label') }}</p>
            </div>
        </div>
    </div>

    <div class="mb-6">
        <div class="flex flex-wrap items-end justify-between gap-2 mb-3">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.group_members.your_team') }}</p>
                <p class="text-xs text-gray-500 mt-0.5">{{ __('borrower.apply.group_members.readiness_hint') }}</p>
            </div>
        </div>
        <div class="space-y-3">
            <template x-for="(member, index) in (group.members || [])" :key="'gm-' + index + '-c' + (member.customer_id || 0) + '-i' + (member.invitation_id || 0)">
                <div class="rounded-3xl overflow-hidden ring-1 bg-white shadow-sm transition"
                     :class="member.role === 'leader' ? 'ring-brand/25' : 'ring-gray-200/90'">
                    <button type="button"
                            class="w-full text-left p-4 sm:p-5"
                            :class="member.role === 'leader' ? 'bg-gradient-to-r from-brand-muted/50 to-white' : 'bg-white'"
                            @click="member.role !== 'leader' && (member._open = !member._open)">
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="size-12 rounded-2xl bg-brand text-white grid place-items-center text-base font-bold shrink-0 shadow-sm shadow-brand/20 overflow-hidden">
                                <img x-show="member.avatar_url" x-cloak :src="member.avatar_url" :alt="member.name || ''" class="size-full object-cover">
                                <span x-show="!member.avatar_url" x-text="(member.name || '?').trim().charAt(0).toUpperCase()"></span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-bold text-sm sm:text-base text-gray-900 truncate" x-text="member.name"></p>
                                    <span x-show="member.role === 'leader'"
                                          class="inline-flex items-center rounded-full bg-brand text-white text-[10px] font-bold uppercase tracking-wider px-2 py-0.5">
                                        {{ __('borrower.apply.group_members.leader_badge') }}
                                    </span>
                                    <span x-show="member.role !== 'leader'"
                                          class="inline-flex items-center rounded-full bg-brand-muted text-brand ring-1 ring-brand/15 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5">
                                        {{ __('borrower.apply.group_members.member_badge') }}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-500 mt-0.5" x-text="member.phone"></p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-xs font-semibold" :class="memberStatusClass(member)" x-text="memberStatusLabel(member)"></p>
                                <p class="text-sm font-extrabold tabular-nums text-gray-900 mt-0.5" x-text="formatTzs(member.requested_amount)"></p>
                            </div>
                            <svg x-show="member.role !== 'leader'" class="w-4 h-4 text-gray-400 shrink-0 transition" :class="member._open && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                        </div>
                        <div class="mt-3 pl-0 sm:pl-15">
                            <div class="flex items-center justify-between gap-2 text-[11px] text-gray-500 mb-1">
                                <span>{{ __('borrower.apply.group.profile_completion') }}</span>
                                <span class="font-bold tabular-nums text-brand" x-text="(member.profile_percent ?? 0) + '%'"></span>
                            </div>
                            <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-500"
                                     :class="(member.profile_percent ?? 0) >= 100 ? 'bg-emerald-500' : 'bg-brand'"
                                     :style="'width:' + Math.max(0, Math.min(100, member.profile_percent ?? 0)) + '%'"></div>
                            </div>
                        </div>
                    </button>
                    <div x-show="member._open && member.role !== 'leader'" x-cloak class="px-4 sm:px-5 pb-5 pt-4 border-t border-gray-100 space-y-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div x-show="member.share?.short_url || member.share?.invitation_url || member.share?.whatsapp_url"
                                 x-cloak x-data="{ inviteOpen: false, copied: false }" class="space-y-3 min-w-0 flex-1">
                                <button type="button" @click="inviteOpen = !inviteOpen"
                                        class="inline-flex items-center gap-2 bg-brand hover:bg-brand-light text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                                    {{ __('borrower.apply.group_members.invite_again') }}
                                    <svg class="w-3.5 h-3.5 transition" :class="inviteOpen && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                                </button>
                                <div x-show="inviteOpen" x-cloak
                                     class="rounded-2xl bg-gradient-to-br from-brand via-brand to-brand-light text-white px-5 py-5 space-y-4 shadow-sm">
                                    <div>
                                        <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.apply.guarantor_fields.share_via') }}</p>
                                        <p class="text-sm text-white/90 mt-1">{{ __('borrower.apply.guarantor_fields.share_ready') }}</p>
                                    </div>
                                    <p class="text-xs font-mono text-brand bg-brand-gold/90 rounded-xl px-3 py-2.5 break-all"
                                       x-text="member.share?.short_url || member.share?.invitation_url"></p>
                                    <div class="flex flex-wrap gap-2">
                                        <a :href="member.share?.whatsapp_url || '#'" :class="!member.share?.whatsapp_url && 'pointer-events-none opacity-50'" target="_blank" rel="noopener"
                                           class="inline-flex items-center gap-2 bg-emerald-500 hover:bg-emerald-400 text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                                            {{ __('borrower.apply.guarantor_fields.share_whatsapp') }}
                                        </a>
                                        <a :href="member.share?.sms_url || '#'" :class="!member.share?.sms_url && 'pointer-events-none opacity-50'"
                                           class="inline-flex items-center gap-2 bg-white/15 hover:bg-white/25 ring-1 ring-white/30 text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                                            {{ __('borrower.apply.guarantor_fields.share_sms') }}
                                        </a>
                                        <a :href="member.share?.email_url || '#'" :class="!member.share?.email_url && 'pointer-events-none opacity-50'"
                                           class="inline-flex items-center gap-2 bg-white/15 hover:bg-white/25 ring-1 ring-white/30 text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                                            {{ __('borrower.apply.guarantor_fields.share_email') }}
                                        </a>
                                        <button type="button"
                                                @click="navigator.clipboard.writeText(member.share?.short_url || member.share?.invitation_url || ''); copied = true; setTimeout(() => copied = false, 2000)"
                                                class="inline-flex items-center gap-2 bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-4 py-2.5 rounded-xl text-sm">
                                            <span x-text="copied ? @js(__('borrower.apply.guarantor_fields.link_copied')) : @js(__('borrower.apply.guarantor_fields.share_copy'))"></span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <button type="button" @click="removeGroupMember(index)"
                                    class="inline-flex items-center gap-2 bg-white ring-1 ring-red-200 hover:bg-red-50 text-red-700 font-semibold px-4 py-2.5 rounded-xl text-sm shrink-0 ml-auto">
                                {{ __('borrower.apply.group_members.remove') }}
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <div x-show="(group.members || []).length < groupTargetCount() && !addMemberOpen" class="mb-5">
        <button type="button" @click="openAddMemberPanel()"
                class="w-full inline-flex items-center justify-center gap-2 rounded-2xl bg-brand hover:bg-brand-light text-white font-semibold px-6 py-4 text-sm shadow-sm shadow-brand/20">
            <span class="size-7 rounded-full bg-white/15 grid place-items-center text-lg leading-none">+</span>
            {{ __('borrower.apply.group_members.add_cta') }}
            <span class="opacity-80 font-normal"
                  x-text="'(' + Math.max(0, groupTargetCount() - group.members.length) + ')'"></span>
        </button>
        <p class="text-xs text-gray-500 mt-2 text-center sm:text-left"
           x-text="@js(__('borrower.apply.group_members.add_more_hint')).replace(':remaining', Math.max(0, groupTargetCount() - group.members.length)).replace(':target', groupTargetCount())"></p>
    </div>

    <div x-show="addMemberOpen && group.members.length < groupTargetCount()" x-cloak
         class="rounded-3xl ring-1 ring-brand/15 bg-white p-5 sm:p-6 space-y-5 mb-5 shadow-sm">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.group_members.add_more_title') }}</p>
                <h3 class="text-lg font-bold text-gray-900 mt-0.5">{{ __('borrower.apply.group_members.add_cta') }}</h3>
            </div>
            <button type="button" @click="closeAddMemberPanel()" class="text-gray-400 hover:text-gray-700 text-2xl leading-none px-1" aria-label="{{ __('borrower.profile.cancel') }}">×</button>
        </div>

        <div class="rounded-2xl ring-1 ring-gray-200 p-1.5 flex flex-wrap gap-1 bg-gray-50">
            <button type="button" @click="groupMemberMode = 'internal'; groupLookupError = ''; groupMemberLookup = { ok: false, label: '', error: '', data: null }"
                    class="flex-1 min-w-[9rem] text-center text-sm font-semibold px-3 py-2.5 rounded-xl transition"
                    :class="groupMemberMode === 'internal' ? 'bg-brand text-white' : 'text-gray-600 hover:bg-white'">
                {{ __('borrower.apply.group_members.mode_internal') }}
            </button>
            <button type="button" @click="groupMemberMode = 'external'; groupLookupError = ''; groupExternalInvite = null"
                    class="flex-1 min-w-[9rem] text-center text-sm font-semibold px-3 py-2.5 rounded-xl transition"
                    :class="groupMemberMode === 'external' ? 'bg-brand text-white' : 'text-gray-600 hover:bg-white'">
                {{ __('borrower.apply.group_members.mode_external') }}
            </button>
        </div>

        <div x-show="groupMemberMode === 'internal'" class="space-y-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">{{ __('borrower.apply.group_members.lookup_membership') }}</label>
                <div class="flex rounded-xl ring-1 ring-gray-200 overflow-hidden bg-white">
                    <span class="inline-flex items-center px-3 bg-gray-100 text-sm font-mono text-gray-600 border-r border-gray-200">KPF-TZ-</span>
                    <input type="text" x-model="groupLookupMemberNo" placeholder="ABC12345"
                           @input="groupMemberLookup = { ok: false, label: '', error: '', data: null }"
                           class="flex-1 border-0 px-3 py-2.5 text-sm font-mono focus:ring-0">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">{{ __('borrower.apply.group_members.lookup_phone') }}</label>
                <div class="flex rounded-xl ring-1 ring-gray-200 overflow-hidden bg-white">
                    <span class="inline-flex items-center px-3 bg-gray-100 text-sm text-gray-600 border-r border-gray-200">+255</span>
                    <input type="tel" x-model="groupLookupPhone" inputmode="numeric" pattern="[0-9]*" data-digits-only placeholder="712345678"
                           @input="groupLookupPhone = String(groupLookupPhone || '').replace(/\D/g, ''); groupMemberLookup = { ok: false, label: '', error: '', data: null }"
                           class="flex-1 border-0 px-3 py-2.5 text-sm focus:ring-0">
                </div>
            </div>
            <div x-show="groupMemberLookup.ok" x-cloak class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-900">
                <p class="font-semibold">{{ __('borrower.apply.alerts.guarantor_verified') }}</p>
                <p class="mt-1" x-text="groupMemberLookup.label"></p>
            </div>
            <div x-show="groupMemberLookup.error && !groupMemberLookup.ok" x-cloak class="rounded-xl bg-rose-50 ring-1 ring-rose-200 px-4 py-3 text-sm text-rose-900">
                <p x-text="groupMemberLookup.error"></p>
            </div>
            <p class="text-xs text-gray-500">{{ __('borrower.apply.group_members.lookup_hint') }}</p>
            <p class="text-xs text-brand">{{ __('borrower.apply.group_members.internal_consent_hint') }}</p>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="validateGroupMember()" :disabled="groupLookupLoading"
                        class="inline-flex rounded-xl bg-brand hover:bg-brand-light text-white font-semibold px-5 py-2.5 text-sm disabled:opacity-50">
                    <span x-text="groupLookupLoading ? @js(__('borrower.apply.guarantor_fields.validating')) : @js(__('borrower.apply.guarantor_fields.validate'))"></span>
                </button>
                <button type="button" x-show="groupMemberLookup.ok" x-cloak @click="confirmAddValidatedGroupMember()"
                        class="inline-flex rounded-xl bg-brand-gold hover:brightness-95 text-brand font-bold px-5 py-2.5 text-sm">
                    {{ __('borrower.apply.group_members.add_member') }}
                </button>
            </div>
        </div>

        <div x-show="groupMemberMode === 'external'" class="space-y-4">
            <div class="grid sm:grid-cols-2 gap-3">
                <input type="text" x-model="groupExternal.first_name" placeholder="{{ __('borrower.profile.fields.first_name') }}"
                       class="rounded-xl border-gray-300 ring-1 ring-gray-200 px-4 py-3 text-sm focus:ring-brand bg-white">
                <input type="text" x-model="groupExternal.last_name" placeholder="{{ __('borrower.profile.fields.last_name') }}"
                       class="rounded-xl border-gray-300 ring-1 ring-gray-200 px-4 py-3 text-sm focus:ring-brand bg-white">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">{{ __('borrower.profile.fields.phone') }}</label>
                <div class="flex rounded-xl ring-1 ring-gray-200 overflow-hidden bg-white">
                    <span class="inline-flex items-center px-3 bg-gray-100 text-sm text-gray-600 border-r border-gray-200">+255</span>
                    <input type="tel" x-model="groupExternal.phone" inputmode="numeric" pattern="[0-9]*" data-digits-only placeholder="712345678"
                           @input="groupExternal.phone = String(groupExternal.phone || '').replace(/\D/g, '')"
                           class="flex-1 border-0 px-3 py-2.5 text-sm focus:ring-0">
                </div>
                <p class="mt-2 text-xs text-gray-500">{{ __('borrower.apply.group_members.external_phone_hint') }}</p>
            </div>
            <button type="button" @click="inviteExternalGroupMember()" :disabled="groupInviteLoading"
                    class="w-full rounded-xl bg-brand hover:bg-brand-light text-white font-semibold px-4 py-3 text-sm disabled:opacity-50">
                <span x-text="groupInviteLoading ? @js(__('borrower.apply.guarantor_fields.generating_link')) : @js(__('borrower.apply.guarantor_fields.generate_link'))"></span>
            </button>
        </div>

        <p x-show="groupLookupError" x-cloak class="text-sm text-red-700" x-text="groupLookupError"></p>
    </div>
</div>
