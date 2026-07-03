<x-site.layout :title="brand_title(__('site.support.title'))">
    <section class="bg-brand text-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-14 text-center">
            <h1 class="text-3xl sm:text-4xl font-bold">{{ __('site.support.title') }}</h1>
            <p class="mt-3 text-white/80">{{ __('site.support.subtitle') }}</p>
        </div>
    </section>

    <section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16">
        <div class="glass-card p-6 mb-8" x-data="siteChatbot()">
            <div class="flex items-center gap-3 mb-4">
                <div class="size-12 rounded-2xl bg-brand text-white grid place-items-center font-bold">AI</div>
                <div>
                    <p class="font-bold text-lg">{{ __('site.support.assistant_title') }}</p>
                    <p class="text-sm text-gray-500">{{ __('site.support.assistant_subtitle') }}</p>
                </div>
            </div>

            <div class="rounded-2xl bg-gradient-to-b from-brand-muted/40 to-white border border-gray-100 p-4 max-h-64 overflow-y-auto space-y-3 text-sm mb-4">
                <template x-for="(msg, i) in messages" :key="i">
                    <div :class="msg.role === 'user' ? 'text-right' : ''">
                        <span class="inline-block px-3 py-2 rounded-2xl max-w-[90%] text-left"
                              :class="msg.role === 'user' ? 'bg-brand text-white' : 'bg-white ring-1 ring-gray-200 text-gray-700'"
                              x-text="msg.text"></span>
                    </div>
                </template>
            </div>

            <div class="flex flex-wrap gap-2 mb-4">
                <template x-for="suggestion in suggestions" :key="suggestion">
                    <button type="button" @click="askSuggestion(suggestion)"
                            class="text-xs px-3 py-1.5 rounded-full bg-brand-muted text-brand hover:bg-brand/10 transition"
                            x-text="suggestion"></button>
                </template>
            </div>

            <form @submit.prevent="ask" class="flex gap-2">
                <input type="text" x-model="input" placeholder="{{ __('site.support.chat_placeholder') }}"
                       class="flex-1 rounded-xl border-gray-200 text-sm focus:border-brand focus:ring-brand/10">
                <button type="submit" class="bg-brand hover:bg-brand-light text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition">{{ __('site.support.chat_send') }}</button>
            </form>
        </div>

        <div class="grid sm:grid-cols-3 gap-4 mb-8">
            <a href="tel:{{ preg_replace('/\s+/', '', brand('support_phone')) }}" class="glass-card p-5 hover:ring-2 hover:ring-brand/20 transition">
                <p class="text-2xl mb-2">📞</p>
                <p class="font-semibold">{{ __('site.nav.contact') }}</p>
                <p class="text-sm text-gray-500 mt-1">{{ brand('support_phone') }}</p>
            </a>
            <a href="mailto:{{ brand('support_email') }}" class="glass-card p-5 hover:ring-2 hover:ring-brand/20 transition">
                <p class="text-2xl mb-2">✉️</p>
                <p class="font-semibold">Email</p>
                <p class="text-sm text-gray-500 mt-1">{{ brand('support_email') }}</p>
            </a>
            <a href="{{ route('site.feedback') }}" class="glass-card p-5 hover:ring-2 hover:ring-brand/20 transition">
                <p class="text-2xl mb-2">💬</p>
                <p class="font-semibold">{{ __('site.footer.feedback') }}</p>
                <p class="text-sm text-gray-500 mt-1">{{ __('site.feedback.subtitle') }}</p>
            </a>
        </div>

        <div class="text-center">
            <a href="{{ route('site.faq') }}" class="text-sm font-semibold text-brand hover:underline">{{ __('site.footer.faq') }} →</a>
        </div>
    </section>

    <script>
        document.addEventListener('alpine:init', () => {
            if (! Alpine.data('siteChatbot')) {
                Alpine.data('siteChatbot', () => ({
                    input: '',
                    messages: [{ role: 'bot', text: @js(__('site.support.chat.greeting')) }],
                    suggestions: @js(__('site.support.suggestions')),
                    answers: @js(__('site.support.chat')),
                    askSuggestion(text) { this.input = text; this.ask(); },
                    ask() {
                        const q = this.input.trim();
                        if (!q) return;
                        this.messages.push({ role: 'user', text: q });
                        this.input = '';
                        const lower = q.toLowerCase();
                        let reply = this.answers.default;
                        if (lower.includes('product') || lower.includes('loan') || lower.includes('bidhaa') || lower.includes('mkopo')) reply = this.answers.products;
                        else if (lower.includes('apply') || lower.includes('omb') || lower.includes('register')) reply = this.answers.apply;
                        else if (lower.includes('market') || lower.includes('asset') || lower.includes('mali')) reply = this.answers.marketplace;
                        else if (lower.includes('repay') || lower.includes('payment') || lower.includes('malipo')) reply = this.answers.repayment;
                        else if (lower.includes('member') || lower.includes('uanachama')) reply = this.answers.membership;
                        else if (lower.includes('affiliate') || lower.includes('msambazaji')) reply = this.answers.affiliate;
                        else if (lower.includes('contact') || lower.includes('support') || lower.includes('wasiliana')) reply = this.answers.contact;
                        this.messages.push({ role: 'bot', text: reply });
                    },
                }));
            }
        });
    </script>
</x-site.layout>
