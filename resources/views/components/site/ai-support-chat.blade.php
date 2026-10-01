@props([
    'memberMode' => false,
    'forceHuman' => false,
    'agentLabel' => null,
    'agentSubtitle' => null,
    'speakUrl' => null,
    'threadUrl' => null,
    'existingMessages' => null,
    'conversation' => null,
    'showBackToFaqs' => false,
    'backToFaqsLabel' => null,
    'showRating' => false,
    'ratingUrl' => null,
    'guestName' => null,
    'guestFirstName' => null,
    'guestLastName' => null,
    'guestPhone' => null,
])

@php
    $chat = app(\App\Services\ChatbotContentService::class)->payload();
    $agentLabel = $agentLabel ?? __('site.support.assistant_title');
    $agentSubtitle = $agentSubtitle ?? __('site.support.assistant_subtitle');
    $registerUrl = route('site.register.borrower');
    $registerPrompt = __('site.support.chat.register_prompt');
    $speakUrl = $speakUrl ?? ($memberMode ? route('site.borrower.support.speak') : null);
    $threadUrl = $threadUrl ?? ($memberMode ? route('site.borrower.support.thread') : null);
    $seedMessages = [];
    if (is_iterable($existingMessages)) {
        foreach ($existingMessages as $m) {
            $seedMessages[] = [
                'id' => (int) ($m->id ?? 0) ?: null,
                'role' => in_array($m->sender_type ?? '', ['staff', 'bot'], true) ? 'bot' : 'user',
                'text' => (string) ($m->body ?? ''),
                'time' => $m->created_at ? format_app_datetime($m->created_at, 'H:i') : null,
            ];
        }
    }
    $startHuman = $forceHuman || count($seedMessages) > 0;
    $presence = app(\App\Services\Support\SupportConversationService::class)
        ->memberChatPresence($conversation instanceof \App\Models\SupportConversation ? $conversation : null);
    // Parent provides focused/centered width; chat fills that container (not a left-aligned max-w-2xl island).
    $shellClass = $forceHuman
        ? 'overflow-hidden rounded-2xl ring-1 ring-brand/15 shadow-sm bg-white w-full'
        : 'glass-card p-5 sm:p-6 w-full';
    $isSw = str_starts_with(app()->getLocale(), 'sw');
    $composerLocked = $conversation instanceof \App\Models\SupportConversation
        && in_array((string) $conversation->status, ['closed', 'resolved'], true);
    $needsGuestIdentity = ! $memberMode && $forceHuman;
    $guestFirstName = trim((string) ($guestFirstName ?? ''));
    $guestLastName = trim((string) ($guestLastName ?? ''));
    if ($guestFirstName === '' && $guestLastName === '' && filled($guestName)) {
        $parts = preg_split('/\s+/', trim((string) $guestName), 2) ?: [];
        $guestFirstName = (string) ($parts[0] ?? '');
        $guestLastName = (string) ($parts[1] ?? '');
    }
@endphp

