{{-- Group loan wizard steps --}}
<div x-show="stepKey === 'group_setup'" class="p-6 sm:p-8">
    <x-site.wizard-step-header
        :eyebrow="__('borrower.apply.steps.group_setup')"
        :title="__('borrower.apply.group_setup.title')"
        :subtitle="__('borrower.apply.group_setup.subtitle')"
    />

    <template x-if="current">
        <div class="space-y-6">
            <div class="glass-card p-5 sm:p-6 ring-1 ring-brand/15 space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">{{ __('borrower.apply.group_setup.name') }}</label>
                    <input type="text" x-model="group.name" maxlength="150"
                           placeholder="{{ __('borrower.apply.group_setup.name_placeholder') }}"
                           class="w-full rounded-xl border-gray-300 ring-1 ring-gray-200 px-4 py-3 text-sm focus:ring-brand">
                </div>
                <div>
                    <div class="flex items-end justify-between gap-3 mb-3">
                        <label class="text-sm font-semibold text-gray-700">{{ __('borrower.apply.group_setup.member_count') }}</label>
                        <span class="text-lg font-extrabold text-brand tabular-nums" x-text="group.target_member_count || groupLimits.min"></span>
                    </div>
                    <input type="range"
                           x-model.number="group.target_member_count"
                           :min="groupLimits.min" :max="groupLimits.max" step="1"
                           @change="clampGroupAmountPerMember(); syncGroupAmounts(); refreshApplicationFeeQuote()"
                           class="w-full accent-brand h-2 rounded-full">
                    <div class="flex justify-between text-xs text-gray-500 mt-2 tabular-nums">
                        <span x-text="groupLimits.min"></span>
                        <span x-text="groupLimits.max"></span>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">{{ __('borrower.apply.group_setup.member_count_hint', ['min' => ($groupMemberLimits['min'] ?? 3), 'max' => ($groupMemberLimits['max'] ?? 10)]) }}</p>
                </div>
                <div>
                    <div class="flex items-end justify-between gap-3 mb-3">
                        <label class="text-sm font-semibold text-gray-700">{{ __('borrower.apply.group_setup.amount_per_member') }}</label>
                        <span class="text-lg font-extrabold text-brand tabular-nums" x-text="formatTzs(group.amount_per_member || 0)"></span>
                    </div>
                    <input type="range"
                           :min="groupAmountPerMemberMin()" :max="groupAmountPerMemberMax()" step="1000"
                           x-model.number="group.amount_per_member"
                           @input="syncGroupAmounts()"
                           class="w-full accent-brand h-2 rounded-full">
                    <div class="flex justify-between text-xs text-gray-500 mt-2 tabular-nums">
                        <span x-text="formatTzs(groupAmountPerMemberMin())"></span>
                        <span x-text="formatTzs(groupAmountPerMemberMax())"></span>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">{{ __('borrower.apply.group_setup.amount_per_member_hint') }}</p>
                </div>
                <div>
                    <x-site.sheet-select
                        model="group.purpose"
                        :label="__('borrower.apply.group_setup.purpose')"
                        :options="$loanPurposes"
                        :placeholder="__('borrower.apply.quote.select_purpose')"
                    />
                </div>
            </div>

            <div class="glass-card overflow-hidden ring-1 ring-brand/15">
                <div class="bg-gradient-to-br from-brand-muted/50 to-white px-5 sm:px-6 py-4 border-b border-brand/10">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.group_setup.tenure') }}</p>
                </div>
                <div class="p-5 sm:p-6 space-y-4">
                    <div class="flex items-end justify-between gap-3">
                        <span class="text-sm font-semibold text-gray-700">{{ __('borrower.apply.group_setup.tenure') }}</span>
                        <span class="text-lg font-extrabold text-brand tabular-nums">
                            <span x-text="form.requested_tenure_months"></span> {{ __('borrower.apply.quote.months') }}
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-2" x-show="(current.tenure_options || []).length">
                        <template x-for="months in (current.tenure_options || [])" :key="months">
                            <button type="button"
                                    @click="selectGroupTenure(months)"
                                    class="rounded-full px-4 py-2 text-sm font-semibold ring-1 transition"
                                    :class="Number(form.requested_tenure_months) === Number(months) ? 'bg-brand text-white ring-brand' : 'bg-white text-gray-700 ring-gray-200 hover:ring-brand/40'">
                                <span x-text="months"></span> {{ __('borrower.apply.quote.months') }}
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-2xl ring-1 ring-brand/20 bg-gradient-to-br from-brand-muted/40 to-white p-5 text-sm">
                <div class="flex justify-between gap-3 items-center">
                    <span class="text-gray-700 font-medium">{{ __('borrower.apply.group_setup.total_preview') }}</span>
                    <span class="text-xl font-extrabold text-brand tabular-nums" x-text="formatTzs(groupTotalAmount())"></span>
                </div>
            </div>
        </div>
    </template>
