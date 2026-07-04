<div x-data="borrowerHelpHub()" x-cloak class="fixed bottom-6 right-6 z-40 flex flex-col items-end gap-3">
    {{-- Choice menu --}}
    <div x-show="menuOpen && !chatOpen" x-transition.opacity.scale.95
         class="w-[min(100vw-2rem,16rem)] rounded-2xl glass-card overflow-hidden shadow-2xl mb-1">
        <div class="p-2">
            <button type="button" @click="openChat()"
                    class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-left hover:bg-brand-muted transition">
                <span class="size-10 rounded-xl bg-brand/10 text-brand grid place-items-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                </span>
                <span>
                    <span class="block text-sm font-semibold text-gray-900">{{ __('borrower.support_page.assistant_title') }}</span>
                    <span class="block text-[11px] text-gray-500">{{ __('site.help_hub.ask_question') }}</span>
                </span>
            </button>
            <a href="{{ route('site.feedback') }}"
               class="w-full flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-brand-muted transition">
                <span class="size-10 rounded-xl bg-brand-gold/20 text-brand grid place-items-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M7 8h10M7 12h6m-6 4h8M5 6a2 2 0 012-2h10a2 2 0 012 2v12l-4-2H7a2 2 0 01-2-2V6z"/></svg>
                </span>
                <span>
                    <span class="block text-sm font-semibold text-gray-900">{{ __('site.footer.feedback') }}</span>
                    <span class="block text-[11px] text-gray-500">{{ __('site.help_hub.send_feedback') }}</span>
                </span>
            </a>
            <a href="{{ route('site.borrower.support') }}"
               class="w-full flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-brand-muted transition">
                <span class="size-10 rounded-xl bg-gray-100 text-gray-600 grid place-items-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 18v.01M9.1 9a3 3 0 1 1 4.4 3.4c-1 .6-1.5 1.2-1.5 2.6M12 22a10 10 0 1 1 0-20 10 10 0 0 1 0 20z"/></svg>
                </span>
                <span>
                    <span class="block text-sm font-semibold text-gray-900">{{ __('borrower.layout.help_center') }}</span>
                    <span class="block text-[11px] text-gray-500">{{ __('borrower.support_page.subtitle') }}</span>
                </span>
            </a>
        </div>
    </div>

    {{-- Inline chat --}}
    <div x-show="chatOpen" x-transition.opacity.scale.95
         class="w-[min(100vw-2rem,24rem)] glass-card overflow-hidden flex flex-col max-h-[min(70vh,32rem)] shadow-2xl mb-1">
        <div class="bg-brand text-white px-4 py-3 flex items-center gap-3">
            <div class="size-9 rounded-xl bg-white/15 grid place-items-center text-sm font-bold">AI</div>
            <div class="min-w-0 flex-1">
                <p class="font-semibold text-sm">{{ __('borrower.support_page.assistant_title') }}</p>
                <p class="text-[11px] text-white/70 truncate">{{ __('borrower.support_page.assistant_subtitle') }}</p>
            </div>
            <button type="button" @click="chatOpen = false; menuOpen = false" class="p-1.5 rounded-lg hover:bg-white/10">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-4 space-y-3 bg-gradient-to-b from-brand-muted/30 to-white min-h-[10rem]">
            <template x-for="(msg, i) in messages" :key="i">
                <div :class="msg.role === 'user' ? 'text-right' : ''">
                    <span class="inline-block px-3 py-2 rounded-2xl text-sm max-w-[90%] text-left"
                          :class="msg.role === 'user' ? 'bg-brand text-white' : 'bg-white ring-1 ring-gray-200 text-gray-700'"
                          x-text="msg.text"></span>
                </div>
            </template>
        </div>
        <form @submit.prevent="ask" class="p-3 border-t border-gray-100 bg-white flex gap-2">
            <input type="text" x-model="input" :placeholder="@js(__('borrower.support_page.chat_placeholder'))"
                   class="flex-1 rounded-xl border-gray-200 text-sm focus:border-brand focus:ring-brand/20">
            <button type="submit" class="bg-brand hover:bg-brand-light text-white text-sm font-semibold px-4 py-2 rounded-xl">{{ __('borrower.support_page.chat_send') }}</button>
        </form>
    </div>

    <button type="button" @click="toggleMenu()"
            class="size-14 rounded-full bg-brand text-white shadow-[0_8px_32px_rgba(0,77,64,0.35)] hover:bg-brand-light transition-all hover:scale-105 flex items-center justify-center"
            aria-label="{{ __('site.help_hub.title') }}">
        <svg x-show="!menuOpen && !chatOpen" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <svg x-show="menuOpen || chatOpen" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('borrowerHelpHub', () => ({
        menuOpen: false,
        chatOpen: false,
        input: '',
        messages: [{ role: 'bot', text: @js(__('borrower.support_page.chat.greeting')) }],
        rules: [
            { keywords: ['loan', 'mkopo', 'apply', 'omb'], answer: @js(__('borrower.support_page.chat.loan')) },
            { keywords: ['pay', 'repay', 'malipo'], answer: @js(__('borrower.support_page.chat.repayment')) },
            { keywords: ['member', 'uanachama'], answer: @js(__('borrower.support_page.chat.membership')) },
            { keywords: ['guarantor', 'mdhamini'], answer: @js(__('borrower.support_page.chat.guarantor')) },
        ],
        defaultReply: @js(__('borrower.support_page.chat.default')),
        toggleMenu() {
            if (this.chatOpen) {
                this.chatOpen = false;
                this.menuOpen = false;
                return;
            }
            this.menuOpen = !this.menuOpen;
        },
        openChat() {
            this.menuOpen = false;
            this.chatOpen = true;
        },
        matchReply(q) {
            const lower = q.toLowerCase();
            for (const rule of this.rules) {
                for (const keyword of (rule.keywords || [])) {
                    if (lower.includes(keyword)) return rule.answer;
                }
            }
            return this.defaultReply;
        },
        ask() {
            const q = this.input.trim();
            if (!q) return;
            this.messages.push({ role: 'user', text: q });
            this.input = '';
            this.messages.push({ role: 'bot', text: this.matchReply(q) });
        },
    }));
});
</script>
