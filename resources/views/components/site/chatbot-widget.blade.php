<div x-data="siteChatbot()" x-cloak>
    <button type="button" @click="open = !open"
            class="fixed bottom-6 right-6 z-50 size-14 rounded-full bg-brand text-white shadow-[0_8px_32px_rgba(0,77,64,0.35)] hover:bg-brand-light transition-all hover:scale-105 flex items-center justify-center"
            :aria-expanded="open" aria-label="{{ __('site.support.assistant_title') }}">
        <svg x-show="!open" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        <svg x-show="open" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
    </button>

    <div x-show="open" x-transition.opacity.scale.95
         class="fixed bottom-24 right-6 z-50 w-[min(100vw-2rem,24rem)] glass-card overflow-hidden flex flex-col max-h-[min(70vh,32rem)] shadow-2xl">
        <div class="bg-brand text-white px-4 py-3 flex items-center gap-3">
            <div class="size-9 rounded-xl bg-white/15 grid place-items-center text-sm font-bold">AI</div>
            <div class="min-w-0">
                <p class="font-semibold text-sm">{{ __('site.support.assistant_title') }}</p>
                <p class="text-[11px] text-white/70 truncate">{{ __('site.support.assistant_subtitle') }}</p>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-3 bg-gradient-to-b from-brand-muted/30 to-white min-h-[12rem]">
            <template x-for="(msg, i) in messages" :key="i">
                <div :class="msg.role === 'user' ? 'text-right' : ''">
                    <span class="inline-block px-3 py-2 rounded-2xl text-sm max-w-[90%] text-left"
                          :class="msg.role === 'user' ? 'bg-brand text-white' : 'bg-white ring-1 ring-gray-200 text-gray-700'"
                          x-text="msg.text"></span>
                </div>
            </template>
        </div>

        <div class="px-3 pt-2 pb-1 border-t border-gray-100 bg-white">
            <p class="text-[10px] uppercase tracking-wider text-gray-400 mb-2">{{ __('site.support.suggested') }}</p>
            <div class="flex flex-wrap gap-1.5 mb-2">
                <template x-for="suggestion in suggestions" :key="suggestion">
                    <button type="button" @click="askSuggestion(suggestion)"
                            class="text-[11px] px-2.5 py-1 rounded-full bg-brand-muted text-brand hover:bg-brand/10 transition"
                            x-text="suggestion"></button>
                </template>
            </div>
        </div>

        <form @submit.prevent="ask" class="p-3 border-t border-gray-100 bg-white flex gap-2">
            <input type="text" x-model="input" :placeholder="@js(__('site.support.chat_placeholder'))"
                   class="flex-1 rounded-xl border-gray-200 text-sm focus:border-brand focus:ring-brand/20">
            <button type="submit" class="bg-brand hover:bg-brand-light text-white text-sm font-semibold px-4 py-2 rounded-xl transition">
                {{ __('site.support.chat_send') }}
            </button>
        </form>

        <div class="px-4 py-3 bg-gray-50 border-t border-gray-100 text-center">
            <a href="{{ route('site.support') }}" class="text-xs font-semibold text-brand hover:underline">{{ __('site.support.escalate') }} →</a>
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('siteChatbot', () => ({
            open: false,
            input: '',
            messages: [{ role: 'bot', text: @js(__('site.support.chat.greeting')) }],
            suggestions: @js(__('site.support.suggestions')),
            answers: @js(__('site.support.chat')),
            askSuggestion(text) {
                this.input = text;
                this.ask();
            },
            ask() {
                const q = this.input.trim();
                if (!q) return;
                this.messages.push({ role: 'user', text: q });
                this.input = '';
                const lower = q.toLowerCase();
                let reply = this.answers.default;
                if (lower.includes('product') || lower.includes('loan') || lower.includes('bidhaa') || lower.includes('mkopo')) reply = this.answers.products;
                else if (lower.includes('apply') || lower.includes('omb') || lower.includes('register') || lower.includes('jisajili')) reply = this.answers.apply;
                else if (lower.includes('market') || lower.includes('asset') || lower.includes('mali') || lower.includes('soko')) reply = this.answers.marketplace;
                else if (lower.includes('repay') || lower.includes('payment') || lower.includes('malipo') || lower.includes('lipa')) reply = this.answers.repayment;
                else if (lower.includes('member') || lower.includes('uanachama')) reply = this.answers.membership;
                else if (lower.includes('affiliate') || lower.includes('msambazaji') || lower.includes('commission')) reply = this.answers.affiliate;
                else if (lower.includes('contact') || lower.includes('support') || lower.includes('wasiliana') || lower.includes('msaada')) reply = this.answers.contact;
                this.messages.push({ role: 'bot', text: reply });
            },
        }));
    });
</script>
