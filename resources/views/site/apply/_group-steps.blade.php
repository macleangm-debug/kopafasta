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
            </div>
        </div>
    </template>
</div>

<div x-show="stepKey === 'group_members'" class="p-6 sm:p-8">
    <h2 class="text-xl font-semibold mb-1">{{ __('borrower.apply.group_members.title') }}</h2>
    <p class="text-sm text-gray-600 mb-2">{{ __('borrower.apply.group_members.subtitle') }}</p>
    <p class="text-xs text-gray-500 mb-5">
        <span x-text="@js(__('borrower.apply.group_members.count', ['count' => ':count', 'max' => ':max']))?.replace(':count', group.members.length)?.replace(':max', groupLimits.max)"></span>
        · <span x-text="@js(__('borrower.apply.group_members.min_hint', ['min' => ':min']))?.replace(':min', groupLimits.min)"></span>
    </p>

    <div class="space-y-4 mb-6">
        <template x-for="(member, index) in group.members" :key="member.customer_id">
            <div class="rounded-xl ring-1 ring-gray-200 bg-gray-50 p-4 space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-sm" x-text="member.name"></p>
                        <p class="text-xs text-gray-500" x-text="member.phone"></p>
                        <p x-show="member.role === 'leader'" class="mt-1 text-[10px] uppercase tracking-widest text-amber-700 font-semibold">{{ __('borrower.apply.group_members.leader_badge') }}</p>
                    </div>
                    <button type="button" x-show="member.role !== 'leader'" @click="removeGroupMember(index)"
                            class="text-xs text-red-700 font-medium shrink-0">{{ __('borrower.apply.group_members.remove') }}</button>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.apply.group_members.member_amount') }}</label>
                    <input type="number" min="1000" step="1000" x-model.number="member.requested_amount"
                           @input="updateGroupTotal()" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                </div>
            </div>
        </template>
    </div>

    <div class="rounded-xl ring-1 ring-amber-200 bg-amber-50 p-4 mb-5 text-sm">
        <div class="flex justify-between gap-3">
            <span class="text-amber-900">{{ __('borrower.apply.group_members.total') }}</span>
            <span class="font-bold text-amber-950" x-text="formatTzs(form.requested_amount)"></span>
        </div>
    </div>

    <div x-show="group.members.length < groupLimits.max" class="rounded-xl ring-1 ring-gray-200 p-4 space-y-3">
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
        <p x-show="groupLookupError" x-cloak class="text-sm text-red-700" x-text="groupLookupError"></p>
    </div>
</div>
