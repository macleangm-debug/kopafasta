@php
    $filters = [
        'all' => 'All',
        'unread' => 'Unread',
        'waiting' => 'Waiting',
        'mine' => 'Mine',
        'cases' => 'Cases',
    ];
    $locale = str_starts_with(app()->getLocale(), 'en') ? 'en' : 'sw';
@endphp
<x-admin.layout title="Inbox" heading="" subheading="">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <x-admin.letterhead
            kicker="Customer Support"
            title="Inbox"
            subtitle="Conversations · reply · context. One messaging workspace." />
    </div>

    <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden"
         x-data="{
            draft: '',
            insertQuick(body) { this.draft = body; $refs.composer?.focus(); }
         }">
        <div class="grid lg:grid-cols-[minmax(0,18rem)_minmax(0,1fr)_minmax(0,16rem)] min-h-[32rem]">
            {{-- Left: conversation list --}}
            <aside class="border-b lg:border-b-0 lg:border-r border-gray-100 flex flex-col {{ $conversation ? 'hidden lg:flex' : 'flex' }}">
                <form method="GET" action="{{ route('admin.support.inbox') }}" class="p-3 space-y-2 border-b border-gray-100">
                    <input type="search" name="q" value="{{ $q }}" placeholder="Search name, phone, topic…"
                           class="w-full rounded-xl border-gray-200 text-sm focus:ring-brand/30">
                    <input type="hidden" name="filter" value="{{ $filter }}">
                </form>
                <div class="flex flex-wrap gap-1 px-3 py-2 border-b border-gray-100">
                    @foreach ($filters as $key => $label)
                        <a href="{{ route('admin.support.inbox', ['filter' => $key, 'q' => $q]) }}"
                           class="text-[11px] font-semibold px-2.5 py-1 rounded-full {{ $filter === $key ? 'bg-brand text-white' : 'bg-gray-100 text-gray-600 hover:bg-brand-muted' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
                <ul class="flex-1 overflow-y-auto divide-y divide-gray-50 max-h-[28rem] lg:max-h-[36rem]">
                    @forelse ($conversations as $item)
                        <li>
                            <a href="{{ $item['url'] }}?filter={{ urlencode($filter) }}&q={{ urlencode($q) }}"
                               class="block px-3 py-3 hover:bg-brand-muted/25 {{ ($activeId ?? null) === $item['id'] ? 'bg-brand-muted/40' : '' }}">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-sm font-semibold text-gray-900 truncate">{{ $item['name'] }}</p>
                                    @if (($item['unread'] ?? 0) > 0)
                                        <span class="shrink-0 inline-flex min-w-[1.25rem] h-5 items-center justify-center rounded-full bg-brand text-white text-[10px] font-bold px-1.5">{{ $item['unread'] }}</span>
                                    @endif
                                </div>
                                @if (! empty($item['topic']))
                                    <p class="text-[11px] text-brand font-medium truncate">{{ $item['topic'] }}</p>
                                @endif
                                <p class="text-xs text-gray-500 mt-0.5 truncate">{{ $item['preview'] ?: 'No messages yet' }}</p>
                                <p class="text-[10px] text-gray-400 mt-1">
                                    @if ($item['needs_human']) Waiting @else {{ ucfirst($item['status']) }} @endif
                                    @if ($item['waiting_label']) · {{ $item['waiting_label'] }} @endif
                                    @if (! empty($item['guest_label'])) · Guest @endif
                                </p>
                            </a>
                        </li>
                    @empty
                        <li class="px-4 py-10 text-center text-sm text-gray-500">
                            No conversations for this filter.
                        </li>
                    @endforelse
                </ul>
            </aside>

            {{-- Centre: chat --}}
            <section class="flex flex-col min-h-[28rem] {{ $conversation ? '' : 'hidden lg:flex' }}">
                @if ($conversation)
                    <div class="px-4 py-3 border-b border-gray-100 flex items-start justify-between gap-3">
                        <div>
                            <a href="{{ route('admin.support.inbox', ['filter' => $filter, 'q' => $q]) }}" class="lg:hidden text-xs font-semibold text-brand">← Inbox</a>
                            <p class="text-sm font-bold text-gray-900">{{ $serialized['name'] ?? 'Conversation' }}</p>
                            <p class="text-xs text-gray-500">
                                {{ ($serialized['is_member'] ?? false) ? 'Member' : 'Guest / Non-member' }}
                                @if (! empty($serialized['topic'])) · {{ $serialized['topic'] }} @endif
                                @if ($conversation->needs_human) · waiting @endif
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2 shrink-0">
                            @if (! $conversation->assigned_to)
                                <form method="POST" action="{{ route('admin.support.inbox.accept', $conversation) }}">
                                    @csrf
                                    <button class="rounded-xl ring-1 ring-brand/20 text-brand text-xs font-semibold px-3 py-2 hover:bg-brand-muted/40">Accept</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('admin.support.inbox.create-case', $conversation) }}">
                                @csrf
                                <button class="rounded-xl bg-brand text-white text-xs font-semibold px-3 py-2 hover:brightness-95">Create case</button>
                            </form>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto px-4 py-4 space-y-3 bg-gradient-to-b from-gray-50/80 to-white max-h-[22rem] lg:max-h-[28rem]">
                        @forelse ($conversation->messages as $message)
                            @php $staff = $message->sender_type === 'staff'; @endphp
                            <div class="flex {{ $staff ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-[85%] rounded-2xl px-3.5 py-2.5 text-sm whitespace-pre-wrap {{ $staff ? 'bg-brand text-white rounded-br-md' : ($message->sender_type === 'bot' ? 'bg-white ring-1 ring-gray-200 text-gray-700' : 'bg-sky-50 text-gray-900 rounded-bl-md') }}">
                                    <p>{{ $message->body }}</p>
                                    <p class="text-[10px] mt-1 {{ $staff ? 'text-white/70' : 'text-gray-400' }}">
                                        {{ $staff ? ($message->senderUser?->name ?: 'Support') : ($message->sender_type === 'bot' ? 'Bot' : 'Member') }}
                                        · {{ $message->created_at?->format('d M H:i') }}
                                    </p>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 text-center py-10">No messages yet.</p>
                        @endforelse
                    </div>

                    <div class="border-t border-gray-100 p-3 space-y-2">
                        @if (! empty($quickReplies))
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($quickReplies as $qr)
                                    <button type="button"
                                            @click="insertQuick(@js($qr['body_'.$locale] ?? $qr['body_sw']))"
                                            class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-brand-muted/70 text-brand hover:bg-brand/10">
                                        {{ $qr['label_'.$locale] ?? $qr['label_sw'] }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                        <form method="POST" action="{{ route('admin.support.inbox.reply', $conversation) }}" class="flex gap-2 items-end">
                            @csrf
                            <textarea name="body" x-model="draft" x-ref="composer" rows="2" required maxlength="5000"
                                      placeholder="Write a reply… (quick replies insert here for edit before send)"
                                      class="flex-1 rounded-xl border-gray-200 text-sm focus:ring-brand/30"></textarea>
                            <button type="submit" class="shrink-0 rounded-xl bg-brand-gold text-brand font-semibold text-sm px-4 py-2.5 hover:brightness-95">Send</button>
                        </form>
                    </div>
                @else
                    <div class="flex-1 grid place-items-center px-6 py-16 text-center">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Select a conversation</p>
                            <p class="text-xs text-gray-500 mt-1 max-w-xs">Open a waiting or unread chat from the list. Member messages appear here as bubbles.</p>
                        </div>
                    </div>
                @endif
            </section>

            {{-- Right: context --}}
            <aside class="border-t lg:border-t-0 lg:border-l border-gray-100 p-4 space-y-4 {{ $conversation ? '' : 'hidden lg:block' }}">
                @if ($conversation && $context)
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Customer</p>
                        <p class="text-sm font-bold text-gray-900 mt-1">{{ $context['name'] }}</p>
                        @if (($context['kind'] ?? '') === 'member')
                            <p class="text-xs text-gray-500 mt-0.5">Member · {{ $context['member_number'] ?: '—' }}</p>
                            <p class="text-xs text-gray-500">{{ $context['phone'] ?: '—' }}</p>
                            @if (! empty($context['member_url']))
                                <a href="{{ $context['member_url'] }}" class="inline-flex mt-2 text-xs font-semibold text-brand hover:underline">Open Member 360 →</a>
                            @endif
                        @else
                            <p class="text-xs font-semibold text-amber-700 mt-0.5">Guest / Non-member</p>
                            <p class="text-xs text-gray-500">{{ $context['phone'] ?: '—' }}</p>
                            <p class="text-xs text-gray-500">{{ $context['email'] ?: '—' }}</p>
                        @endif
                    </div>

                    @if (! empty($context['application']))
                        <div class="rounded-xl bg-gray-50 p-3 text-xs space-y-1">
                            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Current journey</p>
                            <p class="font-semibold text-gray-900">Loan application {{ $context['application']['number'] }}</p>
                            <p class="text-gray-600">{{ ucfirst(str_replace('_', ' ', (string) $context['application']['stage'])) }} · {{ display_label($context['application']['status'], 'application_status') }}</p>
                            @if ($context['application']['amount'])
                                <p class="text-gray-600">Applied: TZS {{ number_format((float) $context['application']['amount']) }}</p>
                            @endif
                            <a href="{{ $context['application']['url'] }}" class="inline-flex mt-1 font-semibold text-brand hover:underline">View application →</a>
                        </div>
                    @endif

                    @if (! empty($context['loan']))
                        <div class="rounded-xl bg-gray-50 p-3 text-xs space-y-1">
                            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Active loan</p>
                            <p class="font-semibold text-gray-900">{{ $context['loan']['number'] }}</p>
                            <p class="text-gray-600">TZS {{ number_format((float) ($context['loan']['outstanding'] ?? 0)) }} outstanding · {{ ucfirst($context['loan']['status']) }}</p>
                            <a href="{{ $context['loan']['url'] }}" class="inline-flex mt-1 font-semibold text-brand hover:underline">View loan →</a>
                        </div>
                    @endif

                    @if (! empty($context['payment']))
                        <div class="rounded-xl bg-gray-50 p-3 text-xs space-y-1">
                            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Recent payment</p>
                            <p class="font-semibold text-gray-900">TZS {{ number_format((float) $context['payment']['amount']) }}</p>
                            <p class="text-gray-600">{{ $context['payment']['paid_at'] ?: '—' }}</p>
                            <a href="{{ $context['payment']['url'] }}" class="inline-flex mt-1 font-semibold text-brand hover:underline">View payment →</a>
                        </div>
                    @endif

                    <div class="text-xs space-y-2">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Open cases ({{ count($context['open_cases'] ?? []) }})</p>
                        @forelse ($context['open_cases'] ?? [] as $case)
                            <a href="{{ $case['url'] }}" class="block font-semibold text-brand hover:underline">{{ $case['number'] }} · {{ $case['subject'] }}</a>
                        @empty
                            <p class="text-gray-400">None</p>
                        @endforelse
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold pt-2">Previous cases ({{ count($context['previous_cases'] ?? []) }})</p>
                        @forelse ($context['previous_cases'] ?? [] as $case)
                            <a href="{{ $case['url'] }}" class="block text-gray-600 hover:underline">{{ $case['number'] }} · {{ $case['subject'] }}</a>
                        @empty
                            <p class="text-gray-400">None</p>
                        @endforelse
                    </div>
                @else
                    <p class="text-xs text-gray-500">Customer context appears when a conversation is open.</p>
                @endif
            </aside>
        </div>
    </div>
</x-admin.layout>
