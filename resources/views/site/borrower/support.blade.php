<x-site.borrower-layout :title="brand_title(__('borrower.support_page.title'))" active="support" content-width="wide">

    @php
        $phones = support_phones();
        $emails = support_emails();
        $whatsapp = \App\Support\PhoneNumber::digits(support_contact('whatsapp'));
        if ($whatsapp === '' && $phones !== []) {
            $whatsapp = \App\Support\PhoneNumber::digits($phones[0]);
        }
        $primaryPhone = $phones[0] ?? null;
        $nidaLocked = $customer->nida_locked_until && now()->lt($customer->nida_locked_until);
    @endphp

    @if ($nidaLocked)
        <div id="identity-appeal" class="mb-6 rounded-2xl border border-amber-300 bg-gradient-to-r from-amber-50 to-white p-6">
            <p class="text-xs uppercase tracking-widest text-amber-700 font-semibold">{{ __('borrower.nida.title') }}</p>
            <h2 class="text-lg font-bold text-amber-950 mt-1">{{ __('borrower.support_page.identity_appeal_title') }}</h2>
            <p class="text-sm text-amber-900 mt-2">{{ __('borrower.nida.verification_locked_appeal') }}</p>
            <p class="text-sm text-amber-900 mt-2">
                {{ __('borrower.nida.account_locked_until', ['time' => $customer->nida_locked_until->format('d M Y H:i')]) }}
            </p>
            <p class="text-sm text-amber-900 mt-3">{{ __('borrower.support_page.identity_appeal_contact_hint') }}</p>
            <div class="mt-4 flex flex-wrap gap-3">
                @foreach ($phones as $phone)
                    <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}"
                       class="inline-flex bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-5 py-2.5 rounded-xl text-sm">
                        {{ __('borrower.support_page.call', ['phone' => $phone]) }}
                    </a>
                @endforeach
                @foreach ($emails as $email)
                    <a href="mailto:{{ $email }}?subject=Identity%20verification%20appeal"
                       class="inline-flex bg-white ring-1 ring-amber-200 hover:bg-amber-100 text-amber-950 font-semibold px-5 py-2.5 rounded-xl text-sm">
                        {{ __('borrower.support_page.email', ['email' => $email]) }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <section class="relative overflow-hidden rounded-3xl kf-premium-panel mb-6">
        <div class="absolute inset-0 opacity-[0.14]" style="background-image: radial-gradient(circle at 18% 20%, #fff 0, transparent 42%), radial-gradient(circle at 88% 0%, #fbbf24 0, transparent 38%);"></div>
        <div class="relative px-5 sm:px-8 py-7 sm:py-9">
            <p class="text-[10px] uppercase tracking-[0.22em] font-semibold text-white/70">{{ __('borrower.support_page.hero_kicker') }}</p>
            <h1 class="mt-2 text-2xl sm:text-3xl font-bold tracking-tight">{{ __('borrower.support_page.title') }}</h1>
            <p class="mt-2 text-sm text-white/80 max-w-xl">{{ __('borrower.support_page.hero_title') }}. {{ __('borrower.support_page.hero_body') }}</p>

            <div class="mt-6 grid sm:grid-cols-3 gap-3">
                @if ($primaryPhone)
                    <a href="tel:{{ preg_replace('/\s+/', '', $primaryPhone) }}"
                       class="rounded-2xl bg-white/12 ring-1 ring-white/20 px-4 py-4 hover:bg-white/18 transition">
                        <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">1</p>
                        <p class="mt-1 font-bold">{{ __('borrower.support_page.call_title') }}</p>
                        <p class="mt-1 text-sm text-white/75">{{ __('borrower.support_page.call_hours', ['phone' => $primaryPhone]) }}</p>
                    </a>
                @endif
                @if ($whatsapp !== '')
                    <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener"
                       class="rounded-2xl bg-white/12 ring-1 ring-white/20 px-4 py-4 hover:bg-white/18 transition">
                        <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">2</p>
                        <p class="mt-1 font-bold">{{ __('borrower.support_page.whatsapp_title') }}</p>
                        <p class="mt-1 text-sm text-white/75">{{ __('borrower.support_page.whatsapp_hint') }}</p>
                    </a>
                @endif
                <a href="#support-chat"
                   class="rounded-2xl bg-brand-gold text-brand px-4 py-4 hover:brightness-95 transition {{ $primaryPhone || $whatsapp !== '' ? '' : 'sm:col-span-3' }}">
                    <p class="text-[10px] uppercase tracking-widest font-semibold opacity-70">{{ $primaryPhone || $whatsapp !== '' ? '3' : '1' }}</p>
                    <p class="mt-1 font-bold">{{ __('borrower.support_page.chat_cta') }}</p>
                    <p class="mt-1 text-sm opacity-80">{{ __('borrower.support_page.chat_cta_hint') }}</p>
                </a>
            </div>
        </div>
    </section>

    <div id="support-chat" class="scroll-mt-24">
        <x-site.ai-support-chat
            class="mb-8"
            :member-mode="true"
            :agent-label="__('borrower.support_page.assistant_title')"
            :agent-subtitle="__('borrower.support_page.assistant_subtitle')"
        />
    </div>

    <div class="mb-8">
        <h2 class="font-semibold text-lg">{{ __('borrower.support_page.faq_title') }}</h2>
        <p class="text-sm text-gray-500 mt-1 mb-4">{{ __('borrower.support_page.faq_intro') }}</p>
        <div class="space-y-2" x-data="{ open: 0 }">
            @foreach (__('borrower.support_page.faq') as $i => $faq)
                <div class="glass-card overflow-hidden">
                    <button type="button" @click="open = open === {{ $i }} ? null : {{ $i }}"
                            class="w-full text-left px-4 py-3.5 font-medium text-sm flex justify-between items-center gap-3 hover:bg-brand-muted/20 transition">
                        <span>{{ $faq['q'] }}</span>
                        <span class="text-brand font-bold shrink-0" x-text="open === {{ $i }} ? '−' : '+'"></span>
                    </button>
                    <div x-show="open === {{ $i }}" x-cloak class="px-4 pb-4 text-sm text-gray-600 border-t border-gray-100/80 pt-3 leading-relaxed">{{ $faq['a'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    @unless ($nidaLocked)
        <details id="identity-appeal" class="mb-8 glass-card p-5 sm:p-6 group">
            <summary class="font-semibold cursor-pointer list-none flex items-center justify-between gap-3 [&::-webkit-details-marker]:hidden">
                <span>{{ __('borrower.support_page.identity_help_title') }}</span>
                <span class="text-brand text-sm font-bold group-open:hidden">+</span>
                <span class="text-brand text-sm font-bold hidden group-open:inline">−</span>
            </summary>
            <p class="text-sm text-gray-600 mt-3 leading-relaxed">{{ __('borrower.support_page.identity_help_body') }}</p>
            <div class="mt-4 flex flex-wrap gap-3">
                @foreach ($emails as $email)
                    <a href="mailto:{{ $email }}?subject=Identity%20verification%20help"
                       class="inline-flex text-sm font-semibold text-brand hover:underline">
                        {{ $email }}
                    </a>
                @endforeach
            </div>
        </details>
    @endunless

</x-site.borrower-layout>
