@php
    $attentionBadges = $attentionBadges ?? ['waiting' => 0, 'active' => 0, 'mine' => 0, 'tickets' => 0];
    $filters = [
        'waiting' => __('admin.support.inbox.filter_waiting'),
        'active' => __('admin.support.inbox.filter_active'),
        'mine' => __('admin.support.inbox.filter_mine'),
        'tickets' => __('admin.support.inbox.filter_tickets'),
        'resolved' => __('admin.support.inbox.filter_resolved'),
    ];
    $locale = str_starts_with(app()->getLocale(), 'en') ? 'en' : 'sw';
    $quickReplyBodies = $quickReplyBodies ?? [];
    $specialistFollowUps = $specialistFollowUps ?? 0;
    $queueKpis = $queueKpis ?? ['waiting_now' => 0, 'longest_waiting_seconds' => 0, 'accepted_today' => 0, 'active_now' => 0];
    $assignableAgents = $assignableAgents ?? [];
    $canPickAgent = $canPickAgent ?? false;
    $ticketTaxonomy = $ticketTaxonomy ?? \App\Support\SupportTaxonomy::all();
    $similarSearchUrl = $similarSearchUrl ?? route('admin.support-tickets.similar');
    $isWaitingDesk = $conversation && ! $conversation->assigned_to
        && ! in_array($conversation->status, ['closed', 'resolved'], true);
    $isResolvedHistory = $conversation
        && in_array((string) $conversation->status, ['closed', 'resolved'], true);
    $seedMessages = [];
    if ($conversation) {
        foreach ($conversation->messages as $message) {
            if ($message->is_automated) {
                $body = (string) $message->body;
                if (str_contains($body, 'Mtoa huduma wako hayupo')
                    || str_contains($body, 'Your support agent is offline')
                    || str_contains($body, 'Your Support agent is offline')) {
                    continue;
                }
            }
            $seedMessages[] = [
                'id' => (int) $message->id,
                'role' => in_array($message->sender_type, ['staff', 'bot'], true) ? 'bot' : 'user',
                'text' => (string) $message->body,
                'time' => $message->created_at ? format_app_datetime($message->created_at, 'H:i') : null,
            ];
        }
    }
    $longest = (int) ($queueKpis['longest_waiting_seconds'] ?? 0);
    $longestLabel = sprintf('%02d:%02d', intdiv($longest, 60) % 60, $longest % 60);
    if ($longest >= 3600) {
        $longestLabel = sprintf('%02d:%02d:%02d', intdiv($longest, 3600), intdiv($longest, 60) % 60, $longest % 60);
    }
