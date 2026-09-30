@php
    $party = old('party', ($customer || ! empty($partner)) ? 'registered' : request('party', 'registered'));
    $channel = old('channel', $channel ?? 'phone');
    $subjects = [
        'how_to_join' => 'How to join / registration',
        'loan_application' => 'Loan application inquiry',
        'existing_loan' => 'Existing loan',
        'payment' => 'Payment',
        'guarantor' => 'Guarantor',
        'marketplace' => 'Marketplace / asset',
        'account_profile' => 'Account / profile',
        'technical' => 'Technical problem',
        'complaint' => 'Complaint',
        'partner_inquiry' => 'Partner inquiry',
        'other' => 'Other',
    ];
    $subjectKey = old('subject_key', '');
@endphp
<x-admin.layout title="New interaction" heading="" subheading="">
    <div class="mb-4">
        <a href="{{ route('admin.support.inbox') }}" class="text-sm font-semibold text-brand hover:underline">← Inbox</a>
    </div>

    <x-admin.letterhead
        kicker="Customer Support"
        title="+ New interaction"
        subtitle="Record a phone, walk-in, or other contact — same customer context as chat." />

    <div class="mt-6 grid lg:grid-cols-5 gap-6"
         x-data="{
            party: @js($party),
            channel: @js($channel),
            subjectKey: @js($subjectKey),
            q: '',
            results: [],
            searching: false,
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
                    <p class="text-[11px] uppercase tracking-[0.16em] font-semibold text-slate-500 mb-2">1. Who contacted us?</p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="party = 'registered'"
                                :class="party === 'registered' ? 'bg-brand text-white' : 'bg-slate-100 text-slate-700'"
                                class="text-xs font-semibold px-3 py-1.5 rounded-full">Member / Partner</button>
                        <button type="button" @click="party = 'non_member'"
                                :class="party === 'non_member' ? 'bg-brand text-white' : 'bg-slate-100 text-slate-700'"
                                class="text-xs font-semibold px-3 py-1.5 rounded-full">Non-member</button>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.support.interactions.store') }}" class="space-y-4" data-no-draft>
                    @csrf
                    <input type="hidden" name="party" :value="party">

                    <div x-show="party === 'registered'" x-cloak class="space-y-3">
                        @if ($customer)
                            <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                            <p class="text-sm font-semibold text-gray-900">{{ trim($customer->first_name.' '.$customer->last_name) }}</p>
                            <p class="text-xs text-gray-500">{{ $customer->phone }} · {{ $customer->customer_number }}</p>
                            <a href="{{ route('admin.support.interactions.new', ['party' => 'registered']) }}" class="text-xs font-semibold text-brand hover:underline">Change person</a>
                        @elseif (! empty($partner))
                            <input type="hidden" name="partner_id" value="{{ $partner->id }}">
                            <p class="text-sm font-semibold text-gray-900">{{ $partner->name }}</p>
                            <p class="text-xs text-gray-500">{{ $partner->phone ?? '—' }} · Partner</p>
                            <a href="{{ route('admin.support.interactions.new', ['party' => 'registered']) }}" class="text-xs font-semibold text-brand hover:underline">Change person</a>
                        @else
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Search Member / Partner</label>
                                <input type="search" x-model="q" @input.debounce.300ms="search()"
                                       placeholder="Phone · Member/Partner # · Name · Application #"
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
                                <p class="text-xs text-gray-500 mt-2" x-show="searching">Searching…</p>
                            </div>
                        @endif
                    </div>

                    <div>
                        <p class="text-[11px] uppercase tracking-[0.16em] font-semibold text-slate-500 mb-2">Channel</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach (['phone' => 'Phone call', 'walk_in' => 'Walk-in', 'other' => 'Other'] as $key => $label)
                                <label class="text-xs font-semibold px-3 py-1.5 rounded-full cursor-pointer"
                                       :class="channel === '{{ $key }}' ? 'bg-brand text-white' : 'bg-slate-100 text-slate-700'">
                                    <input type="radio" name="channel" value="{{ $key }}" class="sr-only" x-model="channel" @checked($channel === $key)>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div x-show="party === 'non_member'" x-cloak class="space-y-3 rounded-xl bg-slate-50 ring-1 ring-slate-200/80 p-4">
                        <div class="grid sm:grid-cols-3 gap-3">
                            <label class="block text-xs font-semibold text-gray-700">First name
                                <input type="text" name="guest_first_name" class="mt-1 w-full rounded-xl border-gray-200 text-sm" value="{{ old('guest_first_name') }}" :required="party === 'non_member'">
                            </label>
                            <label class="block text-xs font-semibold text-gray-700">Middle name <span class="font-normal text-gray-400">(optional)</span>
                                <input type="text" name="guest_middle_name" class="mt-1 w-full rounded-xl border-gray-200 text-sm" value="{{ old('guest_middle_name') }}">
                            </label>
                            <label class="block text-xs font-semibold text-gray-700">Last name
                                <input type="text" name="guest_last_name" class="mt-1 w-full rounded-xl border-gray-200 text-sm" value="{{ old('guest_last_name') }}" :required="party === 'non_member'">
                            </label>
                        </div>
                        <div>
                            <x-admin.phone-input name="guest_phone" label="Phone" :value="old('guest_phone')" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Subject</label>
                        <select name="subject_key" x-model="subjectKey" class="w-full rounded-xl border-gray-200 text-sm" required>
                            <option value="">Select subject…</option>
                            @foreach ($subjects as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="mt-2" x-show="subjectKey === 'other'" x-cloak>
                            <input type="text" name="subject_other" maxlength="180" placeholder="Short subject"
                                   class="w-full rounded-xl border-gray-200 text-sm" value="{{ old('subject_other') }}">
                        </div>
                    </div>

                    <label class="block text-xs font-semibold text-gray-700">Interaction note
                        <textarea name="body" rows="5" required maxlength="5000" class="mt-1 w-full rounded-xl border-gray-200 text-sm" placeholder="What did they ask? What did you advise?">{{ old('body') }}</textarea>
                    </label>

                    <p class="text-xs text-slate-500">Default is <span class="font-semibold text-slate-700">record interaction only</span>. Create a follow-up case later only if investigation or another department is needed.</p>

                    <div class="flex flex-wrap gap-2 pt-1">
                        <button class="rounded-xl bg-brand-gold text-brand text-sm font-semibold px-4 py-2.5">Record interaction</button>
                        <a href="{{ route('admin.support.inbox') }}" class="rounded-xl ring-1 ring-gray-200 text-sm font-semibold px-4 py-2.5 text-gray-700">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <aside class="lg:col-span-2 space-y-4">
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 text-sm space-y-3">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Customer details</p>
                @if ($context)
                    <p class="font-bold text-gray-900">{{ $context['name'] }}</p>
                    <p class="text-xs text-gray-500">{{ $context['phone'] ?: '—' }} · {{ $context['member_number'] ?? 'Guest' }}</p>
                    @if (! empty($context['member_url']))
                        <a href="{{ $context['member_url'] }}" class="text-xs font-semibold text-brand hover:underline">Open Member 360 →</a>
                    @endif
                    @if (! empty($context['application']))
                        <div class="pt-2 border-t border-gray-100 text-xs">
                            <p class="font-semibold">Application {{ $context['application']['number'] }}</p>
                            <p class="text-gray-500">{{ $context['application']['stage'] }}</p>
                        </div>
                    @endif
                    @if (! empty($context['loan']))
                        <div class="pt-2 border-t border-gray-100 text-xs">
                            <p class="font-semibold">Loan {{ $context['loan']['number'] }}</p>
                            <p class="text-gray-500">TZS {{ number_format((float) ($context['loan']['outstanding'] ?? 0)) }} outstanding</p>
                        </div>
                    @endif
                    <div class="pt-2 border-t border-gray-100 text-xs">
                        <p class="font-semibold">Open cases ({{ count($context['open_cases'] ?? []) }})</p>
                        @forelse ($context['open_cases'] ?? [] as $case)
                            <a href="{{ $case['url'] }}" class="block text-brand hover:underline">{{ $case['number'] }}</a>
                        @empty
                            <p class="text-gray-400">None</p>
                        @endforelse
                    </div>
                @else
                    <p class="text-xs text-gray-500">Select a Member/Partner to load their journey, or capture a Non-member below.</p>
                @endif
            </div>
        </aside>
    </div>
</x-admin.layout>
