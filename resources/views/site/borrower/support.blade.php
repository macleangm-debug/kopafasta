<x-site.borrower-layout :title="brand_title(__('borrower.support_page.title'))" active="support" content-width="wide">

    <x-site.borrower-page-header
        :eyebrow="__('borrower.nav.support')"
        :title="__('borrower.support_page.title')"
        :subtitle="__('borrower.support_page.subtitle')"
    />

    @if ($customer->nida_locked_until && now()->lt($customer->nida_locked_until))
        <div id="identity-appeal" class="mb-8 rounded-2xl border border-amber-300 bg-gradient-to-r from-amber-50 to-white p-6">
            <p class="text-xs uppercase tracking-widest text-amber-700 font-semibold">{{ __('borrower.nida.title') }}</p>
            <h2 class="text-lg font-bold text-amber-950 mt-1">{{ __('borrower.support_page.identity_appeal_title') }}</h2>
            <p class="text-sm text-amber-900 mt-2">{{ __('borrower.nida.verification_locked_appeal') }}</p>
            <p class="text-sm text-amber-900 mt-2">
                {{ __('borrower.nida.account_locked_until', ['time' => $customer->nida_locked_until->format('d M Y H:i')]) }}
            </p>
            <p class="text-sm text-amber-900 mt-3">{{ __('borrower.support_page.identity_appeal_contact_hint') }}</p>
            <div class="mt-4 flex flex-wrap gap-3">
                <a href="tel:{{ preg_replace('/\s+/', '', config('branding.support_phone')) }}"
                   class="inline-flex bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-5 py-2.5 rounded-xl text-sm">
                    {{ __('borrower.support_page.call', ['phone' => config('branding.support_phone')]) }}
                </a>
                <a href="mailto:{{ config('branding.support_email') }}?subject=Identity%20verification%20appeal"
                   class="inline-flex bg-white ring-1 ring-amber-200 hover:bg-amber-100 text-amber-950 font-semibold px-5 py-2.5 rounded-xl text-sm">
                    {{ __('borrower.support_page.email', ['email' => config('branding.support_email')]) }}
                </a>
            </div>
        </div>
    @else
        <div id="identity-appeal" class="mb-8 glass-card p-6">
            <h2 class="font-semibold">{{ __('borrower.support_page.identity_help_title') }}</h2>
            <p class="text-sm text-gray-600 mt-2">{{ __('borrower.support_page.identity_help_body') }}</p>
            <a href="mailto:{{ config('branding.support_email') }}?subject=Identity%20verification%20help"
               class="inline-flex mt-4 text-sm font-semibold text-brand hover:underline">
                {{ config('branding.support_email') }}
            </a>
        </div>
    @endif

    {{-- AI Assistant --}}
    <div class="mb-8 glass-card p-5" x-data="supportChat()">
        <div class="flex items-center gap-3 mb-4">
            <div class="size-10 rounded-xl bg-brand text-white grid place-items-center font-bold text-sm">AI</div>
            <div>
                <p class="font-semibold">{{ __('borrower.support_page.assistant_title') }}</p>
                <p class="text-xs text-gray-500">{{ __('borrower.support_page.assistant_subtitle') }}</p>
            </div>
        </div>
        <div class="rounded-xl bg-brand-muted/30 border border-brand/10 p-4 max-h-48 overflow-y-auto space-y-3 text-sm mb-3">
            <template x-for="(msg, i) in messages" :key="i">
                <div :class="msg.role === 'user' ? 'text-right' : ''">
                    <span class="inline-block px-3 py-2 rounded-xl max-w-[90%]"
                          :class="msg.role === 'user' ? 'bg-brand-gold/40 text-gray-900' : 'bg-white ring-1 ring-gray-200/80 text-gray-700'"
                          x-text="msg.text"></span>
                </div>
            </template>
        </div>
        <form @submit.prevent="ask" class="flex gap-2">
            <input type="text" x-model="input" placeholder="{{ __('borrower.support_page.chat_placeholder') }}"
                   class="flex-1 rounded-xl border-gray-300 text-sm focus:border-brand focus:ring-brand">
            <button type="submit" class="bg-brand hover:bg-brand-light text-white text-sm font-semibold px-4 py-2 rounded-xl">{{ __('borrower.support_page.chat_send') }}</button>
        </form>
    </div>

    {{-- FAQ --}}
    <div class="mb-8">
        <h2 class="font-semibold mb-3">{{ __('borrower.support_page.faq_title') }}</h2>
        <div class="space-y-2" x-data="{ open: null }">
            @foreach (__('borrower.support_page.faq') as $i => $faq)
                <div class="glass-card overflow-hidden">
                    <button type="button" @click="open = open === {{ $i }} ? null : {{ $i }}"
                            class="w-full text-left px-4 py-3 font-medium text-sm flex justify-between items-center hover:bg-brand-muted/20 transition">
                        {{ $faq['q'] }}
                        <span class="text-brand font-bold" x-text="open === {{ $i }} ? '−' : '+'"></span>
                    </button>
                    <div x-show="open === {{ $i }}" x-cloak class="px-4 pb-3 text-sm text-gray-600 border-t border-gray-100/80 pt-3">{{ $faq['a'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <a href="tel:+255700000000" class="glass-card p-6 hover:ring-2 hover:ring-brand/20 transition group">
            <p class="font-semibold group-hover:text-brand transition">{{ __('borrower.support_page.call_title') }}</p>
            <p class="text-sm text-gray-500">{{ __('borrower.support_page.call_hours', ['phone' => '+255 700 000 000']) }}</p>
        </a>
        <a href="https://wa.me/255700000000" class="glass-card p-6 hover:ring-2 hover:ring-brand/20 transition group">
            <p class="font-semibold group-hover:text-brand transition">{{ __('borrower.support_page.whatsapp_title') }}</p>
            <p class="text-sm text-gray-500">{{ __('borrower.support_page.whatsapp_hint') }}</p>
        </a>
    </div>

    <script>
        function supportChat() {
            const answers = @js(__('borrower.support_page.chat'));
            return {
                input: '',
                messages: [{ role: 'bot', text: answers.greeting }],
                ask() {
                    const q = this.input.trim();
                    if (!q) return;
                    this.messages.push({ role: 'user', text: q });
                    this.input = '';
                    const lower = q.toLowerCase();
                    let reply = answers.default;
                    if (lower.includes('member')) reply = answers.membership;
                    else if (lower.includes('loan') || lower.includes('apply')) reply = answers.loan;
                    else if (lower.includes('guarantor')) reply = answers.guarantor;
                    else if (lower.includes('repay') || lower.includes('payment')) reply = answers.repayment;
                    else if (lower.includes('penalt')) reply = answers.penalty;
                    this.messages.push({ role: 'bot', text: reply });
                },
            };
        }
    </script>
</x-site.borrower-layout>