@endphp
<x-admin.layout :title="__('admin.support.inbox.title')" heading="" subheading="">
    <x-admin.letterhead
        :kicker="__('admin.support.kicker')"
        :title="__('admin.support.inbox.title')"
        :subtitle="__('admin.support.inbox.subtitle')"
    >
        <x-slot:actions>
            <a href="{{ route('admin.support.interactions.new') }}"
               class="inline-flex rounded-xl bg-brand-gold text-brand text-sm font-semibold px-4 py-2.5 hover:brightness-95">
                {{ __('admin.support.new_support') }}
            </a>
        </x-slot:actions>
    </x-admin.letterhead>

    @if (session('error'))
        <div class="mb-3 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-900">{{ session('error') }}</div>
    @endif
    @if (session('status'))
        <div class="mb-3 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-950">{{ session('status') }}</div>
    @endif

    <div class="mb-3 grid grid-cols-2 lg:grid-cols-4 gap-2">
        <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-3 py-2.5">
            <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">{{ __('admin.support.inbox.kpi_waiting_now') }}</p>
            <p class="text-xl font-bold text-amber-950">{{ (int) $queueKpis['waiting_now'] }}</p>
        </div>
        <div class="rounded-xl bg-white ring-1 ring-slate-200 px-3 py-2.5">
            <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">{{ __('admin.support.inbox.kpi_longest_waiting') }}</p>
            <p class="text-xl font-bold text-slate-900 font-mono">{{ $longestLabel }}</p>
        </div>
        <div class="rounded-xl bg-white ring-1 ring-slate-200 px-3 py-2.5">
            <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">{{ __('admin.support.inbox.kpi_accepted_today') }}</p>
            <p class="text-xl font-bold text-slate-900">{{ (int) $queueKpis['accepted_today'] }}</p>
        </div>
        <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-3 py-2.5">
            <p class="text-[10px] uppercase tracking-widest text-emerald-800 font-semibold">{{ __('admin.support.inbox.kpi_active_now') }}</p>
            <p class="text-xl font-bold text-emerald-950">{{ (int) $queueKpis['active_now'] }}</p>
        </div>
    </div>

    <div class="w-full rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden"
         x-data="supportInboxDesk(@js([
             'draft' => '',
             'bodies' => $quickReplyBodies,
             'messageTemplates' => collect($quickReplies ?? [])->map(fn ($qr) => [
                 'key' => $qr['key'],
                 'kind' => $qr['kind'] ?? 'message',
                 'group' => $qr['group'] ?? '',
                 'label' => $qr['label_'.$locale] ?? $qr['label_sw'],
                 'one_click' => (bool) ($qr['one_click'] ?? false),
             ])->values(),
             'diagnosticTemplates' => collect($diagnosticTemplates ?? [])->map(fn ($qr) => [
                 'key' => $qr['key'],
                 'kind' => 'diagnostic',
                 'group' => $qr['group'] ?? 'diagnostic',
                 'label' => $qr['label_'.$locale] ?? $qr['label_sw'],
             ])->values(),
             'diagnosticUrl' => $conversation ? route('admin.support.inbox.diagnostic-draft', $conversation) : null,
             'messages' => $seedMessages,
             'conversationId' => $conversation?->id,
             'threadUrl' => $conversation ? route('admin.support.inbox.thread', $conversation) : null,
             'replyUrl' => $conversation ? route('admin.support.inbox.reply', $conversation) : null,
             'acceptUrl' => $conversation ? route('admin.support.inbox.accept', $conversation) : null,
             'csrf' => csrf_token(),
             'pollMs' => 2000,
             'deskState' => $serialized['desk_state'] ?? ($conversation ? ucfirst($conversation->status) : ''),
             'activePreview' => $serialized['preview'] ?? '',
             'isWaiting' => (bool) $isWaitingDesk,
             'canPickAgent' => (bool) $canPickAgent,
             'agents' => $assignableAgents,
             'selectedAgentId' => $canPickAgent ? '' : ($agent?->id ?? ''),
         ]))">
        {{-- Full Admin width · ~25% / 47% / 28% --}}
        <div class="grid lg:grid-cols-[minmax(14rem,0.95fr)_minmax(0,1.75fr)_minmax(14rem,1.05fr)] min-h-[36rem]">
            {{-- CONVERSATIONS --}}
            <aside class="bg-slate-50/90 border-b lg:border-b-0 lg:border-r border-slate-200/80 flex flex-col {{ $conversation ? 'hidden lg:flex' : 'flex' }}">
                <div class="px-4 pt-4 pb-2 border-b border-slate-200/70 bg-white/70">
                    <p class="text-[11px] uppercase tracking-[0.16em] font-semibold text-slate-500">{{ __('admin.support.inbox.conversations') }}</p>
                </div>
                <form method="GET" action="{{ route('admin.support.inbox') }}" class="px-3 pt-3 pb-2 space-y-2">
                    <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('admin.support.inbox.search_placeholder') }}"
                           class="w-full rounded-lg border-slate-200 text-sm bg-white focus:ring-brand/30">
                    <input type="hidden" name="filter" value="{{ $filter }}">
                </form>
                <div class="flex flex-wrap gap-1 px-3 pb-3">
                    @foreach ($filters as $key => $label)
                        @php $badge = (int) ($attentionBadges[$key] ?? 0); @endphp
                        <a href="{{ route('admin.support.inbox', ['filter' => $key, 'q' => $q]) }}"
                           class="text-xs font-semibold px-2.5 py-1 rounded-full inline-flex items-center gap-1 {{ $filter === $key ? 'bg-brand text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-brand-muted' }}">
                            {{ $label }}
                            @if ($badge > 0 && $key !== 'resolved')
                                <span class="inline-flex min-w-[1.1rem] h-4 items-center justify-center rounded-full px-1 text-[10px] font-bold {{ $filter === $key ? 'bg-white text-brand' : 'bg-brand text-white' }}">{{ $badge }}</span>
                            @endif
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
                                   @if (($activeId ?? null) === $item['id']) x-text="activePreview || @js($item['preview'] ?: __('admin.support.inbox.no_messages_yet'))" @endif>{{ $item['preview'] ?: __('admin.support.inbox.no_messages_yet') }}</p>
                                <p class="text-xs {{ ($item['waiting_label'] ?? null) ? 'text-amber-800 font-semibold' : 'text-slate-500' }} mt-0.5">
                                    <span @if (($activeId ?? null) === $item['id']) x-text="deskState || @js($item['desk_state'] ?? ucfirst($item['status']))" @endif>{{ $item['desk_state'] ?? ucfirst($item['status']) }}</span>
                                    @if ($item['waiting_label'] ?? null) · {{ $item['waiting_label'] }} @endif
                                    <span class="text-slate-400 font-normal"> · {{ $item['conversation_number'] ?? '' }}</span>
                                </p>
                            </a>
                        </li>
                    @empty
                        <li class="px-4 py-10 text-center text-sm text-slate-500">{{ __('admin.support.inbox.no_conversations_filter') }}</li>
                    @endforelse
                </ul>
            </aside>

            {{-- CONVERSATION --}}
            <section class="flex flex-col min-h-[28rem] bg-white {{ $conversation ? '' : 'hidden lg:flex' }}">
                @if ($conversation)
                    <div class="px-4 py-3 border-b border-slate-200/80 flex items-start justify-between gap-3 bg-white">
                        <div class="min-w-0">
                            <a href="{{ route('admin.support.inbox', ['filter' => $filter, 'q' => $q]) }}" class="lg:hidden text-sm font-semibold text-brand">{{ __('admin.support.inbox.back_conversations') }}</a>
                            <p class="text-[11px] uppercase tracking-[0.16em] font-semibold text-slate-500">{{ $conversation->publicNumber() }}</p>
                            <p class="text-base font-bold text-slate-900 truncate">{{ $serialized['name'] ?? __('admin.support.inbox.conversation_fallback') }}</p>
                            <p class="text-sm text-slate-500">
                                <span x-text="deskState || @js($serialized['desk_state'] ?? ucfirst($conversation->status))"></span>
                                @if (! empty($serialized['topic'])) · {{ $serialized['topic'] }} @endif
                            </p>
                            <p class="text-xs text-red-700 mt-1" x-show="error" x-text="error" x-cloak></p>
                        </div>
                        <div class="flex flex-wrap gap-1.5 shrink-0">
                            @if ($isWaitingDesk)
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($canPickAgent)
                                        <div class="relative" @keydown.escape.window="agentMenuOpen = false">
                                            <button type="button" @click="agentMenuOpen = !agentMenuOpen"
                                                    class="inline-flex items-center gap-2 min-w-[12rem] max-w-[16rem] rounded-xl bg-white ring-1 ring-slate-200 px-3 py-1.5 text-left hover:ring-brand/30">
                                                <span class="min-w-0 flex-1">
                                                    <span class="block text-sm font-semibold text-slate-900 truncate"
                                                          x-text="selectedAgentLabel() || 'Assign to…'"></span>
                                                    <span class="block text-xs text-slate-500"
                                                          x-show="selectedAgentId"
                                                          x-text="selectedAgentActiveLabel()"></span>
                                                </span>
                                                <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                                            </button>
                                            {{-- Desktop dropdown --}}
                                            <div x-show="agentMenuOpen" x-cloak @click.outside="agentMenuOpen = false"
                                                 class="hidden md:block absolute right-0 mt-2 w-64 rounded-2xl bg-white ring-1 ring-slate-200 shadow-lg overflow-hidden z-30 max-h-64 overflow-y-auto">
                                                <template x-for="a in agents" :key="'d-'+a.id">
                                                    <button type="button"
                                                            @click="selectedAgentId = String(a.id); agentMenuOpen = false; error = ''"
                                                            class="w-full flex items-center justify-between gap-3 px-3.5 py-2.5 text-left transition"
                                                            :class="String(selectedAgentId) === String(a.id) ? 'bg-brand/5' : 'hover:bg-slate-50'">
                                                        <span class="min-w-0">
                                                            <span class="block text-sm font-semibold text-slate-900 truncate" x-text="a.name"></span>
                                                            <span class="block text-[11px] text-slate-500" x-text="(a.active_count || 0) + ' active'"></span>
                                                        </span>
                                                        <svg x-show="String(selectedAgentId) === String(a.id)" class="w-4 h-4 text-brand shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12l5 5L20 7"/></svg>
                                                    </button>
                                                </template>
                                            </div>
                                            {{-- Mobile: canonical action-panel (bottom sheet) --}}
                                            <div class="md:hidden">
                                                <x-site.action-panel title="Assign to…" open="agentMenuOpen">
                                                    <div class="space-y-1">
                                                        <template x-for="a in agents" :key="'m-'+a.id">
                                                            <button type="button"
                                                                    @click="selectedAgentId = String(a.id); agentMenuOpen = false; error = ''"
                                                                    class="w-full flex items-center justify-between gap-3 px-3.5 py-3.5 text-left rounded-xl transition"
                                                                    :class="String(selectedAgentId) === String(a.id) ? 'bg-brand/5' : 'hover:bg-slate-50'">
                                                                <span class="min-w-0">
                                                                    <span class="block text-base font-semibold text-slate-900 truncate" x-text="a.name"></span>
                                                                    <span class="block text-xs text-slate-500 mt-0.5" x-text="(a.active_count || 0) + ' active'"></span>
                                                                </span>
                                                                <svg x-show="String(selectedAgentId) === String(a.id)" class="w-5 h-5 text-brand shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12l5 5L20 7"/></svg>
                                                            </button>
                                                        </template>
                                                    </div>
                                                </x-site.action-panel>
                                            </div>
                                        </div>
                                    @endif
                                    <button type="button" @click="acceptConversation()" :disabled="sending"
                                            class="rounded-lg bg-brand text-white text-xs font-semibold px-3 py-1.5 hover:brightness-95 disabled:opacity-60">{{ __('admin.support.inbox.accept') }}</button>
                                </div>
                            @else
                                <div x-data="{ resolveOpen: false }" class="relative">
                                    <button type="button" @click="resolveOpen = !resolveOpen"
                                            class="rounded-lg ring-1 ring-emerald-200 text-emerald-800 text-xs font-semibold px-3 py-1.5 hover:bg-emerald-50">Resolve</button>
                                    <div x-show="resolveOpen" x-cloak @click.outside="resolveOpen = false"
                                         class="absolute right-0 top-full mt-2 z-20 w-72 rounded-xl bg-white ring-1 ring-emerald-200 shadow-lg p-3 space-y-2">
                                        <p class="text-[11px] text-emerald-900/80">Closes this conversation. Member gets a short resolution message and can rate with stars. Next Talk to Support starts a new thread.</p>
                                        <form method="POST" action="{{ route('admin.support.inbox.resolve', $conversation) }}">
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
                                            <input type="hidden" name="ask_rating" value="1">
                                            <button type="submit" class="mt-2 w-full rounded-lg bg-emerald-700 text-white text-xs font-semibold px-3 py-2">Confirm resolve</button>
                                        </form>
                                    </div>
                                </div>
                                <div x-data="inboxCreateTicket({
                                         subjectsByCategory: @js($ticketTaxonomy['subjects'] ?? []),
                                         defaultPriorities: @js($ticketTaxonomy['default_priority'] ?? []),
                                         similarSearchUrl: @js($similarSearchUrl),
                                         topic: @js($conversation->topic),
                                     })" class="relative">
                                    <button type="button" @click="caseOpen = !caseOpen"
                                            class="rounded-lg bg-brand text-white text-xs font-semibold px-3 py-1.5 hover:brightness-95">Create ticket</button>
                                    <div x-show="caseOpen" x-cloak @click.outside="caseOpen = false"
                                         class="absolute right-0 top-full mt-2 z-20 w-80 rounded-xl bg-white ring-1 ring-brand/20 shadow-lg p-3 space-y-2">
                                        <p class="text-[11px] text-slate-600">Use a ticket only when investigation or another department must own follow-up.</p>
                                        <form method="POST" action="{{ route('admin.support.inbox.create-case', $conversation) }}" class="space-y-2"
                                              @submit="if (similarTickets.length && !createAnyway) { $event.preventDefault(); }">
                                            @csrf
                                            <label class="block text-[11px] font-semibold text-gray-700">Category
                                                <select name="category" x-model="category" @change="onCategoryChange()" required
                                                        class="mt-1 w-full rounded-lg border-gray-200 text-xs">
                                                    <option value="">— Select —</option>
                                                    @foreach (($ticketTaxonomy['categories'] ?? []) as $key => $label)
                                                        <option value="{{ $key }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <label class="block text-[11px] font-semibold text-gray-700" x-show="category === 'other'" x-cloak>Custom category
                                                <input type="text" name="category_other" x-model="categoryOther" maxlength="120" class="mt-1 w-full rounded-lg border-gray-200 text-xs">
                                            </label>
                                            <label class="block text-[11px] font-semibold text-gray-700">Subject
                                                <select name="subject" x-model="subject" @change="onSubjectChange(); checkSimilar()" required
                                                        class="mt-1 w-full rounded-lg border-gray-200 text-xs" :disabled="!category">
                                                    <option value="">— Select —</option>
                                                    <template x-for="item in subjectOptions" :key="item">
                                                        <option :value="item" x-text="item"></option>
                                                    </template>
                                                </select>
                                            </label>
                                            <label class="block text-[11px] font-semibold text-gray-700" x-show="subject === 'Other'" x-cloak>Custom subject
                                                <input type="text" name="subject_other" x-model="subjectOther" maxlength="180" class="mt-1 w-full rounded-lg border-gray-200 text-xs">
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
                                                <select name="priority" x-model="priority" class="mt-1 w-full rounded-lg border-gray-200 text-xs">
                                                    <option value="normal">Normal</option>
                                                    <option value="low">Low</option>
                                                    <option value="high">High</option>
                                                    <option value="urgent">Urgent</option>
                                                </select>
                                            </label>
                                            <label class="block text-[11px] font-semibold text-gray-700">Summary
                                                <textarea name="body" rows="3" maxlength="5000" class="mt-1 w-full rounded-lg border-gray-200 text-xs"></textarea>
                                            </label>
                                            <div x-show="similarTickets.length > 0" x-cloak class="rounded-lg bg-amber-50 ring-1 ring-amber-200 px-2.5 py-2 text-[11px] text-amber-950 space-y-1">
                                                <p class="font-semibold">Similar tickets</p>
                                                <template x-for="row in similarTickets" :key="row.ticket_number">
                                                    <p><span class="font-mono font-semibold" x-text="row.ticket_number"></span>
                                                        <span x-text="' · ' + (row.subject || '')"></span></p>
                                                </template>
                                                <label class="flex items-center gap-2 pt-1">
                                                    <input type="checkbox" name="create_anyway" value="1" x-model="createAnyway" class="rounded border-amber-300">
                                                    <span>Create anyway</span>
                                                </label>
                                            </div>
                                            <button type="submit" class="w-full rounded-lg bg-brand text-white text-xs font-semibold px-3 py-2"
                                                    :disabled="similarTickets.length > 0 && !createAnyway">Create ticket</button>
                                        </form>
                                    </div>
                                </div>
                            @endif
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
                        <p class="text-sm text-slate-500 text-center py-10" x-show="!messages.length">{{ __('admin.support.inbox.no_messages') }}</p>
                    </div>

                    @if ($isResolvedHistory)
                        <div class="border-t border-slate-200/80 p-4 bg-slate-50">
                            <p class="text-sm font-bold text-brand">{{ str_starts_with(app()->getLocale(), 'sw') ? 'Imekamilishwa' : 'Resolved' }}</p>
                            <p class="text-xs text-slate-600 mt-1">{{ str_starts_with(app()->getLocale(), 'sw') ? 'Historia tu. Hakuna composer, templates, wala ujumbe mpya.' : 'Read-only history. No composer, templates, or new messages.' }}</p>
                        </div>
                    @elseif ($isWaitingDesk)
                        <div class="border-t border-amber-200/80 p-4 bg-amber-50/80">
                            <p class="text-sm font-semibold text-amber-950">{{ __('admin.support.inbox.waiting_queue') }}</p>
                            <p class="text-xs text-amber-900/80 mt-1">{{ __('admin.support.inbox.waiting_queue_hint') }}</p>
                        </div>
                    @else
                        <div class="border-t border-slate-200/80 p-4 space-y-3 bg-white">
                            @if (! empty($quickReplies) || ! empty($diagnosticTemplates))
                                <div class="space-y-2">
                                    <input type="search" x-model="tq" placeholder="Search templates (SW/EN)…"
                                           class="w-full rounded-lg border-slate-200 text-xs">
                                    <div>
                                        <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold mb-1">Message templates</p>
                                        <div class="flex flex-wrap gap-1.5 max-h-20 overflow-y-auto">
                                            <template x-for="qr in filteredMessageTemplates()" :key="'m-'+qr.key">
                                                <button type="button"
                                                        @click="useMessageTemplate(qr)"
                                                        :disabled="templateBusy === qr.key || sending"
                                                        :class="templateBusy === qr.key ? 'opacity-60 ring-1 ring-brand/40' : ''"
                                                        class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 hover:bg-brand-muted disabled:cursor-wait"
                                                        x-text="templateBusy === qr.key ? (qr.label + '…') : qr.label"></button>
                                            </template>
                                        </div>
                                    </div>
                                    <div>
                                        <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold mb-1">Status / Diagnostic</p>
                                        <div class="flex flex-wrap gap-1.5 max-h-20 overflow-y-auto">
                                            <template x-for="qr in filteredDiagnosticTemplates()" :key="'d-'+qr.key">
                                                <button type="button"
                                                        @click="useDiagnosticTemplate(qr)"
                                                        :disabled="templateBusy === qr.key || sending"
                                                        :class="templateBusy === qr.key ? 'opacity-60 ring-1 ring-brand/40' : ''"
                                                        class="text-xs font-semibold px-2.5 py-1 rounded-full bg-brand-muted/70 text-brand hover:bg-brand-muted disabled:cursor-wait"
                                                        x-text="templateBusy === qr.key ? (qr.label + '…') : qr.label"></button>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            <form @submit.prevent="sendReply()" class="flex gap-3 items-end">
                                <textarea name="body" x-model="draft" x-ref="composer" rows="3" required maxlength="5000"
                                          placeholder="{{ __('admin.support.composer.placeholder') }}"
                                          class="flex-1 min-h-[4.5rem] rounded-xl border-slate-200 text-base focus:ring-brand/30"></textarea>
                                <button type="submit" :disabled="sending || !draft.trim()"
                                        class="shrink-0 rounded-xl bg-brand-gold text-brand font-semibold text-sm px-5 py-3 hover:brightness-95 disabled:opacity-60">{{ __('admin.support.composer.send') }}</button>
                            </form>
                        </div>
                    @endif
                @else
                    <div class="flex-1 grid place-items-center px-6 py-16 text-center bg-[#f7f8fa]">
                        <div>
                            <p class="text-[11px] uppercase tracking-[0.16em] font-semibold text-slate-500">{{ __('admin.support.inbox.conversation_fallback') }}</p>
                            <p class="text-base font-semibold text-slate-900 mt-1">{{ __('admin.support.inbox.select_conversation') }}</p>
                            <p class="text-sm text-slate-500 mt-1 max-w-xs">{{ __('admin.support.inbox.select_conversation_hint') }}</p>
                        </div>
                    </div>
                @endif
            </section>

            {{-- CUSTOMER DETAILS --}}
            <aside class="bg-slate-50/70 border-t lg:border-t-0 lg:border-l border-slate-200/80 p-4 space-y-3 {{ $conversation ? '' : 'hidden lg:block' }}">
                <p class="text-[11px] uppercase tracking-[0.16em] font-semibold text-slate-500">{{ __('admin.support.customer_details') }}</p>
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
                            <p class="text-sm font-semibold text-amber-700 mt-0.5">{{ __('admin.support.guest_non_member') }}</p>
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
                        <p class="text-[11px] uppercase tracking-widest text-slate-500 font-semibold">Open tickets ({{ count($context['open_cases'] ?? []) }})</p>
                        @forelse ($context['open_cases'] ?? [] as $case)
                            <a href="{{ $case['url'] }}" class="block font-semibold text-brand hover:underline">{{ $case['number'] }} · {{ $case['subject'] }}</a>
                        @empty
                            <p class="text-slate-400">None</p>
                        @endforelse
                        <p class="text-[11px] uppercase tracking-widest text-slate-500 font-semibold pt-2">Previous tickets ({{ count($context['previous_cases'] ?? []) }})</p>
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
                        messageTemplates: Array.isArray(config.messageTemplates) ? config.messageTemplates.slice() : [],
                        diagnosticTemplates: Array.isArray(config.diagnosticTemplates) ? config.diagnosticTemplates.slice() : [],
                        diagnosticUrl: config.diagnosticUrl || null,
                        tq: '',
                        templateBusy: null,
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
                        isWaiting: !!config.isWaiting,
                        canPickAgent: !!config.canPickAgent,
                        agents: Array.isArray(config.agents) ? config.agents : [],
                        selectedAgentId: config.selectedAgentId || '',
                        agentMenuOpen: false,
                        unreadBadge: 0,
                        _timer: null,
                        selectedAgent() {
                            var id = String(this.selectedAgentId || '');
                            if (!id) return null;
                            return this.agents.find(function (a) { return String(a.id) === id; }) || null;
                        },
                        selectedAgentLabel() {
                            var a = this.selectedAgent();
                            return a ? (a.name || '') : '';
                        },
                        selectedAgentActiveLabel() {
                            var a = this.selectedAgent();
                            if (!a) return '';
                            return (a.active_count || 0) + ' active';
                        },
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
                        filteredMessageTemplates() {
                            var q = (this.tq || '').toLowerCase();
                            return (this.messageTemplates || []).filter(function (r) {
                                return !q || (r.label + ' ' + r.group + ' ' + r.key).toLowerCase().includes(q);
                            });
                        },
                        filteredDiagnosticTemplates() {
                            var q = (this.tq || '').toLowerCase();
                            return (this.diagnosticTemplates || []).filter(function (r) {
                                return !q || (r.label + ' ' + r.group + ' ' + r.key).toLowerCase().includes(q);
                            });
                        },
                        async useMessageTemplate(qr) {
                            if (!qr || this.templateBusy || this.sending) return;
                            this.templateBusy = qr.key;
                            this.error = '';
                            try {
                                this.draft = this.bodies[qr.key] || '';
                                if (qr.one_click) {
                                    await this.sendReply(qr.key);
                                } else {
                                    this.$refs.composer?.focus();
                                }
                            } finally {
                                this.templateBusy = null;
                            }
                        },
                        async useDiagnosticTemplate(qr) {
                            if (!qr || !this.diagnosticUrl || this.templateBusy || this.sending) return;
                            this.templateBusy = qr.key;
                            this.error = '';
                            try {
                                var res = await fetch(this.diagnosticUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Accept': 'application/json',
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': this.csrfToken(),
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                    credentials: 'same-origin',
                                    body: JSON.stringify({ key: qr.key }),
                                });
                                var data = await res.json().catch(function () { return {}; });
                                if (!res.ok || !data.ok) {
                                    this.error = data.error || 'Diagnostic template failed.';
                                    return;
                                }
                                this.draft = data.draft || '';
                                this.$refs.composer?.focus();
                            } catch (e) {
                                this.error = 'Diagnostic template failed.';
                            } finally {
                                this.templateBusy = null;
                            }
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
                            if (this.canPickAgent && !this.selectedAgentId) {
                                this.error = 'Select a Support staff member before Accept.';
                                return;
                            }
                            this.sending = true;
                            this.error = '';
                            try {
                                var payload = {};
                                if (this.selectedAgentId) payload.agent_id = Number(this.selectedAgentId);
                                var res = await fetch(this.acceptUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Accept': 'application/json',
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': this.csrfToken(),
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                    credentials: 'same-origin',
                                    body: JSON.stringify(payload),
                                });
                                var data = await res.json().catch(function () { return {}; });
                                if (!res.ok || !data.ok) {
                                    this.error = data.error || 'Accept failed. Select a Support staff member first.';
                                    return;
                                }
                                if (data.redirect) {
                                    window.location = data.redirect;
                                    return;
                                }
                                this.messages = this.mapMessages(data.messages || []);
                                this.deskState = 'Assigned';
                                this.isWaiting = false;
                                this.scrollBottom();
                                window.location.reload();
                            } catch (e) {
                                this.error = 'Accept failed. Try again.';
                            } finally {
                                this.sending = false;
                            }
                        },
                        async sendReply(templateKey) {
                            var body = (this.draft || '').trim();
                            if (!body || !this.replyUrl || this.sending) return;
                            this.sending = true;
                            this.error = '';
                            var optimistic = { id: 'tmp-' + Date.now(), role: 'bot', text: body, time: new Date().toTimeString().slice(0, 5) };
                            this.messages.push(optimistic);
                            this.draft = '';
                            this.scrollBottom();
                            try {
                                var payload = { body: body };
                                if (templateKey) payload.template_key = templateKey;
                                var res = await fetch(this.replyUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Accept': 'application/json',
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': this.csrfToken(),
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                    credentials: 'same-origin',
                                    body: JSON.stringify(payload),
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
