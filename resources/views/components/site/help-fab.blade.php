@php
    $isSw = str_starts_with(app()->getLocale(), 'sw');
    $phones = support_phones();
    $primaryPhone = $phones[0] ?? null;
    $variant = $variant ?? 'public'; // public | borrower
    $chatbot = $variant === 'public'
        ? app(\App\Services\ChatbotContentService::class)->payload()
        : null;
    $hasActive = false;
    if ($variant === 'borrower' && auth()->user()?->customer?->id) {
        $hasActive = \App\Models\SupportConversation::query()
            ->where('customer_id', auth()->user()->customer->id)
            ->whereNotIn('status', ['closed', 'resolved'])
            ->exists();
    }
@endphp

{{-- Shared floating help control — same chrome for public site and borrower account shell. --}}
<div {{ $attributes->class([
         'fixed bottom-6 right-6 z-40 print:hidden',
         'hidden lg:block' => $variant === 'borrower' || request()->boolean('chat'),
     ]) }}
     x-data="kfHelpFab(@js([
         'variant' => $variant,
         'greeting' => $chatbot['greeting'] ?? '',
         'suggestions' => $chatbot['suggestions'] ?? [],
         'rules' => $chatbot['rules'] ?? [],
         'default' => $chatbot['default'] ?? '',
     ]))"
     @keydown.escape.window="closeAll()">
    <div x-show="menuOpen && !chatOpen" @click.outside="menuOpen = false" x-cloak
         x-transition class="absolute bottom-16 right-0 w-72 rounded-2xl glass-card overflow-hidden shadow-xl">
        <div class="px-4 py-3 border-b border-gray-100/80 bg-brand text-white">
            <p class="text-sm font-bold">Kopafasta Support</p>
            <p class="text-xs text-white/80 mt-0.5">
                {{ $variant === 'borrower' ? __('site.help_hub.from_account') : ($isSw ? 'Msaada wa umma' : 'Public help') }}
            </p>
        </div>
        <div class="p-2 bg-white/95">
            @if ($variant === 'borrower')
                <a href="{{ route('site.borrower.support') }}"
                   class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 hover:bg-brand-muted">
                    <span class="size-8 rounded-lg bg-brand-muted text-brand grid place-items-center shrink-0">⌕</span>
                    {{ __('site.help_hub.search_help') }}
                </a>
                <a href="{{ route('site.borrower.support') }}"
                   class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 hover:bg-brand-muted">
                    <span class="size-8 rounded-lg bg-brand-muted text-brand grid place-items-center shrink-0">?</span>
                    {{ __('site.help_hub.faq_howto') }}
                </a>
                @if ($hasActive)
                    <a href="{{ route('site.borrower.support', ['chat' => 1]) }}"
                       class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-brand hover:bg-brand-muted">
                        <span class="size-8 rounded-lg bg-brand text-white grid place-items-center shrink-0">💬</span>
                        {{ __('site.help_hub.continue_chat') }}
                    </a>
                @endif
                <a href="{{ route('site.borrower.support', ['chat' => 1]) }}"
                   class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 hover:bg-brand-muted">
                    <span class="size-8 rounded-lg bg-brand-muted text-brand grid place-items-center shrink-0">☎</span>
                    {{ $isSw ? 'Anza mazungumzo' : 'Start a conversation' }}
                </a>
                <button type="button" @click="openFeedback(); menuOpen = false"
                        class="w-full flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 hover:bg-brand-muted text-left">
                    <span class="size-8 rounded-lg bg-brand-muted text-brand grid place-items-center shrink-0">✎</span>
                    {{ $isSw ? 'Tuma maoni' : 'Send feedback' }}
                </button>
                @if ($primaryPhone)
                    <a href="tel:{{ preg_replace('/\s+/', '', $primaryPhone) }}"
                       class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 hover:bg-brand-muted">
                        <span class="size-8 rounded-lg bg-brand-muted text-brand grid place-items-center shrink-0">📞</span>
                        {{ $isSw ? 'Piga Kopafasta' : 'Call Kopafasta' }} · {{ $primaryPhone }}
                    </a>
                @endif
            @else
                <button type="button" @click="openChat()"
                        class="w-full flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 hover:bg-brand-muted text-left">
                    <span class="size-8 rounded-lg bg-brand-muted text-brand grid place-items-center shrink-0">💬</span>
                    {{ __('site.support.assistant_title') }}
                </button>
                <a href="{{ route('site.support.chat') }}"
                   class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 hover:bg-brand-muted">
                    <span class="size-8 rounded-lg bg-brand-muted text-brand grid place-items-center shrink-0">☎</span>
                    {{ $isSw ? 'Anza mazungumzo' : 'Start a conversation' }}
                </a>
                <button type="button" @click="openFeedback(); menuOpen = false"
                        class="w-full flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 hover:bg-brand-muted text-left">
                    <span class="size-8 rounded-lg bg-brand-muted text-brand grid place-items-center shrink-0">✎</span>
                    {{ $isSw ? 'Tuma maoni' : 'Send feedback' }}
                </button>
            @endif
        </div>
    </div>

    @if ($variant === 'public')
        <div x-show="chatOpen" x-cloak x-transition
             class="absolute bottom-16 right-0 w-[min(100vw-2rem,24rem)] glass-card overflow-hidden flex flex-col max-h-[min(70vh,32rem)] shadow-xl">
            <div class="bg-brand text-white px-4 py-3 flex items-center gap-3">
                <div class="size-9 rounded-xl bg-white/15 grid place-items-center text-sm font-bold">AI</div>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-base">{{ __('site.support.assistant_title') }}</p>
                    <p class="text-sm text-white/75 truncate">{{ __('site.support.assistant_subtitle') }}</p>
                </div>
                <button type="button" @click="chatOpen = false" class="p-1.5 rounded-lg hover:bg-white/10" aria-label="Close">×</button>
            </div>
            <div class="flex-1 overflow-y-auto p-3.5 space-y-2.5 bg-gradient-to-b from-brand-muted/30 to-white min-h-[12rem]">
                <template x-for="(msg, i) in messages" :key="i">
                    <div :class="msg.role === 'user' ? 'text-right' : ''">
                        <span class="inline-block px-3.5 py-2 rounded-2xl text-[15px] leading-snug max-w-[min(16rem,82%)] text-left whitespace-pre-wrap"
                              :class="msg.role === 'user' ? 'bg-brand text-white' : 'bg-white ring-1 ring-gray-200 text-gray-800'"
                              x-text="msg.text"></span>
                    </div>
                </template>
            </div>
            <form @submit.prevent="ask" class="p-3 border-t border-gray-100 bg-white flex gap-2">
                <input type="text" x-model="input" :placeholder="@js(__('site.support.chat_placeholder'))"
                       class="flex-1 rounded-xl border-gray-200 text-base focus:border-brand focus:ring-brand/20">
                <button type="submit" class="bg-brand hover:bg-brand-light text-white text-sm font-semibold px-4 py-2.5 rounded-xl">
                    {{ __('site.support.chat_send') }}
                </button>
            </form>
            <div class="px-4 py-3 bg-gray-50 border-t border-gray-100 text-center">
                <a href="{{ route('site.support.chat') }}" class="text-sm font-semibold text-brand hover:underline">{{ __('site.support.escalate') }} →</a>
            </div>
        </div>
    @endif

    <button type="button" @click="toggle()"
            class="size-14 rounded-full bg-brand text-white shadow-lg hover:bg-brand-light transition grid place-items-center ring-4 ring-white/80"
            :aria-expanded="menuOpen || chatOpen" title="Kopafasta Support">
        <span class="text-xl font-bold" x-text="(menuOpen || chatOpen) ? '×' : '?'"></span>
    </button>
