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
        $openHumanChat = $openHumanChat ?? false;
    @endphp

    <div x-data="{ human: @js((bool) $openHumanChat) }">
        @if ($nidaLocked)
            <div id="identity-appeal" class="mb-6 rounded-2xl border border-amber-300 bg-gradient-to-r from-amber-50 to-white p-6">
                <p class="text-xs uppercase tracking-widest text-amber-700 font-semibold">{{ __('borrower.nida.title') }}</p>
                <h2 class="text-lg font-bold text-amber-950 mt-1">{{ __('borrower.support_page.identity_appeal_title') }}</h2>
                <p class="text-sm text-amber-900 mt-2">{{ __('borrower.nida.verification_locked_appeal') }}</p>
                <p class="text-sm text-amber-900 mt-2">
                    {{ __('borrower.nida.account_locked_until', ['time' => $customer->nida_locked_until->format('d M Y H:i')]) }}
                </p>
            </div>
        @endif

        {{-- Self-service first --}}
        <div x-show="!human" x-cloak>
            <section class="relative overflow-hidden rounded-3xl kf-premium-panel mb-6">
                <div class="absolute inset-0 opacity-[0.14]" style="background-image: radial-gradient(circle at 18% 20%, #fff 0, transparent 42%), radial-gradient(circle at 88% 0%, #fbbf24 0, transparent 38%);"></div>
                <div class="relative px-5 sm:px-8 py-7 sm:py-9">
                    <p class="text-[10px] uppercase tracking-[0.22em] font-semibold text-white/70">{{ __('borrower.support_page.hero_kicker') }}</p>
                    <h1 class="mt-2 text-2xl sm:text-3xl font-bold tracking-tight">{{ __('borrower.support_page.title') }}</h1>
                    <p class="mt-2 text-sm text-white/80 max-w-xl">{{ __('borrower.support_page.hero_body') }}</p>

                    <div class="mt-6 grid sm:grid-cols-2 gap-3">
                        @if ($primaryPhone)
                            <a href="tel:{{ preg_replace('/\s+/', '', $primaryPhone) }}"
                               class="rounded-2xl bg-white/12 ring-1 ring-white/20 px-4 py-4 hover:bg-white/18 transition">
                                <p class="mt-1 font-bold">{{ __('borrower.support_page.call_title') }}</p>
                                <p class="mt-1 text-sm text-white/75">{{ __('borrower.support_page.call_hours', ['phone' => $primaryPhone]) }}</p>
                            </a>
                        @endif
                        @if ($whatsapp !== '')
                            <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener"
                               class="rounded-2xl bg-white/12 ring-1 ring-white/20 px-4 py-4 hover:bg-white/18 transition">
                                <p class="mt-1 font-bold">{{ __('borrower.support_page.whatsapp_title') }}</p>
                                <p class="mt-1 text-sm text-white/75">{{ __('borrower.support_page.whatsapp_hint') }}</p>
                            </a>
                        @endif
                    </div>
                </div>
            </section>

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

            <div class="mb-8 glass-card p-5 sm:p-6">
                <p class="text-sm font-semibold text-gray-900">{{ __('borrower.support_page.still_need_help') }}</p>
                <p class="text-sm text-gray-500 mt-1">{{ __('borrower.support_page.still_need_help_hint') }}</p>
                <button type="button" @click="human = true; $nextTick(() => document.getElementById('support-human-chat')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
                        class="mt-4 inline-flex rounded-xl bg-brand-gold text-brand font-bold text-sm px-5 py-2.5 hover:brightness-95">
                    {{ __('borrower.support_page.talk_to_team') }}
                </button>
            </div>
        </div>

        {{-- Human conversation only --}}
        <div id="support-human-chat" x-show="human" x-cloak class="scroll-mt-24 mb-8 max-w-2xl">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-[11px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.support_page.human_mode_label') }}</p>
                    <h2 class="text-xl font-bold text-gray-900">{{ __('borrower.support_page.talk_to_team') }}</h2>
                </div>
                @unless ($supportConversation && $supportConversation->messages->isNotEmpty())
                    <button type="button" @click="human = false" class="text-sm font-semibold text-brand hover:underline">
                        {{ __('borrower.support_page.back_to_faqs') }}
                    </button>
                @endunless
            </div>

            <x-site.ai-support-chat
                class="mb-4"
                :member-mode="true"
                :force-human="true"
                :agent-label="__('borrower.support_page.human_mode_label')"
                :agent-subtitle="__('borrower.support_page.speak_to_support_hint')"
                :existing-messages="$supportConversation?->messages"
            />
        </div>
    </div>

</x-site.borrower-layout>
