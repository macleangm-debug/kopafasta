@php
    $party = old('party', ($customer || ! empty($partner))
        ? 'registered'
        : (request()->filled('guest_phone') || request('party') === 'non_member' ? 'non_member' : request('party', 'registered')));
    $channel = old('channel', $channel ?? request('channel', 'phone'));
    $requestedAction = old('support_action', request('support_action'));
    if ($party === 'non_member') {
        if (in_array($requestedAction, ['interaction', 'ticket', 'save_guest'], true)) {
            $action = $requestedAction;
        } elseif (request('support_action') === 'ticket') {
            $action = 'ticket';
        } elseif (request()->filled('guest_phone') && filled(request('channel'))) {
            $action = 'interaction';
        } else {
            $action = 'save_guest';
        }
    } else {
        $action = in_array($requestedAction, ['conversation', 'interaction', 'ticket'], true)
            ? $requestedAction
            : 'conversation';
    }
    $subjects = [
        'how_to_join' => __('admin.support.subjects.how_to_join'),
        'loan_application' => __('admin.support.subjects.loan_application'),
        'existing_loan' => __('admin.support.subjects.existing_loan'),
        'payment' => __('admin.support.subjects.payment'),
        'guarantor' => __('admin.support.subjects.guarantor'),
        'marketplace' => __('admin.support.subjects.marketplace'),
        'account_profile' => __('admin.support.subjects.account_profile'),
        'technical' => __('admin.support.subjects.technical'),
        'complaint' => __('admin.support.subjects.complaint'),
        'partner_inquiry' => __('admin.support.subjects.partner_inquiry'),
        'other' => __('admin.support.subjects.other'),
    ];
    $subjectKey = old('subject_key', '');
    $preselected = (bool) ($customer || ! empty($partner));
