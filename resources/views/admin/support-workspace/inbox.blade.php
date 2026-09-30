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
    $seedMessages = [];
    if ($conversation) {
        foreach ($conversation->messages as $message) {
            $seedMessages[] = [
                'id' => (int) $message->id,
                'role' => in_array($message->sender_type, ['staff', 'bot'], true) ? 'bot' : 'user',
                'text' => (string) $message->body,
                'time' => $message->created_at ? format_app_datetime($message->created_at, 'H:i') : null,
            ];
        }
    }
@endphp
<x-admin.layout title="Inbox" heading="" subheading="">
    <x-admin.letterhead
        kicker="Customer Support"
        title="Support Inbox"
        subtitle="Conversations · live chat · customer journey — one operational desk"
    >
        <x-slot:actions>
            <a href="{{ route('admin.support.interactions.new') }}"
               class="inline-flex rounded-xl bg-brand-gold text-brand text-sm font-semibold px-4 py-2.5 hover:brightness-95">
                + New interaction
            </a>
        </x-slot:actions>
    </x-admin.letterhead>

    @if (session('error'))
        <div class="mb-3 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-900">{{ session('error') }}</div>
    @endif
    @if (session('status'))
        <div class="mb-3 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-950">{{ session('status') }}</div>
    @endif

    @if ($specialistFollowUps > 0)
        <div class="mb-3 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-950">
            <span class="font-semibold">{{ $specialistFollowUps }}</span> specialist response(s) awaiting Support follow-up on open cases.
        </div>
    @endif

    <div class="w-full rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden"
         x-data="supportInboxDesk(@js([
             'draft' => '',
             'bodies' => $quickReplyBodies,
             'messages' => $seedMessages,
             'conversationId' => $conversation?->id,
             'threadUrl' => $conversation ? route('admin.support.inbox.thread', $conversation) : null,
             'replyUrl' => $conversation ? route('admin.support.inbox.reply', $conversation) : null,
             'acceptUrl' => $conversation ? route('admin.support.inbox.accept', $conversation) : null,
             'csrf' => csrf_token(),
             'pollMs' => 2000,
             'deskState' => $serialized['desk_state'] ?? ($conversation ? ucfirst($conversation->status) : ''),
             'activePreview' => $serialized['preview'] ?? '',
         ]))">
        {{-- Full Admin width · ~25% / 47% / 28% --}}
        <div class="grid lg:grid-cols-[minmax(14rem,0.95fr)_minmax(0,1.75fr)_minmax(14rem,1.05fr)] min-h-[36rem]">
            {{-- CONVERSATIONS --}}
            <aside class="bg-slate-50/90 border-b lg:border-b-0 lg:border-r border-slate-200/80 flex flex-col {{ $conversation ? 'hidden lg:flex' : 'flex' }}">
                <div class="px-4 pt-4 pb-2 border-b border-slate-200/70 bg-white/70">
                    <p class="text-[11px] uppercase tracking-[0.16em] font-semibold text-slate-500">Conversations</p>
                </div>
                <form method="GET" action="{{ route('admin.support.inbox') }}" class="px-3 pt-3 pb-2 space-y-2">
                    <input type="search" name="q" value="{{ $q }}" placeholder="Search name, phone, message…"
                           class="w-full rounded-lg border-slate-200 text-sm bg-white focus:ring-brand/30">
                    <input type="hidden" name="filter" value="{{ $filter }}">
                </form>
                <div class="flex flex-wrap gap-1 px-3 pb-3">
                    @foreach ($filters as $key => $label)
                        <a href="{{ route('admin.support.inbox', ['filter' => $key, 'q' => $q]) }}"
                           class="text-xs font-semibold px-2.5 py-1 rounded-full {{ $filter === $key ? 'bg-brand text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-brand-muted' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
                <ul class="flex-1 overflow-y-auto divide-y divide-slate-200/60 max-h-[32rem] lg:max-h-[40rem]">
                    @forelse ($conversations as $item)
                        <li>
                            <a href="{{ $item['url'] }}?filter={{ urlencode($filter) }}&q={{ urlencode($q) }}"
                               class="block px-3 py-2.5 hover:bg-white/90 {{ ($activeId ?? null) === $item['id'] ? 'bg-white shadow-sm ring-1 ring-inset ring-brand/15' : '' }}"
                               @if (($activeId ?? null) === $item['id']) data-active-conversation @endif>
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-[15px] font-semibold text-slate-900 truncate leading-snug">{{ $item['name'] }}</p>
                                    @if (($item['unread'] ?? 0) > 0)
                                        <span class="shrink-0 inline-flex min-w-[1.25rem] h-5 items-center justify-center rounded-full bg-brand text-white text-[11px] font-bold px-1"
                                              @if (($activeId ?? null) === $item['id']) x-show="unreadBadge > 0" x-text="unreadBadge || {{ (int) $item['unread'] }}" @endif>{{ $item['unread'] }}</span>
                                    @endif
                                </div>
                                <p class="text-sm text-slate-600 mt-0.5 truncate"
                                   @if (($activeId ?? null) === $item['id']) x-text="activePreview || @js($item['preview'] ?: 'No messages yet')" @endif>{{ $item['preview'] ?: 'No messages yet' }}</p>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    <span @if (($activeId ?? null) === $item['id']) x-text="deskState || @js($item['desk_state'] ?? ucfirst($item['status']))" @endif>{{ $item['desk_state'] ?? ucfirst($item['status']) }}</span>
                                    @if ($item['waiting_label'] ?? null) · {{ $item['waiting_label'] }} @endif
                                    <span class="text-slate-400"> · #{{ $item['id'] }}</span>
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
                    <div class="px-4 py-3 border-b border-slate-200/80 flex items-start justify-between gap-3 bg-white">
                        <div class="min-w-0">
                            <a href="{{ route('admin.support.inbox', ['filter' => $filter, 'q' => $q]) }}" class="lg:hidden text-sm font-semibold text-brand">← Conversations</a>
                            <p class="text-[11px] uppercase tracking-[0.16em] font-semibold text-slate-500">Conversation</p>
                            <p class="text-base font-bold text-slate-900 truncate">{{ $serialized['name'] ?? 'Conversation' }}</p>
                            <p class="text-sm text-slate-500">
                                <span x-text="deskState || @js($serialized['desk_state'] ?? ucfirst($conversation->status))"></span>
                                @if (! empty($serialized['topic'])) · {{ $serialized['topic'] }} @endif
                                · #<span x-text="conversationId || {{ (int) $conversation->id }}"></span>
                            </p>
                            <p class="text-xs text-red-700 mt-1" x-show="error" x-text="error" x-cloak></p>
                        </div>
                        <div class="flex flex-wrap gap-1.5 shrink-0">
                            @if (! $conversation->assigned_to)
                                <button type="button" @click="acceptConversation()" :disabled="sending"
                                        class="rounded-lg ring-1 ring-brand/20 text-brand text-xs font-semibold px-3 py-1.5 hover:bg-brand-muted/40 disabled:opacity-60">Accept</button>
                            @endif
                            <div x-data="{ resolveOpen: false }" class="relative">
                                <button type="button" @click="resolveOpen = !resolveOpen"
                                        class="rounded-lg ring-1 ring-emerald-200 text-emerald-800 text-xs font-semibold px-3 py-1.5 hover:bg-emerald-50">Resolve</button>
                                <div x-show="resolveOpen" x-cloak @click.outside="resolveOpen = false"
                                     class="absolute right-0 top-full mt-2 z-20 w-72 rounded-xl bg-white ring-1 ring-emerald-200 shadow-lg p-3 space-y-2">
                                    <form method="POST" action="{{ route('admin.support.inbox.resolve', $conversation) }}"
                                          onsubmit="event.preventDefault(); confirmForm(this, { title: 'Resolve conversation?', message: 'Member receives a resolution message and optional rating. Next Talk to Support starts a new thread. History is preserved.' })">
                                        @csrf
                                        <label class="block text-[11px] font-semibold text-gray-700">Resolution category
                                            <select name="resolution_category" required class="mt-1 w-full rounded-lg border-gray-200 text-xs">
                                                <option value="answered">Answered directly</option>
                                                <option value="guidance_provided">Guidance provided</option>
                                                <option value="technical_fixed">Technical fixed</option>
                                                <option value="payment_clarified">Payment clarified</option>
                                                <option value="application_clarified">Application clarified</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </label>
                                        <label class="block text-[11px] font-semibold text-gray-700 mt-2">Note (optional)
                                            <textarea name="note" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border-gray-200 text-xs"></textarea>
                                        </label>
                                        <label class="inline-flex items-center gap-2 text-[11px] text-gray-700 mt-2">
                                            <input type="checkbox" name="ask_rating" value="1" checked class="rounded border-gray-300 text-brand">
                                            Ask 1–5 rating
                                        </label>
                                        <button class="mt-2 w-full rounded-lg bg-emerald-700 text-white text-xs font-semibold px-3 py-2">Confirm resolve</button>
                                    </form>
                                </div>
                            </div>
                            <div x-data="{ caseOpen: false }" class="relative">
                                <button type="button" @click="caseOpen = !caseOpen"
                                        class="rounded-lg bg-brand text-white text-xs font-semibold px-3 py-1.5 hover:brightness-95">Create case</button>
                                <div x-show="caseOpen" x-cloak @click.outside="caseOpen = false"
                                     class="absolute right-0 top-full mt-2 z-20 w-80 rounded-xl bg-white ring-1 ring-brand/20 shadow-lg p-3 space-y-2">
                                    <form method="POST" action="{{ route('admin.support.inbox.create-case', $conversation) }}" class="space-y-2"
                                          onsubmit="event.preventDefault(); confirmForm(this, { title: 'Create follow-up case?', message: 'Use a case only when investigation or another department must own follow-up. Ordinary answers should stay as conversation only.' })">
                                        @csrf
                                        <label class="block text-[11px] font-semibold text-gray-700">Subject
                                            <input type="text" name="subject" value="{{ $conversation->topic }}" maxlength="180" class="mt-1 w-full rounded-lg border-gray-200 text-xs">
                                        </label>
                                        <label class="block text-[11px] font-semibold text-gray-700">Category
                                            <select name="category" class="mt-1 w-full rounded-lg border-gray-200 text-xs">
                                                <option value="general">General</option>
                                                <option value="complaint">Complaint</option>
                                                <option value="technical">Technical</option>
                                                <option value="payment">Payment</option>
                                                <option value="loan">Loan</option>
                                                <option value="partner">Partner</option>
                                            </select>
                                        </label>
                                        <label class="block text-[11px] font-semibold text-gray-700">Related record
                                            <select name="related_type" class="mt-1 w-full rounded-lg border-gray-200 text-xs">
                                                <option value="">None</option>
                                                <option value="application">Application</option>
                                                <option value="loan">Loan</option>
                                                <option value="payment">Payment</option>
                                                <option value="account">Profile / account</option>
                                            </select>
                                        </label>
                                        <label class="block text-[11px] font-semibold text-gray-700">Related ID (optional)
                                            <input type="number" name="related_id" class="mt-1 w-full rounded-lg border-gray-200 text-xs">
                                        </label>
                                        <label class="block text-[11px] font-semibold text-gray-700">Priority
                                            <select name="priority" class="mt-1 w-full rounded-lg border-gray-200 text-xs">
                                                <option value="normal">Normal</option>
                                                <option value="low">Low</option>
                                                <option value="high">High</option>
                                                <option value="urgent">Urgent</option>
                                            </select>
                                        </label>
                                        <label class="block text-[11px] font-semibold text-gray-700">Summary
                                            <textarea name="body" rows="3" maxlength="5000" class="mt-1 w-full rounded-lg border-gray-200 text-xs" placeholder="Prefill from conversation if blank"></textarea>
                                        </label>
                                        <button class="w-full rounded-lg bg-brand text-white text-xs font-semibold px-3 py-2">Create case</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto px-4 py-3 space-y-2 bg-[#f7f8fa] max-h-[24rem] lg:max-h-[30rem]" x-ref="scroll">
                        <template x-for="msg in messages" :key="msg.id || msg.text + (msg.time || '')">
                            <div class="flex" :class="msg.role === 'bot' ? 'justify-end' : 'justify-start'">
                                <div :class="msg.role === 'bot' ? 'kf-support-bubble kf-support-bubble--outbound' : 'kf-support-bubble kf-support-bubble--inbound'">
                                    <div class="kf-support-bubble__body whitespace-pre-wrap" x-text="msg.text"></div>
                                    <p class="kf-support-bubble__time" x-text="msg.time || ''"></p>
                                </div>
                            </div>
                        </template>
                        <p class="text-sm text-slate-500 text-center py-10" x-show="!messages.length">No messages yet.</p>
                    </div>

                    <div class="border-t border-slate-200/80 p-4 space-y-3 bg-white">
                        @if (! empty($quickReplies))
                            <div x-data="{ tq: '', replies: @js(collect($quickReplies)->map(fn ($qr) => [
                                'key' => $qr['key'],
                                'group' => $qr['group'] ?? '',
                                'label' => $qr['label_'.$locale] ?? $qr['label_sw'],
                            ])->values()) }" class="space-y-2">
                                <input type="search" x-model="tq" placeholder="Search templates (SW/EN)…"
                                       class="w-full rounded-lg border-slate-200 text-xs">
                                <div class="flex flex-wrap gap-1.5 max-h-24 overflow-y-auto">
                                    <template x-for="qr in replies.filter(r => !tq || (r.label + ' ' + r.group + ' ' + r.key).toLowerCase().includes(tq.toLowerCase()))" :key="qr.key">
                                        <button type="button" @click="insertQuick(qr.key)"
                                                class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 hover:bg-brand-muted"
                                                x-text="qr.label"></button>
                                    </template>
                                </div>
                            </div>
                        @endif
                        <form @submit.prevent="sendReply()" class="flex gap-3 items-end">
                            <textarea name="body" x-model="draft" x-ref="composer" rows="3" required maxlength="5000"
                                      placeholder="Write a reply… quick replies insert here for edit before send"
                                      class="flex-1 min-h-[4.5rem] rounded-xl border-slate-200 text-base focus:ring-brand/30"></textarea>
                            <button type="submit" :disabled="sending || !draft.trim()"
                                    class="shrink-0 rounded-xl bg-brand-gold text-brand font-semibold text-sm px-5 py-3 hover:brightness-95 disabled:opacity-60">Send</button>
                        </form>
                    </div>
                @else
                    <div class="flex-1 grid place-items-center px-6 py-16 text-center bg-[#f7f8fa]">
                        <div>
                            <p class="text-[11px] uppercase tracking-[0.16em] font-semibold text-slate-500">Conversation</p>
                            <p class="text-base font-semibold text-slate-900 mt-1">Select a conversation</p>
                            <p class="text-sm text-slate-500 mt-1 max-w-xs">Waiting and unread chats appear on the left. Select a Support staff member, then Accept before introducing yourself by name.</p>
                        </div>
                    </div>
                @endif
            </section>

            {{-- CUSTOMER DETAILS --}}
            <aside class="bg-slate-50/70 border-t lg:border-t-0 lg:border-l border-slate-200/80 p-4 space-y-3 {{ $conversation ? '' : 'hidden lg:block' }}">
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

    @once
        <script>
            document.addEventListener('alpine:init', function () {
                Alpine.data('supportInboxDesk', function (config) {
                    return {
                        draft: config.draft || '',
                        bodies: config.bodies || {},
                        messages: Array.isArray(config.messages) ? config.messages.slice() : [],
                        conversationId: config.conversationId || null,
                        threadUrl: config.threadUrl,
                        replyUrl: config.replyUrl,
                        acceptUrl: config.acceptUrl,
                        csrf: config.csrf,
                        pollMs: config.pollMs || 2000,
                        sending: false,
                        error: '',
                        deskState: config.deskState || '',
                        activePreview: config.activePreview || '',
                        unreadBadge: 0,
                        _timer: null,
                        csrfToken() {
                            var meta = document.querySelector('meta[name="csrf-token"]');
                            if (meta && meta.content) return meta.content;
                            var match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
                            if (match) {
                                try { return decodeURIComponent(match[1]); } catch (e) { return match[1]; }
                            }
                            return this.csrf;
                        },
                        insertQuick(key) {
                            this.draft = this.bodies[key] || '';
                            this.$refs.composer?.focus();
                        },
                        mapMessages(list) {
                            return (list || []).map(function (m) {
                                return {
                                    id: m.id,
                                    role: m.role,
                                    text: m.text,
                                    time: m.time || (m.at ? String(m.at).slice(11, 16) : ''),
                                };
                            });
                        },
                        scrollBottom() {
                            var self = this;
                            this.$nextTick(function () {
                                if (self.$refs.scroll) self.$refs.scroll.scrollTop = self.$refs.scroll.scrollHeight;
                            });
                        },
                        async poll() {
                            if (!this.threadUrl) return;
                            try {
                                var res = await fetch(this.threadUrl + (this.threadUrl.indexOf('?') >= 0 ? '&' : '?') + '_=' + Date.now(), {
                                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                    credentials: 'same-origin',
                                    cache: 'no-store',
                                });
                                if (!res.ok) return;
                                var data = await res.json();
                                if (!data.ok || !data.messages) return;
                                if (data.conversation_id && this.conversationId && Number(data.conversation_id) !== Number(this.conversationId)) {
                                    this.error = 'This desk is on conversation #' + this.conversationId + ' but the member thread is #' + data.conversation_id + '. Re-open the latest conversation.';
                                    return;
                                }
                                var next = this.mapMessages(data.messages);
                                var prevSig = this.messages.map(function (m) { return String(m.id || '') + ':' + (m.text || ''); }).join('|');
                                var nextSig = next.map(function (m) { return String(m.id || '') + ':' + (m.text || ''); }).join('|');
                                if (prevSig !== nextSig) {
                                    this.messages = next;
                                    this.scrollBottom();
                                }
                                if (data.desk_state) this.deskState = data.desk_state;
                                if (data.conversation_id) this.conversationId = data.conversation_id;
                                var lastCustomer = null;
                                for (var i = next.length - 1; i >= 0; i--) {
                                    if (next[i].role === 'user') { lastCustomer = next[i]; break; }
                                }
                                if (lastCustomer) {
                                    this.activePreview = (lastCustomer.text || '').replace(/\s+/g, ' ').slice(0, 72);
                                }
                            } catch (e) { /* keep polling */ }
                        },
                        async acceptConversation() {
                            if (!this.acceptUrl || this.sending) return;
                            this.sending = true;
                            this.error = '';
                            try {
                                var res = await fetch(this.acceptUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Accept': 'application/json',
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': this.csrfToken(),
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                    credentials: 'same-origin',
                                    body: JSON.stringify({}),
                                });
                                var data = await res.json().catch(function () { return {}; });
                                if (!res.ok || !data.ok) {
                                    this.error = data.error || 'Accept failed. Select a Support staff member first.';
                                    return;
                                }
                                this.messages = this.mapMessages(data.messages || []);
                                this.deskState = 'Assigned';
                                this.scrollBottom();
                            } catch (e) {
                                this.error = 'Accept failed. Try again.';
                            } finally {
                                this.sending = false;
                            }
                        },
                        async sendReply() {
                            var body = (this.draft || '').trim();
                            if (!body || !this.replyUrl || this.sending) return;
                            this.sending = true;
                            this.error = '';
                            var optimistic = { id: 'tmp-' + Date.now(), role: 'bot', text: body, time: new Date().toTimeString().slice(0, 5) };
                            this.messages.push(optimistic);
                            this.draft = '';
                            this.scrollBottom();
                            try {
                                var res = await fetch(this.replyUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Accept': 'application/json',
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': this.csrfToken(),
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                    credentials: 'same-origin',
                                    body: JSON.stringify({ body: body }),
                                });
                                var data = await res.json().catch(function () { return {}; });
                                if (!res.ok || !data.ok) {
                                    this.messages = this.messages.filter(function (m) { return m.id !== optimistic.id; });
                                    this.draft = body;
                                    this.error = data.error || 'Reply failed.';
                                    return;
                                }
                                this.messages = this.mapMessages(data.messages || []);
                                this.deskState = 'Active';
                                this.activePreview = body.replace(/\s+/g, ' ').slice(0, 72);
                                this.scrollBottom();
                            } catch (e) {
                                this.messages = this.messages.filter(function (m) { return m.id !== optimistic.id; });
                                this.draft = body;
                                this.error = 'Reply failed. Try again.';
                            } finally {
                                this.sending = false;
                            }
                        },
                        init() {
                            this.scrollBottom();
                            if (this.threadUrl) {
                                var self = this;
                                this.poll();
                                this._timer = setInterval(function () { self.poll(); }, this.pollMs);
                            }
                        },
                        destroy() {
                            if (this._timer) clearInterval(this._timer);
                        },
                    };
                });
            });
        </script>
    @endonce
</x-admin.layout>
