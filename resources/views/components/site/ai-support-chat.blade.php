@props([
    'memberMode' => false,
    'forceHuman' => false,
    'automationMode' => false,
    'agentLabel' => null,
    'agentSubtitle' => null,
    'speakUrl' => null,
    'threadUrl' => null,
    'automationUrl' => null,
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
    $automationMode = (bool) $automationMode;
    $startHuman = ($forceHuman && ! $automationMode) || (
        count($seedMessages) > 0
        && $conversation instanceof \App\Models\SupportConversation
        && (bool) ($conversation->needs_human ?? false)
    );
    $presence = app(\App\Services\Support\SupportConversationService::class)
        ->memberChatPresence($conversation instanceof \App\Models\SupportConversation ? $conversation : null);
    // Parent provides focused/centered width; chat fills that container (not a left-aligned max-w-2xl island).
    $shellClass = ($forceHuman || $automationMode)
        ? 'overflow-hidden rounded-2xl ring-1 ring-brand/15 shadow-sm bg-white w-full'
        : 'glass-card p-5 sm:p-6 w-full';
    $isSw = str_starts_with(app()->getLocale(), 'sw');
    $composerLocked = $conversation instanceof \App\Models\SupportConversation
        && in_array((string) $conversation->status, ['closed', 'resolved'], true);
    // Public guests identify before starting (automation or human). Member/partner skip the gate.
    $needsGuestIdentity = ! $memberMode && ($forceHuman || $automationMode);
    $guestMayEscalate = ! $memberMode && $automationMode;
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
         'greeting' => $automationMode
             ? ($isSw
                 ? 'Habari. Mimi ni Msaidizi wa Kopafasta. Ninaweza kukusaidia saa 24. Unahitaji msaada kuhusu nini?'
                 : 'Hello. I am the Kopafasta Assistant. I can help 24/7. What do you need help with?')
             : ($forceHuman
                 ? __('borrower.support_page.speak_to_support_hint')
                 : $chat['greeting']),
         'default' => $chat['default'],
         'suggestions' => ($forceHuman || $automationMode) ? [] : $chat['suggestions'],
         'rules' => ($forceHuman || $automationMode) ? [] : $chat['rules'],
         'products' => ($forceHuman || $automationMode) ? [] : $chat['products'],
         'chooseProductPrompt' => $chat['choose_product_prompt'],
         'memberMode' => $memberMode,
         'forceHuman' => (bool) $forceHuman,
         'automationMode' => (bool) $automationMode,
         'automationUrl' => $automationUrl,
         'needsGuestIdentity' => (bool) $needsGuestIdentity,
         'guestMayEscalate' => (bool) $guestMayEscalate,
         'guestFirstName' => $guestFirstName,
         'guestLastName' => $guestLastName,
         'guestName' => trim($guestFirstName.' '.$guestLastName),
         'guestPhone' => (string) ($guestPhone ?? ''),
         'guestIdentityHint' => $isSw
             ? 'Kabla ya kuanza mazungumzo, andika jina la kwanza, jina la mwisho na namba ya simu (si usajili).'
             : 'Before starting the conversation, enter your first name, last name and phone (not registration).',
         'guestFirstNameLabel' => $isSw ? 'Jina la kwanza' : 'First name',
         'guestLastNameLabel' => $isSw ? 'Jina la mwisho' : 'Last name',
         'guestPhoneLabel' => $isSw ? 'Namba ya simu' : 'Phone number',
         'guestContinue' => $isSw ? 'Anza mazungumzo' : 'Start chat',
         'guestFirstRequired' => $isSw ? 'Andika jina la kwanza.' : 'Enter your first name.',
         'guestLastRequired' => $isSw ? 'Andika jina la mwisho.' : 'Enter your last name.',
         'guestPhoneRequired' => $isSw ? 'Andika namba ya simu.' : 'Enter your phone number.',
         'registerPrompt' => $registerPrompt,
         'registerUrl' => $registerUrl,
         'typingLabel' => __('site.support.chat.typing'),
         'speakUrl' => $speakUrl,
         'threadUrl' => $threadUrl,
         'csrf' => csrf_token(),
         'seedMessages' => $seedMessages,
         'startHuman' => $startHuman,
         'pollMs' => 2000,
         'responseDelayMinMs' => 1000,
         'responseDelayMaxMs' => 2000,
         'conversationId' => $conversation?->id,
         'conversationNumber' => $conversation instanceof \App\Models\SupportConversation ? $conversation->publicNumber() : null,
         'agentFirstName' => $presence['agent_first_name'],
         'presence' => $presence['presence'],
         'statusOnline' => $isSw ? 'Inasubiri mtoa huduma' : 'Waiting for support',
         'statusAssigned' => $isSw ? 'Mtoa huduma ameteuliwa' : 'Agent assigned',
         'tagline' => $automationMode
             ? ($isSw ? 'Msaidizi wa kidijitali · Digital assistant' : 'Digital assistant · Here to help')
             : ($isSw ? 'Kwa ajili yako · Here to help' : 'Here to help'),
         'brandTitle' => $automationMode
             ? ($isSw ? 'Msaidizi wa Kopafasta' : 'Kopafasta Assistant')
             : 'Kopafasta Support',
         'assignedSuffix' => $isSw ? 'Usaidizi kwa Wateja' : 'Customer Support',
         'deskLabel' => $presence['desk_label'] ?? ($isSw ? 'Inasubiri mtoa huduma' : 'Waiting for support'),
         'automationDesk' => $isSw ? 'Msaidizi wa Kopafasta' : 'Kopafasta Assistant',
         'personaDisplay' => null,
         'showRating' => (bool) $showRating,
         'ratingUrl' => $ratingUrl,
         'composerLocked' => (bool) $composerLocked || (bool) ($presence['composer_locked'] ?? false),
         'ratingThanks' => $isSw ? 'Asante kwa tathmini yako.' : 'Thank you for your rating.',
         'ratingPrompt' => $isSw ? 'Tathmini huduma yetu' : 'Rate our support',
         'ratingCommentPh' => $isSw ? 'Maoni (si lazima)' : 'Comment (optional)',
         'ratingSend' => $isSw ? 'Tuma tathmini' : 'Submit rating',
     ]))">
    @if ($forceHuman || $automationMode)
        {{-- Premium support header — compact; no phone/website --}}
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
                           x-text="agentFirstName
                                ? (agentFirstName + ' · ' + config.assignedSuffix)
                                : (config.personaDisplay || config.brandTitle)"></p>
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
            <div x-show="needsGuestGate" x-cloak class="mb-4 rounded-2xl bg-brand-muted/40 ring-1 ring-brand/15 p-4 space-y-3" x-ref="guestGate"
                 @input.capture="syncGuestPhone()" @change.capture="syncGuestPhone()" @keyup.capture="syncGuestPhone()">
                <p class="text-sm text-gray-700" x-text="config.guestIdentityHint"></p>
                <div class="grid sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1" x-text="config.guestFirstNameLabel"></label>
                        <input type="text" x-model="guestFirstName" maxlength="60" autocomplete="given-name"
                               class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-brand focus:ring-2 focus:ring-brand/10"
                               :class="guestFieldErrors.first ? 'border-red-400' : ''">
                        <p x-show="guestFieldErrors.first" x-cloak class="mt-1 text-xs text-red-600" x-text="guestFieldErrors.first"></p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1" x-text="config.guestLastNameLabel"></label>
                        <input type="text" x-model="guestLastName" maxlength="60" autocomplete="family-name"
                               class="w-full rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-brand focus:ring-2 focus:ring-brand/10"
                               :class="guestFieldErrors.last ? 'border-red-400' : ''">
                        <p x-show="guestFieldErrors.last" x-cloak class="mt-1 text-xs text-red-600" x-text="guestFieldErrors.last"></p>
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
                        :required="false"
                    />
                    <p x-show="guestFieldErrors.phone" x-cloak class="mt-1 text-xs text-red-600" x-text="guestFieldErrors.phone"></p>
                </div>
                <button type="button" @click="confirmGuestIdentity()"
                        class="w-full rounded-xl bg-brand text-white text-sm font-semibold px-4 py-2.5 disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="!guestFormReady"
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

    <div x-show="!needsGuestGate" x-cloak class="rounded-xl bg-gradient-to-b from-brand-muted/30 to-white border border-gray-100/80 p-3.5 max-h-80 overflow-y-auto space-y-2.5 text-[15px] mb-3" x-ref="scroll">
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


    <div class="flex flex-wrap gap-2 mb-4" x-show="automationMode && !humanMode && choices.length && !showRating && !needsGuestGate" x-cloak>
        <template x-for="choice in choices" :key="(choice.action||'')+'-'+(choice.key||choice.label)">
            <button type="button" @click="pickChoice(choice)" :disabled="typing"
                    class="text-sm px-3 py-1.5 rounded-full bg-brand-muted/80 text-brand hover:bg-brand/10 transition disabled:opacity-50 text-left"
                    x-text="choice.label"></button>
        </template>
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

    <form @submit.prevent="ask" class="flex gap-2 items-end" x-show="!(showRating || ratingDone || composerLocked || needsGuestGate || (automationMode && !humanMode && choices.length))">
        <textarea x-model="input" :disabled="typing" x-ref="composer" rows="1"
               @input="growComposer()"
               placeholder="{{ __('site.support.chat_placeholder') }}"
               class="kf-support-composer flex-1 rounded-xl border border-gray-300 px-3.5 py-2.5 text-base focus:border-brand focus:ring-2 focus:ring-brand/10 disabled:opacity-60"></textarea>
        <button type="submit" :disabled="typing || !input.trim()"
                class="bg-brand hover:bg-brand-light disabled:opacity-60 text-white text-sm font-semibold px-4 py-2.5 rounded-xl shrink-0">
            {{ __('site.support.chat_send') }}
        </button>
    </form>

    <div x-show="joinCta && joinCta.url" x-cloak class="mt-3 rounded-2xl bg-gradient-to-br from-brand-muted/70 to-white ring-1 ring-brand/15 shadow-sm p-4 sm:p-5">
        <p class="text-[10px] uppercase tracking-[0.18em] font-bold text-brand">Kopafasta</p>
        <h3 class="mt-1 text-sm sm:text-base font-bold text-gray-900 leading-snug"
            x-text="joinCta.title || ''"></h3>
        <p class="mt-1 text-xs sm:text-sm text-gray-600 leading-relaxed"
           x-text="joinCta.body || joinCta.prompt || ''"></p>
        <a :href="joinCta.url"
           class="mt-3 inline-flex w-full sm:w-auto items-center justify-center rounded-xl bg-brand-gold hover:bg-yellow-400 text-brand font-bold text-sm px-5 py-2.5 shadow-sm transition"
           x-text="joinCta.label || 'Anza Sasa'"></a>
    </div>

    <div x-show="actionCta && actionCta.url" x-cloak class="mt-3">
        <a :href="actionCta.url"
           class="inline-flex w-full sm:w-auto items-center justify-center rounded-xl bg-brand hover:bg-brand-light text-white font-bold text-sm px-5 py-2.5 shadow-sm transition"
           x-text="actionCta.label || 'Continue'"></a>
    </div>

    @unless ($memberMode)
        <div x-show="!needsGuestGate && !joinCta" x-cloak class="mt-3">
            <x-site.guest-conversion-card :url="$registerUrl" compact />
        </div>
    @endunless

    @if ($forceHuman || $automationMode)
        </div>
    @endif

    @once
        <script>
            document.addEventListener('alpine:init', function () {
                Alpine.data('aiSupportChat', function (config) {
                    var guestAlreadyReady = !!(config.guestFirstName || '').trim()
                        && !!(config.guestLastName || '').trim()
                        && String(config.guestPhone || '').replace(/\D/g, '').length >= 9;
                    // Guests must not see a conversation thread until identity is confirmed.
                    var seeded;
                    if (config.needsGuestIdentity && !guestAlreadyReady) {
                        seeded = [];
                    } else if (config.seedMessages && config.seedMessages.length) {
                        seeded = config.seedMessages.slice();
                    } else if (config.automationMode) {
                        seeded = [];
                    } else {
                        seeded = [{ role: 'bot', text: config.greeting }];
                    }
                    return {
                        config: config,
                        input: '',
                        typing: false,
                        sendError: '',
                        messages: seeded,
                        humanMode: !!(config.startHuman || config.forceHuman),
                        automationMode: !!config.automationMode,
                        choices: [],
                        pendingEscalate: false,
                        showProductChips: false,
                        conversationId: config.conversationId || null,
                        agentFirstName: config.agentFirstName || null,
                        presence: config.presence || 'online',
                        showRating: !!config.showRating,
                        ratingUrl: config.ratingUrl || null,
                        composerLocked: !!config.composerLocked,
                        joinCta: null,
                        actionCta: null,
                        rating: 0,
                        hoverStar: 0,
                        ratingComment: '',
                        ratingSending: false,
                        ratingDone: false,
                        guestFirstName: config.guestFirstName || '',
                        guestLastName: config.guestLastName || '',
                        guestName: config.guestName || '',
                        guestPhone: config.guestPhone || '',
                        guestFieldErrors: { first: '', last: '', phone: '' },
                        guestPhoneDigits: String(config.guestPhone || '').replace(/\D/g, ''),
                        guestReady: !!(config.guestFirstName || '').trim()
                            && !!(config.guestLastName || '').trim()
                            && String(config.guestPhone || '').replace(/\D/g, '').length >= 9,
                        get needsGuestGate() {
                            if (this.pendingEscalate && !this.guestReady) return true;
                            return !!config.needsGuestIdentity && !this.guestReady;
                        },
                        get guestFormReady() {
                            return !!(this.guestFirstName || '').trim()
                                && !!(this.guestLastName || '').trim()
                                && String(this.guestPhoneDigits || '').length >= 9;
                        },
                        syncGuestPhone() {
                            var digits = this.readGuestPhone();
                            this.guestPhoneDigits = digits;
                            var phoneEl = this.$refs.guestGate
                                ? this.$refs.guestGate.querySelector('input[name="guest_phone"]')
                                : null;
                            if (phoneEl && phoneEl.value) {
                                this.guestPhone = String(phoneEl.value || '').trim();
                            } else if (digits.length >= 9 && digits.indexOf('255') !== 0) {
                                this.guestPhone = '+255' + digits.replace(/^0+/, '');
                            } else if (digits.length >= 9) {
                                this.guestPhone = '+' + digits;
                            }
                        },
                        readGuestPhone() {
                            var root = this.$refs.guestGate;
                            if (!root) {
                                return String(this.guestPhone || '').replace(/\D/g, '');
                            }
                            var hidden = root.querySelector('input[name="guest_phone"]');
                            var local = root.querySelector('input[data-phone-local], input[name="guest_phone_local"]');
                            var raw = '';
                            if (hidden && String(hidden.value || '').trim()) {
                                raw = String(hidden.value || '').trim();
                            } else if (local && String(local.value || '').trim()) {
                                // Local digits only — prefix is locked +255 for TZ.
                                var prefixEl = root.querySelector('[data-phone-prefix]');
                                var prefix = prefixEl
                                    ? String(prefixEl.value || prefixEl.getAttribute('value') || '255').replace(/\D/g, '')
                                    : '255';
                                raw = prefix + String(local.value || '').replace(/\D/g, '').replace(/^0+/, '');
                            } else {
                                raw = String(this.guestPhone || '').trim();
                            }
                            return String(raw || '').replace(/\D/g, '');
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

                        applyAutomation(data) {
                            if (!data) return;
                            if (data.conversation_id) this.conversationId = data.conversation_id;
                            if (data.conversation_number) this.config.conversationNumber = data.conversation_number;
                            if (data.messages && data.messages.length) {
                                this.messages = this.mapThread(data.messages);
                            }
                            this.choices = data.choices || [];
                            if (data.composer_locked) this.composerLocked = true;
                            if (data.join_cta && data.join_cta.url) {
                                this.joinCta = data.join_cta;
                            }
                            if (data.cta && data.cta.url) {
                                this.actionCta = data.cta;
                            } else if (! data.join_cta) {
                                this.actionCta = null;
                            }
                            if (data.persona_display) {
                                this.config.personaDisplay = data.persona_display;
                                this.config.brandTitle = data.persona_display;
                            }
                            if (data.handling_label) {
                                this.config.deskLabel = data.handling_label;
                            }
                            if (data.show_rating) {
                                this.showRating = true;
                                this.composerLocked = true;
                                this.choices = [];
                                if (data.rating_url) this.ratingUrl = data.rating_url;
                                if (data.rating_prompt) this.config.ratingPrompt = data.rating_prompt;
                            }
                            if (data.mode === 'human' || data.needs_human) {
                                this.humanMode = true;
                                this.automationMode = false;
                                this.choices = [];
                                this.applyPresence(data);
                                if (data.composer_locked) this.composerLocked = true;
                                if (config.threadUrl && !this._timer) {
                                    this.pollThread();
                                    var self = this;
                                    this._timer = setInterval(function () { self.pollThread(); }, config.pollMs || 2000);
                                }
                            }
                            if (data.ticket_number) {
                                this.config.conversationNumber = data.ticket_number;
                            }
                            if (data.needs_guest) {
                                this.pendingEscalate = true;
                            }
                            this.scrollBottom();
                        },
                        paceDelay() {
                            var min = Number(config.responseDelayMinMs || 1000);
                            var max = Number(config.responseDelayMaxMs || 2000);
                            if (max < min) max = min;
                            return min + Math.floor(Math.random() * (max - min + 1));
                        },
                        async runAutomation(action, extra) {
                            if (!config.automationUrl || this.typing) return;
                            this.typing = true;
                            this.sendError = '';
                            var payload = Object.assign({
                                action: action,
                                conversation_id: this.conversationId || null,
                            }, extra || {});
                            if (config.guestMayEscalate || config.needsGuestIdentity) {
                                payload.guest_first_name = (this.guestFirstName || '').trim();
                                payload.guest_last_name = (this.guestLastName || '').trim();
                                payload.guest_name = (this.guestName || '').trim()
                                    || (payload.guest_first_name + ' ' + payload.guest_last_name).trim();
                                payload.guest_phone = (this.guestPhone || '').trim();
                            }
                            var self = this;
                            var startedAt = Date.now();
                            try {
                                var res = await fetch(config.automationUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': this.csrfToken(),
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                    credentials: 'same-origin',
                                    body: JSON.stringify(payload),
                                });
                                var data = {};
                                try { data = await res.json(); } catch (e) { data = {}; }
                                if (!res.ok || data.ok === false) {
                                    self.sendError = data.message || data.error || 'Imeshindikana. Jaribu tena.';
                                    self.typing = false;
                                    return;
                                }
                                var wait = Math.max(0, self.paceDelay() - (Date.now() - startedAt));
                                await new Promise(function (resolve) { setTimeout(resolve, wait); });
                                self.applyAutomation(data);
                            } catch (e) {
                                self.sendError = 'Imeshindikana. Jaribu tena.';
                            } finally {
                                self.typing = false;
                            }
                        },
                        pickChoice(choice) {
                            if (!choice || this.typing) return;
                            var action = choice.action || '';
                            if (action === 'escalate' && config.guestMayEscalate && !this.guestReady) {
                                this.pendingEscalate = true;
                                return;
                            }
                            var extra = { key: choice.key, slug: choice.key };
                            this.runAutomation(action, extra);
                        },
                        askSuggestion(suggestion) {
                            this.input = suggestion;
                            this.ask();
                        },
                        confirmGuestIdentity() {
                            var first = (this.guestFirstName || '').trim();
                            var last = (this.guestLastName || '').trim();
                            var phoneDigits = this.readGuestPhone();
                            var phoneEl = this.$refs.guestGate
                                ? this.$refs.guestGate.querySelector('input[name="guest_phone"]')
                                : null;
                            var phone = phoneEl ? String(phoneEl.value || '').trim() : (this.guestPhone || '').trim();
                            this.guestFieldErrors = {
                                first: first ? '' : (config.guestFirstRequired || ''),
                                last: last ? '' : (config.guestLastRequired || ''),
                                phone: phoneDigits.length >= 9 ? '' : (config.guestPhoneRequired || ''),
                            };
                            if (! first || ! last || phoneDigits.length < 9) {
                                this.sendError = '';
                                return;
                            }
                            this.guestFirstName = first;
                            this.guestLastName = last;
                            this.guestName = (first + ' ' + last).trim();
                            this.guestPhone = phone;
                            this.syncGuestPhone();
                            this.guestReady = true;
                            this.sendError = '';
                            if (this.pendingEscalate) {
                                this.pendingEscalate = false;
                                this.runAutomation('escalate', { key: 'human' });
                                return;
                            }
                            if (this.automationMode && config.automationUrl && !this.humanMode) {
                                this.runAutomation('start', {});
                                return;
                            }
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
                            if (data.desk_label) this.config.deskLabel = data.desk_label;
                            if (data.composer_locked) this.composerLocked = true;
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
                                        if (data.composer_locked) {
                                            self.composerLocked = true;
                                            if (data.messages && data.messages.length) {
                                                self.messages = self.mapThread(data.messages);
                                            }
                                        }
                                        self.typing = false;
                                        return;
                                    }
                                    self.applyPresence(data);
                                    if (data.composer_locked) self.composerLocked = true;
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
                            this.$nextTick(function () {
                                self.growComposer();
                                self.syncGuestPhone();
                            });
                            if (this.needsGuestGate) {
                                return;
                            }
                            if (this.automationMode && config.automationUrl && !this.humanMode) {
                                this.runAutomation('start', {});
                                return;
                            }
                            if (this.humanMode && config.threadUrl) {
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
