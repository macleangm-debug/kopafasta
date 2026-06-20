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
                <input type="number" x-model.number="group.target_member_count"
                       :min="groupLimits.min" :max="groupLimits.max" step="1"
                       @change="syncGroupAmounts()"
                       class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                <p class="mt-1 text-xs text-gray-500">{{ __('borrower.apply.group_setup.member_count_hint', ['min' => ($groupMemberLimits['min'] ?? 5), 'max' => ($groupMemberLimits['max'] ?? 10)]) }}</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.apply.group_setup.amount_per_member') }}</label>
                <input type="number" min="1000" step="1000" x-model.number="group.amount_per_member"
                       @input="syncGroupAmounts()"
                       class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
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
            <div class="bg-gray-50 rounded-xl p-5">
                <div class="flex justify-between text-sm mb-2">
                    <span class="text-gray-600">{{ __('borrower.apply.group_setup.tenure') }}</span>
                    <span class="font-bold"><span x-text="form.requested_tenure_months"></span> {{ __('borrower.apply.quote.months') }}</span>
                </div>
                <input type="range" :min="current.tmin" :max="current.tmax" step="1"
                       x-model.number="form.requested_tenure_months" @input="updateQuote()"
                       class="w-full accent-amber-500">
                <p class="mt-2 text-xs text-gray-500">{{ __('borrower.apply.group_setup.monthly_repayment_only') }}</p>
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
    <p class="text-sm text-gray-600 mb-2">{{ __('borrower.apply.group_members.subtitle') }}</p>

    <div class="rounded-xl ring-1 ring-gray-200 bg-gray-50 p-4 mb-5 text-sm space-y-1">
        <template x-for="(line, idx) in groupProgress().summary" :key="idx">
            <p class="font-medium text-gray-800" x-text="line"></p>
        </template>
    </div>

    <div class="space-y-4 mb-6">
        <template x-for="(member, index) in group.members" :key="member.customer_id || member.invitation_id || index">
            <div class="rounded-xl ring-1 ring-gray-200 bg-gray-50 p-4 space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-sm" x-text="member.name"></p>
                        <p class="text-xs text-gray-500" x-text="member.phone"></p>
                        <p x-show="member.role === 'leader'" class="mt-1 text-[10px] uppercase tracking-widest text-amber-700 font-semibold">{{ __('borrower.apply.group_members.leader_badge') }}</p>
                        <p class="mt-1 text-xs font-medium"
                           :class="memberStatusClass(member)"
                           x-text="memberStatusLabel(member)"></p>
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
            <label class="block text-xs font-medium text-gray-600">{{ __('borrower.apply.group_members.lookup_phone') }}</label>
            <div class="flex gap-2">
                <input type="tel" x-model="groupLookupPhone" inputmode="numeric" placeholder="712345678"
                       class="flex-1 rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                <button type="button" @click="lookupGroupMember()" :disabled="groupLookupLoading"
                        class="shrink-0 rounded-full bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-2 text-sm disabled:opacity-50">
                    {{ __('borrower.apply.group_members.add_member') }}
                </button>
            </div>
            <p class="text-xs text-gray-500">{{ __('borrower.apply.group_members.lookup_hint') }}</p>
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
