@php
    $filters = [
        'all' => 'All',
        'unread' => 'Unread',
        'waiting' => 'Waiting',
        'mine' => 'Mine',
        'cases' => 'Cases',
    ];
    $locale = str_starts_with(app()->getLocale(), 'en') ? 'en' : 'sw';
    $quickReplyBodies = $quickReplyBodies ?? [];
    $specialistFollowUps = $specialistFollowUps ?? 0;
@endphp
<x-admin.layout title="Inbox" heading="" subheading="">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <x-admin.letterhead
            kicker="Customer Support"
            title="Inbox"
            subtitle="Conversations · Conversation · Customer details" />
        <a href="{{ route('admin.support.interactions.new') }}"
           class="inline-flex rounded-xl bg-brand text-white text-sm font-semibold px-4 py-2.5 hover:brightness-95">
            + New interaction
        </a>
    </div>

    @if ($specialistFollowUps > 0)
        <div class="mb-3 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-950">
            <span class="font-semibold">{{ $specialistFollowUps }}</span> specialist response(s) awaiting Support follow-up on open cases.
        </div>
    @endif

    <div class="mx-auto w-full max-w-6xl rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden"
         x-data="{
            draft: '',
            bodies: @js($quickReplyBodies),
            insertQuick(key) {
                this.draft = this.bodies[key] || '';
                $refs.composer?.focus();
            }
         }">
        {{-- ~26% / 42% / 32% — chat column deliberately narrower than before --}}
        <div class="grid lg:grid-cols-[minmax(15rem,1.1fr)_minmax(0,1.55fr)_minmax(14rem,1.2fr)] min-h-[34rem]">
            {{-- CONVERSATIONS --}}
            <aside class="bg-slate-50/80 border-b lg:border-b-0 lg:border-r border-slate-200/80 flex flex-col {{ $conversation ? 'hidden lg:flex' : 'flex' }}">
                <div class="px-3 pt-3 pb-1">
                    <p class="text-[11px] uppercase tracking-[0.16em] font-semibold text-slate-500">Conversations</p>
                </div>
                <form method="GET" action="{{ route('admin.support.inbox') }}" class="px-3 pb-2 space-y-2">
                    <input type="search" name="q" value="{{ $q }}" placeholder="Search name, phone, message…"
                           class="w-full rounded-lg border-slate-200 text-sm bg-white focus:ring-brand/30">
                    <input type="hidden" name="filter" value="{{ $filter }}">
                </form>
                <div class="flex flex-wrap gap-1 px-3 pb-2">
                    @foreach ($filters as $key => $label)
                        <a href="{{ route('admin.support.inbox', ['filter' => $key, 'q' => $q]) }}"
                           class="text-xs font-semibold px-2.5 py-1 rounded-full {{ $filter === $key ? 'bg-brand text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-brand-muted' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
                <ul class="flex-1 overflow-y-auto divide-y divide-slate-200/60 max-h-[30rem] lg:max-h-[38rem]">
                    @forelse ($conversations as $item)
                        <li>
                            <a href="{{ $item['url'] }}?filter={{ urlencode($filter) }}&q={{ urlencode($q) }}"
                               class="block px-3 py-2.5 hover:bg-white/90 {{ ($activeId ?? null) === $item['id'] ? 'bg-white shadow-sm ring-1 ring-inset ring-brand/15' : '' }}">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-[15px] font-semibold text-slate-900 truncate leading-snug">{{ $item['name'] }}</p>
                                    @if (($item['unread'] ?? 0) > 0)
                                        <span class="shrink-0 inline-flex min-w-[1.25rem] h-5 items-center justify-center rounded-full bg-brand text-white text-[11px] font-bold px-1">{{ $item['unread'] }}</span>
                                    @endif
                                </div>
                                <p class="text-sm text-slate-600 mt-0.5 truncate">{{ $item['preview'] ?: 'No messages yet' }}</p>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    {{ $item['desk_state'] ?? ucfirst($item['status']) }}
                                    @if ($item['waiting_label'] ?? null) · {{ $item['waiting_label'] }} @endif
                                </p>
                            </a>
                        </li>
                    @empty
                        <li class="px-4 py-10 text-center text-sm text-slate-500">No conversations for this filter.</li>
                    @endforelse
                </ul>
            </aside>

            {{-- CONVERSATION --}}
            <section class="flex flex-col min-h-[28rem] bg-white {{ $conversation ? '' : 'hidden lg:flex' }}">
                @if ($conversation)
                    <div class="px-3 py-2.5 border-b border-slate-200/80 flex items-start justify-between gap-3 bg-white">
                        <div class="min-w-0">
                            <a href="{{ route('admin.support.inbox', ['filter' => $filter, 'q' => $q]) }}" class="lg:hidden text-sm font-semibold text-brand">← Conversations</a>
                            <p class="text-[11px] uppercase tracking-[0.16em] font-semibold text-slate-500">Conversation</p>
                            <p class="text-base font-bold text-slate-900 truncate">{{ $serialized['name'] ?? 'Conversation' }}</p>
                            <p class="text-sm text-slate-500">
                                {{ $serialized['desk_state'] ?? ucfirst($conversation->status) }}
                                @if (! empty($serialized['topic'])) · {{ $serialized['topic'] }} @endif
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-1.5 shrink-0">
                            @if (! $conversation->assigned_to)
                                <form method="POST" action="{{ route('admin.support.inbox.accept', $conversation) }}">
                                    @csrf
                                    <button class="rounded-lg ring-1 ring-brand/20 text-brand text-xs font-semibold px-3 py-1.5 hover:bg-brand-muted/40">Accept</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('admin.support.inbox.resolve', $conversation) }}"
                                  onsubmit="event.preventDefault(); confirmForm(this, { title: 'Resolve conversation?', message: 'Use this when the issue is answered here. Create a case only if investigation or another department is needed.' })">
                                @csrf
                                <button class="rounded-lg ring-1 ring-emerald-200 text-emerald-800 text-xs font-semibold px-3 py-1.5 hover:bg-emerald-50">Resolve</button>
                            </form>
                            <form method="POST" action="{{ route('admin.support.inbox.create-case', $conversation) }}">
                                @csrf
                                <button class="rounded-lg bg-brand text-white text-xs font-semibold px-3 py-1.5 hover:brightness-95">Create case</button>
                            </form>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto px-3 py-3 space-y-2 bg-[#f7f8fa] max-h-[22rem] lg:max-h-[28rem]">
                        @forelse ($conversation->messages as $message)
                            @php
                                $staff = in_array($message->sender_type, ['staff', 'bot'], true);
                            @endphp
                            <div class="flex {{ $staff ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-[min(18rem,78%)] rounded-2xl px-3 py-2 text-[15px] leading-snug whitespace-pre-wrap shadow-sm
                                    {{ $staff ? 'bg-brand text-white rounded-br-md' : 'bg-white text-slate-900 rounded-bl-md ring-1 ring-slate-200/70' }}">
                                    <p>{{ $message->body }}</p>
                                    <p class="text-xs mt-1 text-right {{ $staff ? 'text-white/70' : 'text-slate-400' }}">
                                        {{ $message->created_at?->format('H:i') }}
                                    </p>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500 text-center py-10">No messages yet.</p>
                        @endforelse
                    </div>

                    <div class="border-t border-slate-200/80 p-3 space-y-2 bg-white">
                        @if (! empty($quickReplies))
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($quickReplies as $qr)
                                    <button type="button"
                                            @click="insertQuick(@js($qr['key']))"
                                            class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 hover:bg-brand-muted">
                                        {{ $qr['label_'.$locale] ?? $qr['label_sw'] }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                        <form method="POST" action="{{ route('admin.support.inbox.reply', $conversation) }}" class="flex gap-2 items-end">
                            @csrf
                            <textarea name="body" x-model="draft" x-ref="composer" rows="2" required maxlength="5000"
                                      placeholder="Write a reply… quick replies insert here for edit before send"
                                      class="flex-1 rounded-xl border-slate-200 text-base focus:ring-brand/30"></textarea>
                            <button type="submit" class="shrink-0 rounded-xl bg-brand-gold text-brand font-semibold text-sm px-4 py-2.5 hover:brightness-95">Send</button>
                        </form>
                    </div>
                @else
                    <div class="flex-1 grid place-items-center px-6 py-16 text-center bg-[#f7f8fa]">
                        <div>
                            <p class="text-[11px] uppercase tracking-[0.16em] font-semibold text-slate-500">Conversation</p>
                            <p class="text-base font-semibold text-slate-900 mt-1">Select a conversation</p>
                            <p class="text-sm text-slate-500 mt-1 max-w-xs">Waiting and unread chats appear on the left. Accept before introducing yourself by name.</p>
                        </div>
                    </div>
                @endif
            </section>

            {{-- CUSTOMER DETAILS --}}
            <aside class="bg-slate-50/50 border-t lg:border-t-0 lg:border-l border-slate-200/80 p-4 space-y-3 {{ $conversation ? '' : 'hidden lg:block' }}">
                <p class="text-[11px] uppercase tracking-[0.16em] font-semibold text-slate-500">Customer details</p>
                @if ($conversation && $context)
                    <div>
                        <p class="text-base font-bold text-slate-900">{{ $context['name'] }}</p>
                        @if (($context['kind'] ?? '') === 'member')
                            <p class="text-sm text-slate-600 mt-0.5">Member · {{ $context['member_number'] ?: '—' }}</p>
                            <p class="text-sm text-slate-600">{{ $context['phone'] ?: '—' }}</p>
                            @if (! empty($context['member_url']))
                                <a href="{{ $context['member_url'] }}" class="inline-flex mt-2 text-sm font-semibold text-brand hover:underline">Open Member 360 →</a>
                            @endif
                        @else
                            <p class="text-sm font-semibold text-amber-700 mt-0.5">Guest / Non-member</p>
                            <p class="text-sm text-slate-600">{{ $context['phone'] ?: '—' }}</p>
                        @endif
                    </div>

                    @if (! empty($context['application']))
                        <div class="rounded-xl bg-white ring-1 ring-slate-200/80 p-3 text-sm space-y-1">
                            <p class="text-[11px] uppercase tracking-widest text-slate-500 font-semibold">Current application</p>
                            <p class="font-semibold text-slate-900">{{ $context['application']['number'] }}</p>
                            <p class="text-slate-600">{{ ucfirst(str_replace('_', ' ', (string) $context['application']['stage'])) }}</p>
                            @if ($context['application']['amount'])
                                <p class="text-slate-600">Applied: TZS {{ number_format((float) $context['application']['amount']) }}</p>
                            @endif
                            <a href="{{ $context['application']['url'] }}" class="inline-flex mt-1 font-semibold text-brand hover:underline">View application →</a>
                        </div>
                    @endif

                    @if (! empty($context['loan']))
                        <div class="rounded-xl bg-white ring-1 ring-slate-200/80 p-3 text-sm space-y-1">
                            <p class="text-[11px] uppercase tracking-widest text-slate-500 font-semibold">Active loan</p>
                            <p class="font-semibold text-slate-900">{{ $context['loan']['number'] }}</p>
                            <p class="text-slate-600">TZS {{ number_format((float) ($context['loan']['outstanding'] ?? 0)) }} outstanding</p>
                            <a href="{{ $context['loan']['url'] }}" class="inline-flex mt-1 font-semibold text-brand hover:underline">View loan →</a>
                        </div>
                    @endif

                    @if (! empty($context['payment']))
                        <div class="rounded-xl bg-white ring-1 ring-slate-200/80 p-3 text-sm space-y-1">
                            <p class="text-[11px] uppercase tracking-widest text-slate-500 font-semibold">Recent payment</p>
                            <p class="font-semibold text-slate-900">TZS {{ number_format((float) $context['payment']['amount']) }}</p>
                            <p class="text-slate-600">{{ $context['payment']['paid_at'] ?: '—' }}</p>
                        </div>
                    @endif

                    <div class="text-sm space-y-1.5">
                        <p class="text-[11px] uppercase tracking-widest text-slate-500 font-semibold">Open cases ({{ count($context['open_cases'] ?? []) }})</p>
                        @forelse ($context['open_cases'] ?? [] as $case)
                            <a href="{{ $case['url'] }}" class="block font-semibold text-brand hover:underline">{{ $case['number'] }} · {{ $case['subject'] }}</a>
                        @empty
                            <p class="text-slate-400">None</p>
                        @endforelse
                        <p class="text-[11px] uppercase tracking-widest text-slate-500 font-semibold pt-2">Previous cases ({{ count($context['previous_cases'] ?? []) }})</p>
                        @forelse ($context['previous_cases'] ?? [] as $case)
                            <a href="{{ $case['url'] }}" class="block text-slate-600 hover:underline">{{ $case['number'] }} · {{ $case['subject'] }}</a>
                        @empty
                            <p class="text-slate-400">None</p>
                        @endforelse
                    </div>
                @else
                    <p class="text-sm text-slate-500">Customer journey appears when a conversation is open.</p>
                @endif
            </aside>
        </div>
    </div>
</x-admin.layout>
