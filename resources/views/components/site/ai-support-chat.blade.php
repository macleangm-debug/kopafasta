@props([
    'memberMode' => false,
    'agentLabel' => null,
    'agentSubtitle' => null,
    'speakUrl' => null,
    'threadUrl' => null,
    'existingMessages' => null,
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
                'role' => in_array($m->sender_type ?? '', ['staff', 'bot'], true) ? 'bot' : 'user',
                'text' => (string) ($m->body ?? ''),
            ];
        }
    }
@endphp

<div {{ $attributes->merge(['class' => 'glass-card p-5 sm:p-6']) }}
     x-data="aiSupportChat(@js([
         'greeting' => $chat['greeting'],
         'default' => $chat['default'],
         'suggestions' => $chat['suggestions'],
         'rules' => $chat['rules'],
         'products' => $chat['products'],
         'chooseProductPrompt' => $chat['choose_product_prompt'],
         'memberMode' => $memberMode,
         'registerPrompt' => $registerPrompt,
         'registerUrl' => $registerUrl,
         'typingLabel' => __('site.support.chat.typing'),
         'speakUrl' => $speakUrl,
         'threadUrl' => $threadUrl,
         'csrf' => csrf_token(),
         'speakLabel' => __('borrower.support_page.speak_to_support'),
         'speakHint' => __('borrower.support_page.speak_to_support_hint'),
         'humanModeLabel' => __('borrower.support_page.human_mode_label'),
         'seedMessages' => $seedMessages,
     ]))">
    <div class="flex items-center gap-3 mb-4">
        <div class="relative size-11 rounded-xl bg-brand text-white grid place-items-center font-bold text-sm shrink-0">
            <span x-text="humanMode ? 'CS' : 'AI'"></span>
            <span class="absolute -bottom-0.5 -right-0.5 size-3 rounded-full bg-emerald-400 ring-2 ring-white"></span>
        </div>
        <div class="min-w-0 flex-1">
            <p class="font-semibold text-gray-900" x-text="humanMode ? config.humanModeLabel : @js($agentLabel)"></p>
            <p class="text-xs text-gray-500" x-text="humanMode ? config.speakHint : @js($agentSubtitle)"></p>
        </div>
    </div>

    <div class="rounded-xl bg-gradient-to-b from-brand-muted/30 to-white border border-gray-100/80 p-4 max-h-56 overflow-y-auto space-y-3 text-sm mb-3" x-ref="scroll">
        <template x-for="(msg, i) in messages" :key="i">
            <div :class="msg.role === 'user' ? 'text-right' : ''">
                <span class="inline-block px-3 py-2 rounded-2xl max-w-[92%] text-left whitespace-pre-wrap"
                      :class="msg.role === 'user' ? 'bg-brand text-white' : 'bg-white ring-1 ring-gray-200/80 text-gray-700'"
                      x-text="msg.text"></span>
            </div>
        </template>
        <div x-show="typing" x-cloak class="flex items-center gap-2 text-xs text-gray-500">
            <span class="inline-flex gap-1">
                <span class="size-1.5 rounded-full bg-gray-400 animate-bounce" style="animation-delay: 0ms"></span>
                <span class="size-1.5 rounded-full bg-gray-400 animate-bounce" style="animation-delay: 150ms"></span>
                <span class="size-1.5 rounded-full bg-gray-400 animate-bounce" style="animation-delay: 300ms"></span>
            </span>
            <span x-text="config.typingLabel"></span>
        </div>
    </div>

    <div class="flex flex-wrap gap-2 mb-4" x-show="!showProductChips && !humanMode">
        <template x-for="suggestion in config.suggestions" :key="suggestion">
            <button type="button" @click="askSuggestion(suggestion)" :disabled="typing"
                    class="text-xs px-3 py-1.5 rounded-full bg-brand-muted/80 text-brand hover:bg-brand/10 transition disabled:opacity-50"
                    x-text="suggestion"></button>
        </template>
    </div>

    <div class="mb-4 space-y-2" x-show="showProductChips && !humanMode" x-cloak>
        <p class="text-xs font-semibold text-gray-600" x-text="config.chooseProductPrompt"></p>
        <div class="flex flex-wrap gap-2">
            <template x-for="product in config.products" :key="product.code">
                <button type="button" @click="selectProduct(product)" :disabled="typing"
                        class="text-xs px-3 py-1.5 rounded-full ring-1 ring-brand/25 bg-white text-brand hover:bg-brand-muted transition disabled:opacity-50"
                        x-text="product.name"></button>
            </template>
        </div>
    </div>

    @if ($memberMode && $speakUrl)
        <div class="mb-3" x-show="!humanMode">
            <button type="button" @click="startHuman()"
                    class="w-full sm:w-auto inline-flex justify-center rounded-xl bg-brand-gold text-brand text-sm font-bold px-4 py-2.5 hover:brightness-95">
                {{ __('borrower.support_page.speak_to_support') }}
            </button>
        </div>
        <div class="mb-3" x-show="humanMode" x-cloak>
            <button type="button" @click="humanMode = false"
                    class="text-xs font-semibold text-brand hover:underline">{{ __('borrower.support_page.back_to_assistant') }}</button>
        </div>
    @endif

    <form @submit.prevent="ask" class="flex gap-2">
        <input type="text" x-model="input" :disabled="typing"
               placeholder="{{ __('site.support.chat_placeholder') }}"
               class="flex-1 rounded-xl border border-gray-300 px-3 py-2.5 text-sm focus:border-brand focus:ring-2 focus:ring-brand/10 disabled:opacity-60">
        <button type="submit" :disabled="typing"
                class="bg-brand hover:bg-brand-light disabled:opacity-60 text-white text-sm font-semibold px-4 py-2 rounded-xl">
            {{ __('site.support.chat_send') }}
        </button>
    </form>

    @unless ($memberMode)
        <p class="mt-3 text-xs text-gray-500">
            {{ __('site.support.chat.guest_hint') }}
            <a href="{{ $registerUrl }}" class="font-semibold text-brand hover:underline">{{ __('site.hero.get_started') }}</a>
        </p>
    @endunless

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
                        humanMode: !!(config.seedMessages && config.seedMessages.length),
                        showProductChips: false,
                        messages: seeded,
                        askSuggestion(text) {
                            this.input = text;
                            this.ask();
                        },
                        startHuman() {
                            this.humanMode = true;
                            this.showProductChips = false;
                            this.messages.push({
                                role: 'bot',
                                text: config.speakHint || 'Andika ujumbe wako. Wakala wa usaidizi atakuona kwenye Inbox.',
                            });
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
                            if (product.url) {
                                reply += '\n' + product.url;
                            }
                            if (!config.memberMode) {
                                reply = reply + '\n\n' + config.registerPrompt;
                            }
                            var self = this;
                            setTimeout(function () {
                                self.messages.push({ role: 'bot', text: reply });
                                self.typing = false;
                            }, 500);
                        },
                        contextSnippet() {
                            var parts = [];
                            this.messages.slice(-6).forEach(function (m) {
                                if (m.role === 'user') parts.push('Member: ' + m.text);
                                else parts.push('Bot: ' + m.text);
                            });
                            return parts.join('\n').slice(0, 3500);
                        },
                        ask() {
                            var q = this.input.trim();
                            if (!q || this.typing) return;
                            this.messages.push({ role: 'user', text: q });
                            this.input = '';
                            this.typing = true;
                            var self = this;

                            if (this.humanMode && config.speakUrl) {
                                fetch(config.speakUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': config.csrf,
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                    body: JSON.stringify({
                                        body: q,
                                        context: this.contextSnippet(),
                                    }),
                                }).then(function (r) { return r.json(); }).then(function (data) {
                                    self.messages.push({ role: 'bot', text: data.ack || 'Tumepokea ombi lako.' });
                                    self.typing = false;
                                    self.$nextTick(function () {
                                        if (self.$refs.scroll) self.$refs.scroll.scrollTop = self.$refs.scroll.scrollHeight;
                                    });
                                }).catch(function () {
                                    self.messages.push({ role: 'bot', text: 'Imeshindikana kutuma. Jaribu tena.' });
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
                            }, delay);
                        },
                    };
                });
            });
        </script>
    @endonce
</div>
