<x-site.borrower-layout :title="brand_title(__('borrower.support_page.title'))" active="support" content-width="wide">

    @php
        $phones = support_phones();
        $whatsapp = \App\Support\PhoneNumber::digits(support_contact('whatsapp'));
        if ($whatsapp === '' && $phones !== []) {
            $whatsapp = \App\Support\PhoneNumber::digits($phones[0]);
        }
        $primaryPhone = $phones[0] ?? null;
        $nidaLocked = $customer->nida_locked_until && now()->lt($customer->nida_locked_until);
        $openHumanChat = $openHumanChat ?? false;
        $supportHistory = $supportHistory ?? collect();
        $helpGroups = $helpGroups ?? [];
        $helpResults = $helpResults ?? [];
        $helpQuery = $helpQuery ?? '';
        $isSw = str_starts_with(app()->getLocale(), 'sw');
    @endphp

    <div x-data="{ human: @js((bool) $openHumanChat), q: @js($helpQuery) }">
        @if ($nidaLocked)
            <div id="identity-appeal" class="mb-6 rounded-2xl border border-amber-300 bg-gradient-to-r from-amber-50 to-white p-6">
                <p class="text-xs uppercase tracking-widest text-amber-700 font-semibold">{{ __('borrower.nida.title') }}</p>
                <h2 class="text-lg font-bold text-amber-950 mt-1">{{ __('borrower.support_page.identity_appeal_title') }}</h2>
                <p class="text-sm text-amber-900 mt-2">{{ __('borrower.nida.verification_locked_appeal') }}</p>
            </div>
        @endif

        {{-- SUPPORT HOME --}}
        <div x-show="!human" x-cloak class="space-y-6">
            <section class="relative overflow-hidden rounded-3xl kf-premium-panel">
                <div class="absolute inset-0 opacity-[0.14]" style="background-image: radial-gradient(circle at 18% 20%, #fff 0, transparent 42%), radial-gradient(circle at 88% 0%, #fbbf24 0, transparent 38%);"></div>
                <div class="relative px-5 sm:px-8 py-7 sm:py-9">
                    <p class="text-[10px] uppercase tracking-[0.22em] font-semibold text-white/70">{{ $isSw ? 'Kituo cha Usaidizi' : 'Help Center' }}</p>
                    <h1 class="mt-2 text-2xl sm:text-3xl font-bold tracking-tight">{{ $isSw ? 'Unahitaji msaada gani?' : 'How can we help?' }}</h1>
                    <form method="GET" action="{{ route('site.borrower.support') }}" class="mt-5 max-w-xl">
                        <input type="search" name="q" value="{{ $helpQuery }}"
                               placeholder="{{ $isSw ? 'Tafuta FAQ na HOW TO…' : 'Search FAQs and HOW TO…' }}"
                               class="w-full rounded-2xl border-0 bg-white/95 text-gray-900 text-sm px-4 py-3 shadow-sm focus:ring-2 focus:ring-brand-gold/50">
                    </form>
                </div>
            </section>

            @if ($supportConversation)
                <a href="{{ route('site.borrower.support', ['chat' => 1]) }}"
                   class="block rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm px-4 py-4 hover:bg-brand-muted/20 transition">
                    <p class="text-[11px] uppercase tracking-widest text-brand font-semibold">{{ $isSw ? 'Mazungumzo yanayoendelea' : 'Continue conversation' }}</p>
                    <p class="text-sm font-bold text-gray-900 mt-1">Kopafasta Support · #{{ $supportConversation->id }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $isSw ? 'Gusa kuendelea — hautafunguliwa kiotomatiki.' : 'Tap to continue — Support Home never opens chat automatically.' }}</p>
                </a>
            @endif

            @if ($helpQuery !== '')
                <section class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 space-y-3">
                    <p class="text-sm font-semibold text-gray-900">{{ $isSw ? 'Matokeo' : 'Results' }} ({{ count($helpResults) }})</p>
                    @forelse ($helpResults as $row)
                        <div class="rounded-xl bg-slate-50 px-4 py-3">
                            <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">{{ strtoupper($row['type']) }} · {{ $row['group'] }}</p>
                            <p class="text-sm font-bold text-gray-900 mt-1">{{ $row['title'] }}</p>
                            <p class="text-sm text-gray-600 mt-1">{{ $row['body'] }}</p>
                            @if (! empty($row['steps']))
                                <ol class="mt-2 list-decimal ml-5 text-sm text-gray-700 space-y-1">
                                    @foreach ($row['steps'] as $step)
                                        <li>{{ $step }}</li>
                                    @endforeach
                                </ol>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">{{ $isSw ? 'Hakuna matokeo. Jaribu maneno mengine au Ongea na Timu.' : 'No matches. Try different words or Talk to Support.' }}</p>
                    @endforelse
                </section>
            @else
                <section class="space-y-4">
                    <h2 class="font-semibold text-lg">{{ $isSw ? 'FAQ na HOW TO' : 'FAQs & HOW TO' }}</h2>
                    @foreach ($helpGroups as $group)
                        @php
                            $gLabel = $isSw ? ($group['label_sw'] ?? $group['label_en']) : ($group['label_en'] ?? $group['label_sw']);
                        @endphp
                        <details class="glass-card rounded-2xl overflow-hidden group">
                            <summary class="px-4 py-3.5 font-semibold text-sm cursor-pointer list-none flex justify-between gap-3">
                                <span>{{ $gLabel }}</span>
                                <span class="text-brand">+</span>
                            </summary>
                            <div class="px-4 pb-4 space-y-3 border-t border-gray-100/80 pt-3">
                                @foreach ($group['faqs'] ?? [] as $faq)
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">{{ $isSw ? ($faq['q_sw'] ?? $faq['q_en']) : ($faq['q_en'] ?? $faq['q_sw']) }}</p>
                                        <p class="text-sm text-gray-600 mt-1">{{ $isSw ? ($faq['a_sw'] ?? $faq['a_en']) : ($faq['a_en'] ?? $faq['a_sw']) }}</p>
                                    </div>
                                @endforeach
                                @foreach ($group['howtos'] ?? [] as $how)
                                    <div class="rounded-xl bg-brand-muted/30 px-3 py-3">
                                        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">HOW TO</p>
                                        <p class="text-sm font-bold text-gray-900 mt-1">{{ $isSw ? ($how['title_sw'] ?? $how['title_en']) : ($how['title_en'] ?? $how['title_sw']) }}</p>
                                        <p class="text-sm text-gray-600 mt-1">{{ $isSw ? ($how['intro_sw'] ?? $how['intro_en']) : ($how['intro_en'] ?? $how['intro_sw']) }}</p>
                                        <ol class="mt-2 list-decimal ml-5 text-sm text-gray-700 space-y-1">
                                            @foreach (($isSw ? ($how['steps_sw'] ?? []) : ($how['steps_en'] ?? [])) as $step)
                                                <li>{{ $step }}</li>
                                            @endforeach
                                        </ol>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endforeach
                </section>
            @endif

            @if ($supportHistory->isNotEmpty())
                <section class="rounded-2xl bg-white ring-1 ring-brand/10 p-5">
                    <h2 class="font-semibold text-sm text-gray-900">{{ $isSw ? 'Historia ya usaidizi' : 'Support history' }}</h2>
                    <ul class="mt-3 divide-y divide-gray-100">
                        @foreach ($supportHistory as $h)
                            <li class="py-2.5 flex items-center justify-between gap-3 text-sm">
                                <div>
                                    <p class="font-semibold text-gray-800">{{ $h->topic ?: ('#'.$h->id) }}</p>
                                    <p class="text-xs text-gray-500">{{ format_app_datetime($h->last_message_at ?? $h->created_at, 'd M Y · H:i') }}
                                        · {{ ucfirst($h->status) }}
                                        @if ($h->rating) · ★ {{ $h->rating }} @endif
                                    </p>
                                </div>
                                <a href="{{ route('site.borrower.support.history', $h) }}" class="text-xs font-semibold text-brand hover:underline">{{ $isSw ? 'Fungua' : 'Open' }}</a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <div class="grid sm:grid-cols-2 gap-3">
                @if ($supportConversation)
                    <a href="{{ route('site.borrower.support', ['chat' => 1]) }}"
                       class="rounded-2xl bg-brand-gold text-brand font-bold text-sm px-5 py-4 text-center hover:brightness-95">
                        {{ $isSw ? 'Endelea mazungumzo' : 'Continue conversation' }}
                    </a>
                @else
                    <a href="{{ route('site.borrower.support', ['chat' => 1]) }}"
                       class="rounded-2xl bg-brand-gold text-brand font-bold text-sm px-5 py-4 text-center hover:brightness-95">
                        {{ $isSw ? 'Ongea na Timu ya Usaidizi' : 'Talk to Support' }}
                    </a>
                @endif
                <button type="button"
                        @click="$dispatch('open-feedback')"
                        class="rounded-2xl ring-1 ring-brand/20 text-brand font-bold text-sm px-5 py-4 text-center hover:bg-brand-muted/30">
                    {{ $isSw ? 'Tuma maoni' : 'Send feedback' }}
                </button>
            </div>

            @if ($primaryPhone || $whatsapp !== '')
                <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 text-sm space-y-2">
                    <p class="text-[11px] uppercase tracking-widest text-brand font-semibold">{{ $isSw ? 'Piga simu Kopafasta' : 'Call Kopafasta' }}</p>
                    @if ($primaryPhone)
                        <a href="tel:{{ preg_replace('/\s+/', '', $primaryPhone) }}" class="block font-semibold text-gray-900 hover:text-brand">{{ $primaryPhone }}</a>
                    @endif
                    @if ($whatsapp !== '')
                        <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" class="block text-brand font-semibold hover:underline">WhatsApp</a>
                    @endif
                </div>
            @endif
        </div>

        {{-- LIVE CHAT (explicit entry only) --}}
        <div id="support-human-chat"
             x-show="human"
             x-cloak
             class="scroll-mt-24 mb-8 max-w-2xl"
             @support-back-to-faqs.window="human = false; window.location = @js(route('site.borrower.support'))">
            <div class="mb-3">
                <a href="{{ route('site.borrower.support') }}" class="text-sm font-semibold text-brand hover:underline">← {{ $isSw ? 'Rudi Kituo cha Usaidizi' : 'Back to Help Center' }}</a>
            </div>
            <x-site.ai-support-chat
                class="mb-4"
                :member-mode="true"
                :force-human="true"
                :conversation="$supportConversation"
                :existing-messages="$supportConversation?->messages"
                :rating-url="$supportConversation && in_array($supportConversation->status, ['resolved', 'closed'], true) && ! $supportConversation->rating
                    ? route('site.borrower.support.conversation.rate', $supportConversation)
                    : null"
                :show-rating="$supportConversation && in_array($supportConversation->status, ['resolved', 'closed'], true) && ! $supportConversation->rating"
            />
        </div>
    </div>

    <x-site.feedback-form-panel
        :show-trigger="false"
        :show-faq-link="false"
        :open-on-load="request()->boolean('feedback') || filled(session('status'))"
        from="borrower"
    />

</x-site.borrower-layout>