</div>

<div x-show="stepKey === 'group_members' && ! $data.feeGateOpen" class="p-6 sm:p-8">
    <x-site.wizard-step-header
        :eyebrow="__('borrower.apply.steps.group_members')"
        :title="__('borrower.apply.group_members.title')"
        :subtitle="__('borrower.apply.group_members.subtitle')"
    />

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        <div class="rounded-2xl bg-white ring-1 ring-brand/15 px-3 py-3 text-center">
            <p class="text-2xl font-extrabold text-brand tabular-nums" x-text="groupProgress().added + '/' + groupProgress().target"></p>
            <p class="text-[10px] uppercase tracking-widest text-gray-500 mt-1">{{ __('borrower.apply.group.progress.added_label') }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-brand/15 px-3 py-3 text-center">
            <p class="text-2xl font-extrabold text-brand tabular-nums" x-text="(groupProgress().profiles_complete ?? 0) + '/' + groupProgress().target"></p>
            <p class="text-[10px] uppercase tracking-widest text-gray-500 mt-1">{{ __('borrower.apply.group.progress.profiles_label') }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-brand/15 px-3 py-3 text-center">
            <p class="text-2xl font-extrabold text-brand tabular-nums" x-text="groupProgress().verified + '/' + groupProgress().target"></p>
            <p class="text-[10px] uppercase tracking-widest text-gray-500 mt-1">{{ __('borrower.apply.group.progress.verified_label') }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-brand/15 px-3 py-3 text-center">
            <p class="text-2xl font-extrabold text-amber-600 tabular-nums" x-text="groupProgress().invitations_pending ?? 0"></p>
            <p class="text-[10px] uppercase tracking-widest text-gray-500 mt-1">{{ __('borrower.apply.group.progress.pending_label') }}</p>
        </div>
    </div>

    <div class="mb-5">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.group_members.your_team') }}</p>
            <p class="text-xs text-gray-500">{{ __('borrower.apply.group_members.steps_legend') }}</p>
        </div>
        <div class="space-y-2">
            <template x-for="(member, index) in (group.members || [])" :key="'gm-' + index + '-c' + (member.customer_id || 0) + '-i' + (member.invitation_id || 0)">
                <div class="glass-card rounded-2xl ring-1 overflow-hidden"
                     :class="member.role === 'leader' ? 'ring-brand/30 bg-brand-muted/20' : 'ring-gray-200/80'">
                    <button type="button"
                            class="w-full text-left p-4 flex flex-wrap items-center gap-3"
                            @click="member._open = !member._open">
                        <div class="size-11 rounded-full bg-brand text-white grid place-items-center text-sm font-bold shrink-0"
                             x-text="(member.name || '?').trim().charAt(0).toUpperCase()"></div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-sm text-gray-900 truncate" x-text="member.name"></p>
                            <p class="text-xs text-gray-500" x-text="member.phone"></p>
                            <p x-show="member.role === 'leader'" class="mt-0.5 text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.apply.group_members.leader_badge') }}</p>
                        </div>
                        <span class="text-xs font-semibold shrink-0" :class="memberStatusClass(member)" x-text="memberStatusLabel(member)"></span>
                        <span class="text-sm font-semibold tabular-nums text-gray-900 shrink-0" x-text="formatTzs(member.requested_amount)"></span>
                        <svg class="w-4 h-4 text-gray-400 shrink-0 transition" :class="member._open && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                    </button>
                    <div x-show="member._open" x-cloak class="px-4 pb-4 pt-0 border-t border-gray-100 space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 pt-3" x-show="(member.progress_steps || []).length">
                            <template x-for="step in (member.progress_steps || [])" :key="step.key">
                                <div class="rounded-xl px-3 py-2.5 text-sm font-semibold ring-1 flex items-center gap-2"
                                      :class="step.complete ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : 'bg-amber-50/80 text-amber-900 ring-amber-200'">
                                    <span class="size-5 rounded-full grid place-items-center text-xs shrink-0"
                                          :class="step.complete ? 'bg-emerald-600 text-white' : 'bg-white ring-1 ring-amber-300 text-amber-700'"
                                          x-text="step.complete ? '✓' : '○'"></span>
                                    <span x-text="step.label"></span>
                                </div>
                            </template>
                        </div>

                        <div x-show="member.role !== 'leader'" class="flex flex-wrap items-start justify-between gap-3 pt-1">
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
                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-2xl bg-brand hover:bg-brand-light text-white font-semibold px-6 py-3.5 text-sm shadow-sm">
            <span class="text-lg leading-none">+</span>
            {{ __('borrower.apply.group_members.add_cta') }}
            <span class="opacity-80 font-normal"
                  x-text="'(' + Math.max(0, groupTargetCount() - group.members.length) + ')'"></span>
        </button>
        <p class="text-xs text-gray-500 mt-2"
           x-text="@js(__('borrower.apply.group_members.add_more_hint')).replace(':remaining', Math.max(0, groupTargetCount() - group.members.length)).replace(':target', groupTargetCount())"></p>
    </div>

    {{-- Expand-in-page add panel (same pattern as guarantor) --}}
    <div x-show="addMemberOpen && group.members.length < groupTargetCount()" x-cloak
         class="glass-card rounded-2xl ring-1 ring-brand/15 p-5 sm:p-6 space-y-5 mb-5">
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
                    <input type="tel" x-model="groupLookupPhone" inputmode="numeric" placeholder="712345678"
                           @input="groupMemberLookup = { ok: false, label: '', error: '', data: null }"
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
                    <input type="tel" x-model="groupExternal.phone" inputmode="numeric" placeholder="712345678"
                           class="flex-1 border-0 px-3 py-2.5 text-sm focus:ring-0">
                </div>
            </div>
            <button type="button" x-show="!groupExternalInvite?.short_url" @click="inviteExternalGroupMember()" :disabled="groupInviteLoading"
                    class="w-full rounded-xl bg-brand hover:bg-brand-light text-white font-semibold px-4 py-3 text-sm disabled:opacity-50">
                <span x-text="groupInviteLoading ? @js(__('borrower.apply.guarantor_fields.generating_link')) : @js(__('borrower.apply.guarantor_fields.generate_link'))"></span>
            </button>
            <div x-show="groupExternalInvite?.short_url" x-cloak x-data="{ copied: false }"
                 class="rounded-2xl bg-gradient-to-br from-brand via-brand to-brand-light text-white px-5 py-5 space-y-4 shadow-sm">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.apply.guarantor_fields.share_via') }}</p>
                    <p class="text-sm text-white/90 mt-1">{{ __('borrower.apply.group_members.invite_ready') }}</p>
                </div>
                <p class="text-xs font-mono text-brand bg-brand-gold/90 rounded-xl px-3 py-2.5 break-all" x-text="groupExternalInvite.short_url || groupExternalInvite.invitation_url"></p>
                <div class="flex flex-wrap gap-2">
                    <a :href="groupExternalInvite.whatsapp_url || '#'" :class="!groupExternalInvite.whatsapp_url && 'pointer-events-none opacity-50'" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-2 bg-emerald-500 hover:bg-emerald-400 text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                        {{ __('borrower.apply.guarantor_fields.share_whatsapp') }}
                    </a>
                    <a :href="groupExternalInvite.sms_url || '#'" :class="!groupExternalInvite.sms_url && 'pointer-events-none opacity-50'"
                       class="inline-flex items-center gap-2 bg-white/15 hover:bg-white/25 ring-1 ring-white/30 text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                        {{ __('borrower.apply.guarantor_fields.share_sms') }}
                    </a>
                    <button type="button"
                            @click="navigator.clipboard.writeText(groupExternalInvite.short_url || groupExternalInvite.invitation_url); copied = true; setTimeout(() => copied = false, 2000)"
                            class="inline-flex items-center gap-2 bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-4 py-2.5 rounded-xl text-sm">
                        <span x-text="copied ? @js(__('borrower.apply.guarantor_fields.link_copied')) : @js(__('borrower.apply.guarantor_fields.share_copy'))"></span>
                    </button>
                </div>
                <button type="button" @click="closeAddMemberPanel()"
                        class="text-xs font-semibold text-brand-gold hover:underline">{{ __('borrower.apply.group_members.done_adding') }}</button>
            </div>
        </div>

        <p x-show="groupLookupError" x-cloak class="text-sm text-red-700" x-text="groupLookupError"></p>
    </div>
</div>
