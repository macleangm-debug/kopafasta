{{-- Group loan wizard steps --}}
<div x-show="stepKey === 'group_setup'" class="p-6 sm:p-8">
    <h2 class="text-xl font-semibold mb-1">{{ __('borrower.apply.group_setup.title') }}</h2>
    <p class="text-sm text-gray-600 mb-5">{{ __('borrower.apply.group_setup.subtitle') }}</p>
    <template x-if="current">
        <div class="space-y-5">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.apply.group_setup.name') }}</label>
                <input type="text" x-model="group.name" maxlength="150"
                       placeholder="{{ __('borrower.apply.group_setup.name_placeholder') }}"
                       class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.apply.group_setup.member_count') }}</label>
                <input type="range"
                       x-model.number="group.target_member_count"
                       :min="groupLimits.min" :max="groupLimits.max" step="1"
                       @change="clampGroupAmountPerMember(); syncGroupAmounts(); refreshApplicationFeeQuote()"
                       class="w-full accent-amber-500">
                <div class="flex justify-between text-xs text-gray-500 mt-1">
                    <span x-text="groupLimits.min"></span>
                    <span class="font-semibold text-gray-900" x-text="group.target_member_count || groupLimits.min"></span>
                    <span x-text="groupLimits.max"></span>
                </div>
                <p class="mt-1 text-xs text-gray-500">{{ __('borrower.apply.group_setup.member_count_hint', ['min' => ($groupMemberLimits['min'] ?? 3), 'max' => ($groupMemberLimits['max'] ?? 10)]) }}</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.apply.group_setup.amount_per_member') }}</label>
                <input type="range"
                       :min="groupAmountPerMemberMin()" :max="groupAmountPerMemberMax()" step="1000"
                       x-model.number="group.amount_per_member"
                       @input="syncGroupAmounts()"
                       class="w-full accent-amber-500">
                <div class="flex justify-between text-xs text-gray-500 mt-1">
                    <span x-text="formatTzs(groupAmountPerMemberMin())"></span>
                    <span class="font-semibold text-gray-900" x-text="formatTzs(group.amount_per_member || 0)"></span>
                    <span x-text="formatTzs(groupAmountPerMemberMax())"></span>
                </div>
                <p class="mt-1 text-xs text-gray-500">{{ __('borrower.apply.group_setup.amount_per_member_hint') }}</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.apply.group_setup.purpose') }}</label>
                <select x-model="group.purpose" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                    <option value="">{{ __('borrower.apply.quote.select_purpose') }}</option>
                    @foreach ($loanPurposes as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="bg-gray-50 rounded-xl p-5 space-y-3">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">{{ __('borrower.apply.group_setup.tenure') }}</span>
                    <span class="font-bold"><span x-text="form.requested_tenure_months"></span> {{ __('borrower.apply.quote.months') }}</span>
                </div>
                <div class="flex flex-wrap gap-2" x-show="(current.tenure_options || []).length">
                    <template x-for="months in (current.tenure_options || [])" :key="months">
                        <button type="button"
                                @click="selectGroupTenure(months)"
                                class="rounded-full px-4 py-2 text-sm font-semibold ring-1 transition"
                                :class="Number(form.requested_tenure_months) === Number(months) ? 'bg-amber-500 text-gray-900 ring-amber-500' : 'bg-white text-gray-700 ring-gray-200 hover:ring-amber-300'">
                            <span x-text="months"></span> {{ __('borrower.apply.quote.months') }}
                        </button>
                    </template>
                </div>
                <input type="range"
                       x-show="(current.tenure_options || []).length >= 1"
                       :min="0"
                       :max="Math.max(0, (current.tenure_options || []).length - 1)"
                       step="1"
                       :value="Math.max(0, (current.tenure_options || []).indexOf(Number(form.requested_tenure_months)))"
                       @input="selectGroupTenure((current.tenure_options || [])[Number($event.target.value)] || form.requested_tenure_months)"
                       class="w-full accent-amber-500 mt-2">
                <p class="text-xs text-gray-500" x-text="current.group_cadence_label || @js(__('borrower.apply.group_setup.weekly_repayment'))"></p>
            </div>
            <div class="rounded-xl ring-1 ring-amber-200 bg-amber-50 p-4 text-sm">
                <div class="flex justify-between gap-3">
                    <span class="text-amber-900">{{ __('borrower.apply.group_setup.total_preview') }}</span>
                    <span class="font-bold text-amber-950" x-text="formatTzs(groupTotalAmount())"></span>
                </div>
            </div>
        </div>
    </template>
</div>

<div x-show="stepKey === 'group_members'" class="p-6 sm:p-8">
    <h2 class="text-xl font-semibold mb-1">{{ __('borrower.apply.group_members.title') }}</h2>
    <p class="text-sm text-gray-600 mb-4">{{ __('borrower.apply.group_members.subtitle') }}</p>

    <div class="rounded-xl ring-1 ring-gray-200 bg-white p-4 mb-5" x-show="groupApplicationStatus">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-500">{{ __('borrower.apply.group.application_status.title') }}</p>
            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold"
                  :class="{
                      'bg-emerald-100 text-emerald-800': ['ready_for_submission', 'approved', 'disbursed'].includes(groupApplicationStatus?.key),
                      'bg-amber-100 text-amber-800': ['inviting_members', 'member_completion'].includes(groupApplicationStatus?.key),
                      'bg-blue-100 text-blue-800': groupApplicationStatus?.key === 'under_review',
                      'bg-red-100 text-red-800': groupApplicationStatus?.key === 'rejected',
                      'bg-gray-100 text-gray-700': !groupApplicationStatus || ['draft', 'cancelled'].includes(groupApplicationStatus?.key),
                  }"
                  x-text="groupApplicationStatus?.label || ''"></span>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm" x-show="groupScoring">
            <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 px-3 py-2">
                <p class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('borrower.apply.group.scoring.completion') }}</p>
                <p class="font-bold text-gray-900" x-text="(groupScoring?.member_completion_percent ?? 0) + '%'"></p>
            </div>
            <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 px-3 py-2">
                <p class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('borrower.apply.group.scoring.avg_credit') }}</p>
                <p class="font-bold text-gray-900" x-text="groupScoring?.average_credit_score != null ? Math.round(groupScoring.average_credit_score) : '—'"></p>
            </div>
            <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 px-3 py-2">
                <p class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('borrower.apply.group.scoring.avg_income') }}</p>
                <p class="font-bold text-gray-900" x-text="groupScoring?.average_income != null ? formatTzs(groupScoring.average_income) : '—'"></p>
            </div>
            <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 px-3 py-2">
                <p class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('borrower.apply.group.scoring.risk_score') }}</p>
                <p class="font-bold text-gray-900">
                    <span x-text="groupScoring?.group_risk_score ?? '—'"></span>
                    <span class="text-xs font-medium text-gray-500" x-show="groupScoring?.risk_band" x-text="'(' + (groupScoringRiskBandLabel(groupScoring?.risk_band) || '') + ')'"></span>
                </p>
            </div>
        </div>
    </div>

    <div class="rounded-xl ring-1 ring-gray-200 bg-white p-4 mb-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">{{ __('borrower.apply.group.dashboard.title') }}</p>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4 text-sm">
            <template x-for="(line, idx) in groupProgress().summary" :key="idx">
                <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 px-3 py-2 font-medium text-gray-800" x-text="line"></div>
            </template>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-gray-500 border-b border-gray-100">
                    <tr>
                        <th class="py-2 pr-3">{{ __('borrower.apply.group.dashboard.member_name') }}</th>
                        <th class="py-2 pr-3">{{ __('borrower.apply.group.progress_steps') }}</th>
                        <th class="py-2">{{ __('borrower.apply.group.dashboard.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(member, index) in group.members" :key="member.customer_id || member.invitation_id || index">
                        <tr class="border-b border-gray-50 last:border-0">
                            <td class="py-2.5 pr-3">
                                <p class="font-medium" x-text="member.name"></p>
                                <p class="text-xs text-gray-500" x-text="member.phone"></p>
                                <p x-show="member.role === 'leader'" class="mt-0.5 text-[10px] uppercase tracking-widest text-amber-700 font-semibold">{{ __('borrower.apply.group_members.leader_badge') }}</p>
                            </td>
                            <td class="py-2.5 pr-3">
                                <div class="flex flex-wrap gap-1.5" x-show="(member.progress_steps || []).length">
                                    <template x-for="step in (member.progress_steps || [])" :key="step.key">
                                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1"
                                              :class="step.complete ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : 'bg-gray-50 text-gray-600 ring-gray-200'"
                                              :title="step.label">
                                            <span x-text="step.complete ? '✓' : '○'"></span>
                                            <span x-text="step.label"></span>
                                        </span>
                                    </template>
                                </div>
                            </td>
                            <td class="py-2.5">
                                <span class="text-xs font-semibold" :class="memberStatusClass(member)" x-text="memberStatusLabel(member)"></span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <div class="space-y-4 mb-6">
        <template x-for="(member, index) in group.members" :key="'card-' + (member.customer_id || member.invitation_id || index)">
            <div class="rounded-xl ring-1 ring-gray-200 bg-gray-50 p-4 space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-sm" x-text="member.name"></p>
                        <p class="text-xs text-gray-500" x-text="member.phone"></p>
                    </div>
                    <button type="button" x-show="member.role !== 'leader'" @click="removeGroupMember(index)"
                            class="text-xs text-red-700 font-medium shrink-0">{{ __('borrower.apply.group_members.remove') }}</button>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">{{ __('borrower.apply.group_members.member_amount') }}</span>
                    <span class="font-semibold" x-text="formatTzs(member.requested_amount)"></span>
                </div>
            </div>
        </template>
    </div>

    <div x-show="group.members.length < groupTargetCount()" class="rounded-xl ring-1 ring-gray-200 p-4 space-y-4 mb-5">
        <div class="flex flex-wrap gap-3">
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="radio" value="internal" x-model="groupMemberMode" class="text-amber-500"> {{ __('borrower.apply.group_members.mode_internal') }}
            </label>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="radio" value="external" x-model="groupMemberMode" class="text-amber-500"> {{ __('borrower.apply.group_members.mode_external') }}
            </label>
        </div>

        <div x-show="groupMemberMode === 'internal'" class="space-y-3">
            <div x-show="previousGroupMembers.length" x-cloak class="rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-4 space-y-3">
                <p class="text-sm font-semibold text-sky-900">{{ __('borrower.apply.group_members.previous_title') }}</p>
                <p class="text-xs text-sky-800">{{ __('borrower.apply.group_members.previous_hint') }}</p>
                <div class="space-y-2">
                    <template x-for="item in previousGroupMembers" :key="item.customer_id">
                        <button type="button" @click="selectPreviousGroupMember(item.customer_id)"
                                :disabled="groupLookupLoading || group.members.some(m => Number(m.customer_id) === Number(item.customer_id))"
                                class="w-full text-left rounded-lg bg-white ring-1 ring-sky-200 px-3 py-2 text-sm hover:bg-sky-50 disabled:opacity-50">
                            <span class="font-medium" x-text="item.label || item.name"></span>
                        </button>
                    </template>
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.apply.group_members.lookup_membership') }}</label>
                <div class="flex rounded-lg ring-1 ring-gray-200 overflow-hidden">
                    <span class="inline-flex items-center px-3 bg-gray-100 text-sm font-mono text-gray-600 border-r border-gray-200">KPF-TZ-</span>
                    <input type="text" x-model="groupLookupMemberNo" placeholder="ABC12345"
                           class="flex-1 border-0 px-3 py-2.5 text-sm font-mono focus:ring-0">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.apply.group_members.lookup_phone') }}</label>
                <div class="flex gap-2">
                    <div class="flex flex-1 rounded-lg ring-1 ring-gray-200 overflow-hidden">
                        <span class="inline-flex items-center px-3 bg-gray-100 text-sm text-gray-600 border-r border-gray-200">+255</span>
                        <input type="tel" x-model="groupLookupPhone" inputmode="numeric" placeholder="712345678"
                               class="flex-1 border-0 px-3 py-2.5 text-sm focus:ring-0">
                    </div>
                    <button type="button" @click="lookupGroupMember()" :disabled="groupLookupLoading"
                            class="shrink-0 rounded-full bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-2 text-sm disabled:opacity-50">
                        {{ __('borrower.apply.group_members.add_member') }}
                    </button>
                </div>
            </div>
            <p class="text-xs text-gray-500">{{ __('borrower.apply.group_members.lookup_hint') }}</p>
            <p class="text-xs text-amber-800">{{ __('borrower.apply.group_members.internal_consent_hint') }}</p>
        </div>

        <div x-show="groupMemberMode === 'external'" class="grid sm:grid-cols-2 gap-3">
            <input type="text" x-model="groupExternal.first_name" placeholder="{{ __('borrower.profile.fields.first_name') }}" class="rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
            <input type="text" x-model="groupExternal.last_name" placeholder="{{ __('borrower.profile.fields.last_name') }}" class="rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
            <input type="tel" x-model="groupExternal.phone" placeholder="{{ __('borrower.profile.fields.phone') }}" class="rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm sm:col-span-2">
            <button type="button" @click="inviteExternalGroupMember()" :disabled="groupInviteLoading"
                    class="sm:col-span-2 rounded-full bg-gray-900 hover:bg-gray-800 text-white font-semibold px-4 py-2.5 text-sm disabled:opacity-50">
                {{ __('borrower.apply.group_members.send_invite') }}
            </button>
            <div x-show="groupExternalInvite?.short_url" x-cloak class="sm:col-span-2 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 p-4 text-sm space-y-2">
                <p class="font-semibold text-emerald-900">{{ __('borrower.apply.group_members.invite_ready') }}</p>
                <a :href="groupExternalInvite.whatsapp_url || groupExternalInvite.short_url" target="_blank" class="inline-flex bg-white ring-1 ring-emerald-300 text-emerald-900 font-semibold px-4 py-2 rounded-full text-xs">{{ __('borrower.apply.guarantor_fields.share_whatsapp') }}</a>
            </div>
        </div>

        <p x-show="groupLookupError" x-cloak class="text-sm text-red-700" x-text="groupLookupError"></p>
    </div>
</div>