</div>

@once
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('kfHelpFab', (cfg) => ({
            menuOpen: false,
            chatOpen: false,
            input: '',
            messages: cfg.greeting ? [{ role: 'bot', text: cfg.greeting }] : [],
            suggestions: cfg.suggestions || [],
            rules: cfg.rules || [],
            defaultReply: cfg.default || '',
            toggle() {
                if (this.chatOpen) { this.chatOpen = false; this.menuOpen = false; return; }
                this.menuOpen = !this.menuOpen;
            },
            closeAll() { this.menuOpen = false; this.chatOpen = false; },
            openChat() { this.menuOpen = false; this.chatOpen = true; },
            openFeedback() {
                this.menuOpen = false;
                window.dispatchEvent(new CustomEvent('open-feedback'));
            },
            ask() {
                var q = (this.input || '').trim();
                if (!q) return;
                this.messages.push({ role: 'user', text: q });
                this.input = '';
                var reply = this.defaultReply;
                var lower = q.toLowerCase();
                (this.rules || []).some((rule) => {
                    var keys = rule.keywords || [];
                    if (keys.some((k) => lower.indexOf(String(k).toLowerCase()) !== -1)) {
                        reply = rule.answer || reply;
                        return true;
                    }
                    return false;
                });
                this.messages.push({ role: 'bot', text: reply });
            },
        }));
    });
</script>
@endonce