<div {{ $attributes->merge(['class' => $shellClass]) }}
     x-data="aiSupportChat(@js([
         'greeting' => $forceHuman
             ? __('borrower.support_page.speak_to_support_hint')
             : $chat['greeting'],
         'default' => $chat['default'],
         'suggestions' => $forceHuman ? [] : $chat['suggestions'],
         'rules' => $forceHuman ? [] : $chat['rules'],
         'products' => $forceHuman ? [] : $chat['products'],
         'chooseProductPrompt' => $chat['choose_product_prompt'],
         'memberMode' => $memberMode,
         'forceHuman' => (bool) $forceHuman,
         'needsGuestIdentity' => (bool) $needsGuestIdentity,
         'guestFirstName' => $guestFirstName,
         'guestLastName' => $guestLastName,
         'guestName' => trim($guestFirstName.' '.$guestLastName),
         'guestPhone' => (string) ($guestPhone ?? ''),
         'guestIdentityHint' => $isSw
             ? 'Andika jina la kwanza, jina la mwisho na namba ya simu ili timu iweze kukujibu (si usajili).'
             : 'Enter your first name, last name and phone so the team can reply (not registration).',
         'guestFirstNameLabel' => $isSw ? 'Jina la kwanza' : 'First name',
         'guestLastNameLabel' => $isSw ? 'Jina la mwisho' : 'Last name',
         'guestPhoneLabel' => $isSw ? 'Namba ya simu' : 'Phone number',
         'guestContinue' => $isSw ? 'Anza mazungumzo' : 'Start chat',
         'registerPrompt' => $registerPrompt,
         'registerUrl' => $registerUrl,
         'typingLabel' => __('site.support.chat.typing'),
         'speakUrl' => $speakUrl,
         'threadUrl' => $threadUrl,
         'csrf' => csrf_token(),
         'seedMessages' => $seedMessages,
         'startHuman' => $startHuman,
         'pollMs' => 2000,
         'conversationId' => $conversation?->id,
         'conversationNumber' => $conversation instanceof \App\Models\SupportConversation ? $conversation->publicNumber() : null,
         'agentFirstName' => $presence['agent_first_name'],
         'presence' => $presence['presence'],
         'statusOnline' => 'Waiting for support',
         'statusAssigned' => 'Agent assigned',
         'tagline' => 'Kwa ajili yako · Here to help',
         'brandTitle' => 'Kopafasta Support',
         'assignedSuffix' => 'Customer Support',
         'deskLabel' => $presence['desk_label'] ?? 'Waiting for support',
         'showRating' => (bool) $showRating,
         'ratingUrl' => $ratingUrl,
         'composerLocked' => (bool) $composerLocked,
         'ratingThanks' => $isSw ? 'Asante kwa tathmini yako.' : 'Thank you for your rating.',
         'ratingPrompt' => $isSw ? 'Tathmini huduma yetu' : 'Rate our support',
         'ratingCommentPh' => $isSw ? 'Maoni (si lazima)' : 'Comment (optional)',
         'ratingSend' => $isSw ? 'Tuma tathmini' : 'Submit rating',
     ]))">
    @if ($forceHuman)
        {{-- Premium live-support header — compact; no phone/website --}}
        <div class="relative overflow-hidden bg-gradient-to-br from-brand via-[#127A5F] to-[#0a4a3c] text-white px-3.5 sm:px-4 py-3 sm:py-3.5">
            <div class="absolute inset-0 opacity-20 pointer-events-none"
                 style="background-image: radial-gradient(circle at 12% 20%, #fff 0, transparent 42%), radial-gradient(circle at 92% 0%, #fbbf24 0, transparent 36%);"></div>
            <div class="relative flex items-center gap-3">
                <div class="relative size-10 sm:size-11 rounded-xl bg-white/15 ring-1 ring-white/25 grid place-items-center font-bold text-sm shrink-0">
                    <span x-text="agentFirstName ? agentFirstName.charAt(0).toUpperCase() : 'CS'"></span>
                    <span class="absolute -bottom-0.5 -right-0.5 size-2.5 rounded-full bg-emerald-300 ring-2 ring-[#0f5c4a]"
                          :class="presence === 'assigned' ? 'bg-brand-gold' : 'bg-emerald-300'"></span>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                        <p class="text-[15px] sm:text-base font-bold tracking-tight truncate"
                           x-text="agentFirstName ? (agentFirstName + ' · ' + config.assignedSuffix) : config.brandTitle"></p>
                        <span class="inline-flex items-center gap-1 rounded-full bg-white/15 px-2 py-0.5 text-[10px] sm:text-[11px] font-semibold uppercase tracking-wide">
                            <span class="size-1.5 rounded-full"
                                  :class="presence === 'assigned' ? 'bg-brand-gold' : 'bg-emerald-300'"></span>
                            <span x-text="presence === 'assigned' ? config.statusAssigned : (config.deskLabel || config.statusOnline)"></span>
                        </span>
                        <span class="text-[10px] sm:text-[11px] text-white/70 font-semibold" x-show="config.conversationNumber" x-text="config.conversationNumber"></span>
                    </div>
                    <p class="text-[11px] sm:text-xs text-white/75 mt-0.5 truncate" x-text="config.tagline"></p>
                </div>
                @if ($showBackToFaqs)
                    <button type="button" @click="$dispatch('support-back-to-faqs')"
                            class="shrink-0 text-[11px] sm:text-xs font-semibold text-white/85 hover:text-white underline-offset-2 hover:underline">
                        {{ $backToFaqsLabel ?? __('borrower.support_page.back_to_faqs') }}
                    </button>
                @endif
            </div>
        </div>
        <div class="px-3.5 sm:px-4 pt-3 pb-4">
            <div x-show="needsGuestGate" x-cloak class="mb-4 rounded-2xl bg-brand-muted/40 ring-1 ring-brand/15 p-4 space-y-3" x-ref="guestGate">
                <p class="text-sm text-gray-700" x-text="config.guestIdentityHint"></p>
                <div class="grid sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1" x-text="config.guestFirstNameLabel"></label>
                        <input type="text" x-model="guestFirstName" maxlength="60" autocomplete="given-name"
                               class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-brand focus:ring-2 focus:ring-brand/10">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1" x-text="config.guestLastNameLabel"></label>
                        <input type="text" x-model="guestLastName" maxlength="60" autocomplete="family-name"
                               class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-brand focus:ring-2 focus:ring-brand/10">
                    </div>
                </div>
                <div>
                    <x-site.phone-input
                        name="guest_phone"
                        :label="$isSw ? 'Namba ya simu' : 'Phone number'"
                        :value="$guestPhone"
                        variant="rounded"
                        :allow-country-change="false"
                        :help="false"
                        :required="true"
                    />
                </div>
                <button type="button" @click="confirmGuestIdentity()"
                        class="w-full rounded-xl bg-brand text-white text-sm font-semibold px-4 py-2.5 disabled:opacity-60"
                        :disabled="!guestFirstName.trim() || !guestLastName.trim()"
                        x-text="config.guestContinue"></button>
            </div>
    @else
        <div class="flex items-center gap-3 mb-4">
            <div class="relative size-11 rounded-xl bg-brand text-white grid place-items-center font-bold text-sm shrink-0">
                <span x-text="humanMode ? 'CS' : 'AI'"></span>
                <span class="absolute -bottom-0.5 -right-0.5 size-3 rounded-full bg-emerald-400 ring-2 ring-white"></span>
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-base font-semibold text-gray-900">{{ $agentLabel }}</p>
                <p class="text-sm text-gray-500">{{ $agentSubtitle }}</p>
            </div>
        </div>
    @endif

    <div class="rounded-xl bg-gradient-to-b from-brand-muted/30 to-white border border-gray-100/80 p-3.5 max-h-80 overflow-y-auto space-y-2.5 text-[15px] mb-3" x-ref="scroll">
        <template x-for="(msg, i) in messages" :key="msg.id || ('m-'+i)">
            <div class="flex" :class="msg.role === 'user' ? 'justify-end' : 'justify-start'">
                <div :class="msg.role === 'user' ? 'kf-support-bubble kf-support-bubble--outbound' : 'kf-support-bubble kf-support-bubble--inbound'">
                    <div class="kf-support-bubble__body whitespace-pre-wrap" x-text="msg.text"></div>
                    <p class="kf-support-bubble__time" x-show="msg.time" x-text="msg.time"></p>
                </div>
            </div>
        </template>
        <div x-show="typing" x-cloak class="flex items-center gap-2 text-sm text-gray-500">
            <span x-text="config.typingLabel"></span>
        </div>
        <p class="text-sm text-red-700" x-show="sendError" x-text="sendError" x-cloak></p>
    </div>

    <div x-show="showRating && !ratingDone" x-cloak class="mb-4 rounded-2xl bg-gradient-to-br from-amber-50 to-white ring-1 ring-amber-200/80 p-4 space-y-3">
        <p class="text-sm font-bold text-amber-950" x-text="config.ratingPrompt"></p>
        <div class="flex items-center justify-center gap-1.5" role="radiogroup" aria-label="Rating">
            <template x-for="n in [1,2,3,4,5]" :key="'star-'+n">
                <button type="button" @click="rating = n" @mouseenter="hoverStar = n" @mouseleave="hoverStar = 0"
                        class="text-3xl leading-none transition transform hover:scale-110 focus:outline-none"
                        :class="(hoverStar || rating) >= n ? 'text-amber-400' : 'text-slate-300'"
                        :aria-checked="rating === n" role="radio">★</button>
            </template>
        </div>
        <textarea x-model="ratingComment" rows="2" maxlength="500" :placeholder="config.ratingCommentPh"
                  class="w-full rounded-xl border-amber-200/80 text-sm focus:ring-amber-300/40"></textarea>
        <button type="button" @click="submitRating()" :disabled="!rating || ratingSending"
                class="w-full rounded-xl bg-brand text-white text-sm font-semibold px-4 py-2.5 disabled:opacity-60"
                x-text="config.ratingSend"></button>
    </div>
    <div x-show="ratingDone" x-cloak class="mb-4 rounded-2xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-5 text-center space-y-1">
        <p class="text-2xl text-amber-400 tracking-widest" x-text="'★'.repeat(rating || 5)"></p>
        <p class="text-sm font-bold text-emerald-950" x-text="config.ratingThanks"></p>
    </div>

    <div class="flex flex-wrap gap-2 mb-4" x-show="!humanMode && !showProductChips && !showRating">
        <template x-for="suggestion in config.suggestions" :key="suggestion">
            <button type="button" @click="askSuggestion(suggestion)" :disabled="typing"
                    class="text-sm px-3 py-1.5 rounded-full bg-brand-muted/80 text-brand hover:bg-brand/10 transition disabled:opacity-50"
                    x-text="suggestion"></button>
        </template>
    </div>

    <div class="mb-4 space-y-2" x-show="!humanMode && showProductChips" x-cloak>
        <p class="text-sm font-semibold text-gray-600" x-text="config.chooseProductPrompt"></p>
        <div class="flex flex-wrap gap-2">
            <template x-for="product in config.products" :key="product.code">
                <button type="button" @click="selectProduct(product)" :disabled="typing"
                        class="text-sm px-3 py-1.5 rounded-full ring-1 ring-brand/25 bg-white text-brand hover:bg-brand-muted transition disabled:opacity-50"
                        x-text="product.name"></button>
            </template>
        </div>
    </div>

    <form @submit.prevent="ask" class="flex gap-2 items-end" x-show="!(showRating || ratingDone || composerLocked || needsGuestGate)">
        <textarea x-model="input" :disabled="typing" x-ref="composer" rows="1"
               @input="growComposer()"
               placeholder="{{ __('site.support.chat_placeholder') }}"
               class="kf-support-composer flex-1 rounded-xl border border-gray-300 px-3.5 py-2.5 text-base focus:border-brand focus:ring-2 focus:ring-brand/10 disabled:opacity-60"></textarea>
        <button type="submit" :disabled="typing || !input.trim()"
                class="bg-brand hover:bg-brand-light disabled:opacity-60 text-white text-sm font-semibold px-4 py-2.5 rounded-xl shrink-0">
            {{ __('site.support.chat_send') }}
        </button>
    </form>

    @unless ($memberMode)
        <p class="mt-3 text-sm text-gray-500" x-show="!needsGuestGate">
            {{ __('site.support.chat.guest_hint') }}
            <a href="{{ $registerUrl }}" class="font-semibold text-brand hover:underline">{{ __('site.hero.get_started') }}</a>
        </p>
    @endunless

    @if ($forceHuman)
        </div>
    @endif

    @once
        <script>
            document.addEventListener('alpine:init', function () {
                Alpine.data('aiSupportChat', function (config) {
                    var seeded = (config.seedMessages && config.seedMessages.length)
                        ? config.seedMessages.slice()
                        : [{ role: 'bot', text: config.greeting }];
                    return {
                        config: config,
                        input: '',
                        typing: false,
                        sendError: '',
                        messages: seeded,
                        humanMode: !!(config.startHuman || config.forceHuman),
                        showProductChips: false,
                        conversationId: config.conversationId || null,
                        agentFirstName: config.agentFirstName || null,
                        presence: config.presence || 'online',
                        showRating: !!config.showRating,
                        ratingUrl: config.ratingUrl || null,
                        composerLocked: !!config.composerLocked,
                        rating: 0,
                        hoverStar: 0,
                        ratingComment: '',
                        ratingSending: false,
                        ratingDone: false,
                        guestFirstName: config.guestFirstName || '',
                        guestLastName: config.guestLastName || '',
                        guestName: config.guestName || '',
                        guestPhone: config.guestPhone || '',
                        guestReady: !config.needsGuestIdentity || (
                            !!(config.guestFirstName || '').trim()
                            && !!(config.guestLastName || '').trim()
                            && !!(config.guestPhone || '').trim()
                        ),
                        get needsGuestGate() {
                            return !!config.needsGuestIdentity && !this.guestReady;
                        },
                        _timer: null,
                        csrfToken() {
                            var meta = document.querySelector('meta[name="csrf-token"]');
                            if (meta && meta.content) return meta.content;
                            var match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
                            if (match) {
                                try { return decodeURIComponent(match[1]); } catch (e) {}
                            }
                            return config.csrf;
                        },
                        growComposer() {
                            var el = this.$refs.composer;
                            if (!el) return;
                            el.style.height = 'auto';
                            var line = parseFloat(getComputedStyle(el).lineHeight) || 22;
                            var max = Math.round(line * 3 + 20); // ~3 lines + padding ≈ 4.5–5.5rem
                            if (max < 72) max = 84;
                            if (max > 96) max = 96;
                            var next = Math.min(el.scrollHeight, max);
                            el.style.height = Math.max(next, Math.round(line + 20)) + 'px';
                            el.style.overflowY = el.scrollHeight > max ? 'auto' : 'hidden';
                        },
                        scrollBottom() {
                            var el = this.$refs.scroll;
                            if (el) this.$nextTick(function () { el.scrollTop = el.scrollHeight; });
                        },
                        askSuggestion(suggestion) {
                            this.input = suggestion;
                            this.ask();
                        },
                        confirmGuestIdentity() {
                            var first = (this.guestFirstName || '').trim();
                            var last = (this.guestLastName || '').trim();
                            var phoneEl = this.$refs.guestGate
                                ? this.$refs.guestGate.querySelector('input[name="guest_phone"]')
                                : null;
                            var phone = phoneEl ? String(phoneEl.value || '').trim() : (this.guestPhone || '').trim();
                            if (! first || ! last || ! phone) {
                                this.sendError = config.guestIdentityHint || '';
                                return;
                            }
                            this.guestFirstName = first;
                            this.guestLastName = last;
                            this.guestName = (first + ' ' + last).trim();
                            this.guestPhone = phone;
                            this.guestReady = true;
                            this.sendError = '';
                            this.$nextTick(function () {
                                var self = this;
                                if (self.humanMode && config.threadUrl && !self._timer) {
                                    self.pollThread();
                                    self._timer = setInterval(function () { self.pollThread(); }, config.pollMs || 2000);
                                }
                            }.bind(this));
                        },
                        applyPresence(data) {
                            if (!data) return;
                            if (data.conversation_id) this.conversationId = data.conversation_id;
                            if (data.conversation_number) this.config.conversationNumber = data.conversation_number;
                            if (data.agent_first_name) {
                                this.agentFirstName = data.agent_first_name;
                                this.presence = 'assigned';
                            } else if (data.presence) {
                                this.presence = data.presence;
                                if (data.presence === 'online') this.agentFirstName = null;
                            } else if (data.assigned_to && !this.agentFirstName) {
                                this.presence = 'assigned';
                            }
                            if (data.status && ['closed', 'resolved'].indexOf(data.status) !== -1) {
                                this.composerLocked = true;
                            }
                        },
                        mapThread(rows) {
                            return (rows || []).map(function (m) {
                                return {
                                    id: m.id,
                                    role: m.role,
                                    text: m.text,
                                    time: m.time || (m.at ? String(m.at).slice(11, 16) : ''),
                                };
                            });
                        },
                        async pollThread() {
                            if (!this.humanMode || !config.threadUrl) return;
                            try {
                                var res = await fetch(config.threadUrl, {
                                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                    credentials: 'same-origin',
                                    cache: 'no-store',
                                });
                                if (!res.ok) return;
                                var data = await res.json();
                                if (!data.ok) return;
                                this.applyPresence(data);
                                if (!data.messages || !data.messages.length) return;
                                var next = this.mapThread(data.messages);
                                var prevSig = this.messages.map(function (m) { return String(m.id || '') + ':' + (m.text || ''); }).join('|');
                                var nextSig = next.map(function (m) { return String(m.id || '') + ':' + (m.text || ''); }).join('|');
                                if (prevSig !== nextSig) {
                                    this.messages = next;
                                    this.scrollBottom();
                                }
                            } catch (e) { /* keep polling */ }
                        },
                        async submitRating() {
                            if (!this.ratingUrl || !this.rating || this.ratingSending) return;
                            this.ratingSending = true;
                            this.sendError = '';
                            var self = this;
                            try {
                                var res = await fetch(this.ratingUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': this.csrfToken(),
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                    credentials: 'same-origin',
                                    body: JSON.stringify({ rating: this.rating, comment: this.ratingComment || null }),
                                });
                                var data = {};
                                try { data = await res.json(); } catch (e) { data = {}; }
                                if (!res.ok || data.ok === false) {
                                    self.sendError = data.message || data.error || 'Rating failed.';
                                    return;
                                }
                                self.ratingDone = true;
                                self.showRating = false;
                                self.composerLocked = true;
                                if (data.thanks) self.config.ratingThanks = data.thanks;
                                var go = data.redirect || null;
                                if (go) {
                                    setTimeout(function () { window.location = go; }, 1100);
                                }
                            } catch (e) {
                                self.sendError = 'Rating failed. Try again.';
                            } finally {
                                self.ratingSending = false;
                            }
                        },
                        matchReply(q) {
                            var lower = q.toLowerCase();
                            var rules = config.rules || [];
                            for (var i = 0; i < rules.length; i++) {
                                var keywords = rules[i].keywords || [];
                                for (var j = 0; j < keywords.length; j++) {
                                    var kw = String(keywords[j] || '').toLowerCase();
                                    if (kw && lower.indexOf(kw) !== -1) {
                                        return rules[i];
                                    }
                                }
                            }
                            return null;
                        },
                        selectProduct(product) {
                            this.showProductChips = false;
                            this.messages.push({ role: 'user', text: product.name });
                            this.typing = true;
                            var reply = product.summary;
                            if (product.url) reply += '\n' + product.url;
                            if (!config.memberMode) reply = reply + '\n\n' + config.registerPrompt;
                            var self = this;
                            setTimeout(function () {
                                self.messages.push({ role: 'bot', text: reply });
                                self.typing = false;
                                self.scrollBottom();
                            }, 500);
                        },
                        ask() {
                            var q = this.input.trim();
                            if (!q || this.typing) return;
                            if (this.needsGuestGate) {
                                this.sendError = config.guestIdentityHint || '';
                                return;
                            }
                            this.sendError = '';
                            var optimistic = { id: 'local-' + Date.now(), role: 'user', text: q, time: new Date().toTimeString().slice(0, 5) };
                            this.messages.push(optimistic);
                            this.input = '';
                            this.typing = true;
                            this.scrollBottom();
                            var self = this;

                            if (this.humanMode && config.speakUrl) {
                                var payload = { body: q };
                                if (config.needsGuestIdentity) {
                                    payload.guest_first_name = (this.guestFirstName || '').trim();
                                    payload.guest_last_name = (this.guestLastName || '').trim();
                                    payload.guest_name = (this.guestName || '').trim()
                                        || (payload.guest_first_name + ' ' + payload.guest_last_name).trim();
                                    payload.guest_phone = (this.guestPhone || '').trim();
                                }
                                fetch(config.speakUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': this.csrfToken(),
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                    credentials: 'same-origin',
                                    body: JSON.stringify(payload),
                                }).then(async function (r) {
                                    var data = {};
                                    try { data = await r.json(); } catch (e) { data = {}; }
                                    if (!r.ok || data.ok === false) {
                                        self.messages = self.messages.filter(function (m) { return m.id !== optimistic.id; });
                                        self.input = q;
                                        self.sendError = data.message || data.error || 'Imeshindikana kutuma. Jaribu tena.';
                                        self.typing = false;
                                        return;
                                    }
                                    self.applyPresence(data);
                                    if (data.messages && data.messages.length) {
                                        self.messages = self.mapThread(data.messages);
                                    } else if (data.ack) {
                                        self.messages.push({ role: 'bot', text: data.ack });
                                    }
                                    self.typing = false;
                                    self.scrollBottom();
                                }).catch(function () {
                                    self.messages = self.messages.filter(function (m) { return m.id !== optimistic.id; });
                                    self.input = q;
                                    self.sendError = 'Imeshindikana kutuma. Jaribu tena.';
                                    self.typing = false;
                                });
                                return;
                            }

                            var matched = this.matchReply(q);
                            var reply = matched ? (matched.answer || config.default) : config.default;
                            this.showProductChips = !!(matched && matched.follow_up === 'choose_product' && (config.products || []).length);
                            if (!config.memberMode && !this.showProductChips) {
                                reply = reply + '\n\n' + config.registerPrompt;
                            }
                            var delay = 600 + Math.floor(Math.random() * 900);
                            setTimeout(function () {
                                self.messages.push({ role: 'bot', text: reply });
                                self.typing = false;
                                self.scrollBottom();
                            }, delay);
                        },
                        init() {
                            var self = this;
                            this.scrollBottom();
                            this.$nextTick(function () { self.growComposer(); });
                            if (this.humanMode && config.threadUrl && !this.needsGuestGate) {
                                this.pollThread();
                                this._timer = setInterval(function () { self.pollThread(); }, config.pollMs || 2000);
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
</div>
