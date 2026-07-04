@props([
    'memberMode' => false,
    'agentLabel' => null,
    'agentSubtitle' => null,
])

@php
    $chat = app(\App\Services\ChatbotContentService::class)->payload();
    $agentLabel = $agentLabel ?? __('site.support.assistant_title');
    $agentSubtitle = $agentSubtitle ?? __('site.support.assistant_subtitle');
    $registerUrl = route('site.register.borrower');
    $registerPrompt = __('site.support.chat.register_prompt');
@endphp

<div {{ $attributes->merge(['class' => 'glass-card p-5 sm:p-6']) }}
     x-data="aiSupportChat(@js([
         'greeting' => $chat['greeting'],
         'default' => $chat['default'],
         'suggestions' => $chat['suggestions'],
         'rules' => $chat['rules'],
         'memberMode' => $memberMode,
         'registerPrompt' => $registerPrompt,
         'registerUrl' => $registerUrl,
         'typingLabel' => __('site.support.chat.typing'),
     ]))">
    <div class="flex items-center gap-3 mb-4">
        <div class="relative size-11 rounded-xl bg-brand text-white grid place-items-center font-bold text-sm shrink-0">
            AI
            <span class="absolute -bottom-0.5 -right-0.5 size-3 rounded-full bg-emerald-400 ring-2 ring-white"></span>
        </div>
        <div>
            <p class="font-semibold text-gray-900">{{ $agentLabel }}</p>
            <p class="text-xs text-gray-500">{{ $agentSubtitle }}</p>
        </div>
    </div>

    <div class="rounded-xl bg-gradient-to-b from-brand-muted/30 to-white border border-gray-100/80 p-4 max-h-56 overflow-y-auto space-y-3 text-sm mb-3">
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

    <div class="flex flex-wrap gap-2 mb-4">
        <template x-for="suggestion in config.suggestions" :key="suggestion">
            <button type="button" @click="askSuggestion(suggestion)" :disabled="typing"
                    class="text-xs px-3 py-1.5 rounded-full bg-brand-muted/80 text-brand hover:bg-brand/10 transition disabled:opacity-50"
                    x-text="suggestion"></button>
        </template>
    </div>

    <form @submit.prevent="ask" class="flex gap-2">
        <input type="text" x-model="input" :disabled="typing"
               placeholder="{{ __('site.support.chat_placeholder') }}"
               class="flex-1 rounded-xl border-gray-300 text-sm focus:border-brand focus:ring-brand disabled:opacity-60">
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
                if (Alpine.data('aiSupportChat')) return;

                Alpine.data('aiSupportChat', function (config) {
                    return {
                        config: config,
                        input: '',
                        typing: false,
                        messages: [{ role: 'bot', text: config.greeting }],
                        askSuggestion(text) {
                            this.input = text;
                            this.ask();
                        },
                        matchReply(q) {
                            var lower = q.toLowerCase();
                            var rules = config.rules || [];
                            for (var i = 0; i < rules.length; i++) {
                                var keywords = rules[i].keywords || [];
                                for (var j = 0; j < keywords.length; j++) {
                                    var kw = String(keywords[j] || '').toLowerCase();
                                    if (kw && lower.indexOf(kw) !== -1) {
                                        return rules[i].answer || config.default;
                                    }
                                }
                            }
                            return config.default;
                        },
                        ask() {
                            var q = this.input.trim();
                            if (!q || this.typing) return;
                            this.messages.push({ role: 'user', text: q });
                            this.input = '';
                            this.typing = true;

                            var reply = this.matchReply(q);
                            if (!config.memberMode) {
                                reply = reply + '\n\n' + config.registerPrompt;
                            }

                            var delay = 800 + Math.floor(Math.random() * 1400);
                            var self = this;
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