@endphp
<x-admin.layout :title="__('admin.support.new_support')" heading="" subheading="">
    <div class="mb-4">
        <a href="{{ route('admin.support.inbox') }}" class="text-sm font-semibold text-brand hover:underline">← {{ __('admin.support.inbox.title') }}</a>
    </div>

    <x-admin.letterhead
        :kicker="__('admin.support.kicker')"
        :title="__('admin.support.new_support')"
        :subtitle="__('admin.support.new_support_subtitle')" />

    <div class="mt-6 grid lg:grid-cols-5 gap-6"
         x-data="{
            party: @js($party),
            channel: @js($channel),
            action: @js($action),
            subjectKey: @js($subjectKey),
            q: '',
            results: [],
            searching: false,
            setParty(next) {
                this.party = next;
                if (next === 'non_member' && this.action === 'conversation') {
                    this.action = 'save_guest';
                }
                if (next === 'registered' && this.action === 'save_guest') {
                    this.action = 'conversation';
                }
            },
            search() {
                if (this.q.trim().length < 2) { this.results = []; return; }
                this.searching = true;
                fetch(@js(route('admin.support.interactions.search')) + '?q=' + encodeURIComponent(this.q) + '&party=' + encodeURIComponent(this.party), {
                    headers: { 'Accept': 'application/json' }
                }).then(r => r.json()).then(data => {
                    this.results = data.data || [];
                    this.searching = false;
                }).catch(() => { this.searching = false; });
            }
         }">
        <div class="lg:col-span-3 space-y-4">
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 space-y-5">
                <div>
                    <p class="text-[11px] uppercase tracking-[0.16em] font-semibold text-slate-500 mb-2">{{ __('admin.support.who_helping') }}</p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="setParty('registered')"
                                :class="party === 'registered' ? 'bg-brand text-white' : 'bg-slate-100 text-slate-700'"
                                class="text-xs font-semibold px-3 py-1.5 rounded-full">{{ __('admin.support.party_registered') }}</button>
                        <button type="button" @click="setParty('non_member')"
                                :class="party === 'non_member' ? 'bg-brand text-white' : 'bg-slate-100 text-slate-700'"
                                class="text-xs font-semibold px-3 py-1.5 rounded-full">{{ __('admin.support.party_guest') }}</button>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.support.interactions.store') }}" class="space-y-4" data-no-draft>
                    @csrf
                    <input type="hidden" name="party" :value="party">

                    <div x-show="party === 'registered'" x-cloak class="space-y-3 rounded-xl bg-slate-50 ring-1 ring-slate-200/80 p-4">
                        <p class="text-[11px] uppercase tracking-[0.16em] font-semibold text-slate-500">{{ __('admin.support.search_card') }}</p>
                        @if ($customer)
                            <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                            <div class="rounded-xl bg-white ring-1 ring-brand/15 px-4 py-3">
                                <p class="text-[11px] uppercase tracking-widest text-brand font-semibold">{{ __('admin.support.customer_details') }}</p>
                                <p class="text-sm font-semibold text-gray-900 mt-1">{{ trim($customer->first_name.' '.$customer->last_name) }}</p>
                                <p class="text-xs text-gray-500">{{ $customer->phone }} · {{ $customer->customer_number }}</p>
                            </div>
                            @unless ($preselected)
                                <a href="{{ route('admin.support.interactions.new', ['party' => 'registered']) }}" class="text-xs font-semibold text-brand hover:underline">{{ __('admin.support.change_person') }}</a>
                            @endunless
                        @elseif (! empty($partner))
                            <input type="hidden" name="partner_id" value="{{ $partner->id }}">
                            <div class="rounded-xl bg-white ring-1 ring-brand/15 px-4 py-3">
                                <p class="text-[11px] uppercase tracking-widest text-brand font-semibold">{{ __('admin.support.customer_details') }}</p>
                                <p class="text-sm font-semibold text-gray-900 mt-1">{{ $partner->name }}</p>
                                <p class="text-xs text-gray-500">{{ $partner->phone ?? '—' }} · {{ __('admin.support.partner') }}</p>
                            </div>
                        @else
                            <label class="block text-xs font-semibold text-gray-700 mb-1">{{ __('admin.support.search_label') }}</label>
                            <input type="search" x-model="q" @input.debounce.300ms="search()"
                                   placeholder="{{ __('admin.support.search_placeholder') }}"
                                   class="w-full rounded-xl border-gray-200 text-sm">
                            <ul class="mt-2 space-y-1" x-show="results.length" x-cloak>
                                <template x-for="row in results" :key="row.id + '-' + row.kind">
                                    <li>
                                        <a :href="row.url" class="block rounded-lg px-3 py-2 text-sm hover:bg-brand-muted/40">
                                            <span class="font-semibold" x-text="row.label"></span>
                                            <span class="text-xs text-slate-500" x-text="' · ' + (row.kind_label || '')"></span>
                                        </a>
                                    </li>
                                </template>
                            </ul>
                            <p class="text-xs text-gray-500 mt-2" x-show="searching">{{ __('admin.support.searching') }}</p>
                        @endif
                    </div>

                    <div x-show="party === 'non_member'" x-cloak class="space-y-3 rounded-xl bg-slate-50 ring-1 ring-slate-200/80 p-4">
                        <div class="grid sm:grid-cols-2 gap-3">
                            <label class="block text-xs font-semibold text-gray-700">{{ __('admin.support.guest_first_name') }}
                                <input type="text" name="guest_first_name" class="mt-1 w-full rounded-xl border-gray-200 text-sm" value="{{ old('guest_first_name', request('guest_first_name')) }}" :required="party === 'non_member'">
                            </label>
                            <label class="block text-xs font-semibold text-gray-700">{{ __('admin.support.guest_last_name') }}
                                <input type="text" name="guest_last_name" class="mt-1 w-full rounded-xl border-gray-200 text-sm" value="{{ old('guest_last_name', request('guest_last_name')) }}" :required="party === 'non_member'">
                            </label>
                        </div>
                        <x-admin.phone-input name="guest_phone" :label="__('admin.support.guest_phone')" :value="old('guest_phone', request('guest_phone'))" :required="false" />
                        <p class="text-xs text-slate-500">{{ __('admin.support.hint_guest_no_outbound_chat') }}</p>
                    </div>

                    <div>
                        <p class="text-[11px] uppercase tracking-[0.16em] font-semibold text-slate-500 mb-2">{{ __('admin.support.choose_action') }}</p>
                        <div class="flex flex-wrap gap-2">
                            <label x-show="party === 'registered'" class="text-xs font-semibold px-3 py-1.5 rounded-full cursor-pointer"
                                   :class="action === 'conversation' ? 'bg-brand text-white' : 'bg-slate-100 text-slate-700'">
                                <input type="radio" name="support_action" value="conversation" class="sr-only" x-model="action">
                                {{ __('admin.support.action_conversation') }}
                            </label>
                            <label x-show="party === 'non_member'" class="text-xs font-semibold px-3 py-1.5 rounded-full cursor-pointer"
                                   :class="action === 'save_guest' ? 'bg-brand text-white' : 'bg-slate-100 text-slate-700'">
                                <input type="radio" name="support_action" value="save_guest" class="sr-only" x-model="action">
                                {{ __('admin.support.action_save_guest') }}
                            </label>
                            <label class="text-xs font-semibold px-3 py-1.5 rounded-full cursor-pointer"
                                   :class="action === 'interaction' ? 'bg-brand text-white' : 'bg-slate-100 text-slate-700'">
                                <input type="radio" name="support_action" value="interaction" class="sr-only" x-model="action">
                                {{ __('admin.support.action_interaction') }}
                            </label>
                            <label class="text-xs font-semibold px-3 py-1.5 rounded-full cursor-pointer"
                                   :class="action === 'ticket' ? 'bg-brand text-white' : 'bg-slate-100 text-slate-700'">
                                <input type="radio" name="support_action" value="ticket" class="sr-only" x-model="action">
                                {{ __('admin.support.action_ticket') }}
                            </label>
                        </div>
                        <p class="text-xs text-slate-500 mt-2" x-show="action === 'conversation'" x-cloak>{{ __('admin.support.hint_conversation') }}</p>
                        <p class="text-xs text-slate-500 mt-2" x-show="action === 'save_guest'" x-cloak>{{ __('admin.support.hint_save_guest') }}</p>
                        <p class="text-xs text-slate-500 mt-2" x-show="action === 'interaction'" x-cloak>{{ __('admin.support.hint_interaction') }}</p>
                        <p class="text-xs text-slate-500 mt-2" x-show="action === 'ticket'" x-cloak>{{ __('admin.support.hint_ticket') }}</p>
                    </div>

                    <div x-show="action === 'interaction'" x-cloak>
                        <p class="text-[11px] uppercase tracking-[0.16em] font-semibold text-slate-500 mb-2">{{ __('admin.support.channel') }}</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach (['phone' => __('admin.support.channel_phone'), 'walk_in' => __('admin.support.channel_walk_in'), 'other' => __('admin.support.channel_other')] as $key => $label)
                                <label class="text-xs font-semibold px-3 py-1.5 rounded-full cursor-pointer"
                                       :class="channel === '{{ $key }}' ? 'bg-brand text-white' : 'bg-slate-100 text-slate-700'">
                                    <input type="radio" name="channel" value="{{ $key }}" class="sr-only" x-model="channel" @checked($channel === $key)>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <template x-if="action !== 'interaction'">
                        <input type="hidden" name="channel" value="web_chat">
                    </template>

                    <div x-show="action !== 'conversation' && action !== 'save_guest'" x-cloak class="space-y-3">
                        <label class="block text-xs font-semibold text-gray-700">{{ __('admin.support.subject') }}
                            <select name="subject_key" x-model="subjectKey" class="mt-1 w-full rounded-xl border-gray-200 text-sm"
                                    :required="action !== 'conversation' && action !== 'save_guest'">
                                <option value="">{{ __('admin.support.subject_select') }}</option>
                                @foreach ($subjects as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-xs font-semibold text-gray-700" x-show="subjectKey === 'other'" x-cloak>{{ __('admin.support.subject_other') }}
                            <input type="text" name="subject_other" class="mt-1 w-full rounded-xl border-gray-200 text-sm" value="{{ old('subject_other') }}">
                        </label>
                        <label class="block text-xs font-semibold text-gray-700">
                            <span x-text="action === 'ticket' ? @js(__('admin.support.ticket_description')) : @js(__('admin.support.internal_notes'))"></span>
                            <textarea name="body" rows="4" class="mt-1 w-full rounded-xl border-gray-200 text-sm" :required="action !== 'conversation' && action !== 'save_guest'"
                                      placeholder="{{ __('admin.support.internal_notes_placeholder') }}">{{ old('body') }}</textarea>
                        </label>
                    </div>

                    <button type="submit" class="w-full sm:w-auto rounded-xl bg-brand text-white text-sm font-semibold px-5 py-2.5">
                        <span x-show="action === 'conversation'">{{ __('admin.support.submit_conversation') }}</span>
                        <span x-show="action === 'save_guest'" x-cloak>{{ __('admin.support.submit_save_guest') }}</span>
                        <span x-show="action === 'interaction'" x-cloak>{{ __('admin.support.submit_interaction') }}</span>
                        <span x-show="action === 'ticket'" x-cloak>{{ __('admin.support.submit_ticket') }}</span>
                    </button>
                </form>
            </div>
        </div>
        <div class="lg:col-span-2 space-y-4">
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 text-sm text-slate-600 space-y-2">
                <p class="text-[11px] uppercase tracking-[0.16em] font-semibold text-brand">{{ __('admin.support.tips_title') }}</p>
                <p>{{ __('admin.support.tips_body') }}</p>
            </div>
        </div>
    </div>
</x-admin.layout>
