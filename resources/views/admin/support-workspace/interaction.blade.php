@php
    $channel = $channel ?? 'phone';
@endphp
<x-admin.layout title="New interaction" heading="" subheading="">
    <div class="mb-4">
        <a href="{{ route('admin.support.inbox') }}" class="text-sm font-semibold text-brand hover:underline">← Inbox</a>
    </div>

    <x-admin.letterhead
        kicker="Customer Support"
        title="+ New interaction"
        subtitle="Phone call, walk-in, or other — same customer context as chat." />

    <div class="mt-6 grid lg:grid-cols-5 gap-6" x-data="{
        q: '',
        results: [],
        searching: false,
        search() {
            if (this.q.trim().length < 2) { this.results = []; return; }
            this.searching = true;
            fetch(@js(route('admin.support.interactions.search')) + '?q=' + encodeURIComponent(this.q) + '&channel={{ $channel }}', {
                headers: { 'Accept': 'application/json' }
            }).then(r => r.json()).then(data => {
                this.results = data.data || [];
                this.searching = false;
            }).catch(() => { this.searching = false; });
        }
    }">
        <div class="lg:col-span-3 space-y-4">
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 space-y-4">
                <div class="flex flex-wrap gap-2">
                    @foreach (['phone' => 'Phone call', 'walk_in' => 'Walk-in', 'other' => 'Other'] as $key => $label)
                        <a href="{{ route('admin.support.interactions.new', array_filter(['channel' => $key, 'customer_id' => $customer?->id])) }}"
                           class="text-xs font-semibold px-3 py-1.5 rounded-full {{ $channel === $key ? 'bg-brand text-white' : 'bg-slate-100 text-slate-700' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>

                @unless ($customer)
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Search member</label>
                        <input type="search" x-model="q" @input.debounce.300ms="search()"
                               placeholder="Phone · Member # · Name · Application #"
                               class="w-full rounded-xl border-gray-200 text-sm">
                        <ul class="mt-2 space-y-1" x-show="results.length" x-cloak>
                            <template x-for="row in results" :key="row.id">
                                <li>
                                    <a :href="row.url" class="block rounded-lg px-3 py-2 text-sm hover:bg-brand-muted/40" x-text="row.label"></a>
                                </li>
                            </template>
                        </ul>
                        <p class="text-xs text-gray-500 mt-2">Or continue as Guest / Non-member below.</p>
                    </div>
                @endunless

                <form method="POST" action="{{ route('admin.support.interactions.store') }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="channel" value="{{ $channel }}">
                    @if ($customer)
                        <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                        <p class="text-sm font-semibold text-gray-900">{{ trim($customer->first_name.' '.$customer->last_name) }}</p>
                        <p class="text-xs text-gray-500">{{ $customer->phone }} · {{ $customer->customer_number }}</p>
                    @else
                        <div class="grid sm:grid-cols-2 gap-3">
                            <label class="block text-xs font-semibold text-gray-700">Guest name
                                <input type="text" name="guest_name" required class="mt-1 w-full rounded-xl border-gray-200 text-sm" value="{{ old('guest_name') }}">
                            </label>
                            <label class="block text-xs font-semibold text-gray-700">Guest phone
                                <input type="text" name="guest_phone" required class="mt-1 w-full rounded-xl border-gray-200 text-sm" value="{{ old('guest_phone') }}">
                            </label>
                        </div>
                    @endif

                    <label class="block text-xs font-semibold text-gray-700">Subject (optional)
                        <input type="text" name="subject" class="mt-1 w-full rounded-xl border-gray-200 text-sm" value="{{ old('subject') }}">
                    </label>
                    <label class="block text-xs font-semibold text-gray-700">Interaction note
                        <textarea name="body" rows="5" required maxlength="5000" class="mt-1 w-full rounded-xl border-gray-200 text-sm" placeholder="What did the caller ask? What did you advise?">{{ old('body') }}</textarea>
                    </label>

                    <label class="inline-flex items-center gap-2 text-xs text-gray-700">
                        <input type="checkbox" name="create_case" value="1" class="rounded border-gray-300 text-brand">
                        Also create a case (only if investigation / follow-up is required)
                    </label>

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
                            <a href="{{ $context['application']['url'] }}" class="font-semibold text-brand hover:underline">View →</a>
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
                    <p class="text-xs text-gray-500">Search and select a member to load their journey, or capture a guest.</p>
                @endif
            </div>
        </aside>
    </div>
</x-admin.layout>
