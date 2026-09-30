<x-site.vendor-layout :title="__('site.partner_portal.nav_support')" active="support">
    @php
        $wa = preg_replace('/\D+/', '', (string) $supportWhatsapp) ?: '';
        $tel = preg_replace('/\s+/', '', (string) $supportPhone);
        $openHumanChat = $openHumanChat ?? false;
        $supportConversation = $supportConversation ?? null;
        $supportHistory = $supportHistory ?? collect();
        $openTickets = $openTickets ?? collect();
        $resolvedTickets = $resolvedTickets ?? collect();
        $helpGroups = $helpGroups ?? [];
        $helpResults = $helpResults ?? [];
        $helpQuery = $helpQuery ?? '';
        $helpSection = $helpSection ?? 'help';
        $chatUrl = $chatUrl ?? route('site.partner.support', ['chat' => 1]);
        $homeUrl = $supportPageUrl ?? route('site.partner.support');
        $feedbackUrl = $feedbackUrl ?? route('site.feedback', ['open' => 1, 'from' => 'partner']);
        $isSw = str_starts_with(app()->getLocale(), 'sw');
        $hasActive = (bool) $supportConversation || $openTickets->isNotEmpty();
        $chatConversation = $supportConversation;
        if (! $chatConversation && request()->boolean('chat') && auth()->user()) {
            $chatConversation = app(\App\Services\Support\SupportConversationService::class)
                ->memberFacingConversation(null, auth()->id());
        }
        $showRating = $chatConversation?->awaitsRating() ?? false;
        $rateUrl = null;
        if ($showRating && $chatConversation) {
            $rateUrl = request()->routeIs('site.vendor.*')
                ? route('site.vendor.support.conversation.rate', $chatConversation)
                : route('site.partner.support.conversation.rate', $chatConversation);
        }
    @endphp

    <div x-data="{ human: @js((bool) $openHumanChat), section: @js($helpSection) }">
        <div x-show="!human" x-cloak class="space-y-6">
            <section class="relative overflow-hidden rounded-3xl kf-premium-panel">
                <div class="relative px-5 sm:px-8 py-7 sm:py-9">
                    <p class="text-[10px] uppercase tracking-[0.22em] font-semibold text-white/70">{{ $isSw ? 'Kituo cha Usaidizi' : 'Help Center' }}</p>
                    <h1 class="mt-2 text-2xl sm:text-3xl font-bold tracking-tight text-white">{{ $isSw ? 'Unahitaji msaada gani?' : 'How can we help?' }}</h1>
                    <form method="GET" action="{{ $homeUrl }}" class="mt-5 max-w-xl">
                        <input type="hidden" name="section" value="help">
                        <input type="search" name="q" value="{{ $helpQuery }}"
                               placeholder="{{ $isSw ? 'Tafuta msaada wa Partner…' : 'Search Partner help…' }}"
                               class="w-full rounded-2xl border-0 bg-white/95 text-gray-900 text-sm px-4 py-3 shadow-sm">
                    </form>
                </div>
            </section>

            <nav class="flex flex-wrap gap-2" aria-label="Help Center sections">
                @foreach ([
                    'help' => $isSw ? 'Msaada' : 'Help',
                    'active' => $isSw ? 'Inayoendelea' : 'Active support',
                    'history' => $isSw ? 'Historia' : 'History',
                ] as $key => $label)
                    <button type="button"
                            @click="section = @js($key)"
                            class="rounded-full px-4 py-2 text-sm font-semibold ring-1 transition"
                            :class="section === @js($key) ? 'bg-brand text-white ring-brand' : 'bg-white text-brand ring-brand/20 hover:bg-brand-muted/40'">
                        {{ $label }}
                    </button>
                @endforeach
            </nav>

            <div x-show="section === 'help'" x-cloak class="space-y-4">
                @if ($helpQuery !== '')
                    <section class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 space-y-3">
                        @forelse ($helpResults as $row)
                            <div class="rounded-xl bg-slate-50 px-4 py-3">
                                <p class="text-[10px] uppercase tracking-widest text-slate-500 font-semibold">{{ strtoupper($row['type']) }} · {{ $row['group'] }}</p>
                                <p class="text-sm font-bold text-gray-900 mt-1">{{ $row['title'] }}</p>
                                <p class="text-sm text-gray-600 mt-1">{{ $row['body'] }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">{{ $isSw ? 'Hakuna matokeo.' : 'No matches.' }}</p>
                        @endforelse
                    </section>
                @else
                    <section class="space-y-3">
                        @foreach ($helpGroups as $group)
                            @php $gLabel = $isSw ? ($group['label_sw'] ?? $group['label_en']) : ($group['label_en'] ?? $group['label_sw']); @endphp
                            <details class="glass-card rounded-2xl overflow-hidden">
                                <summary class="px-4 py-3.5 font-semibold text-sm cursor-pointer">{{ $gLabel }}</summary>
                                <div class="px-4 pb-4 space-y-3 border-t border-gray-100 pt-3">
                                    @foreach ($group['faqs'] ?? [] as $faq)
                                        <div>
                                            <p class="text-sm font-semibold">{{ $isSw ? ($faq['q_sw'] ?? $faq['q_en']) : ($faq['q_en'] ?? $faq['q_sw']) }}</p>
                                            <p class="text-sm text-gray-600 mt-1">{{ $isSw ? ($faq['a_sw'] ?? $faq['a_en']) : ($faq['a_en'] ?? $faq['a_sw']) }}</p>
                                        </div>
                                    @endforeach
                                    @foreach ($group['howtos'] ?? [] as $how)
                                        <div class="rounded-xl bg-brand-muted/30 px-3 py-3">
                                            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">HOW TO</p>
                                            <p class="text-sm font-bold mt-1">{{ $isSw ? ($how['title_sw'] ?? $how['title_en']) : ($how['title_en'] ?? $how['title_sw']) }}</p>
                                            <ol class="mt-2 list-decimal ml-5 text-sm space-y-1">
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

                <div class="grid sm:grid-cols-2 gap-3">
                    @if ($supportConversation)
                        <a href="{{ $chatUrl }}" class="rounded-2xl bg-brand-gold text-brand font-bold text-sm px-5 py-4 text-center">{{ $isSw ? 'Endelea mazungumzo' : 'Continue conversation' }}</a>
                    @else
                        <a href="{{ $chatUrl }}" class="rounded-2xl bg-brand-gold text-brand font-bold text-sm px-5 py-4 text-center">{{ $isSw ? 'Ongea na Timu ya Usaidizi' : 'Talk to Support' }}</a>
                    @endif
                    <button type="button" @click="$dispatch('open-feedback')" class="rounded-2xl ring-1 ring-brand/20 text-brand font-bold text-sm px-5 py-4 text-center">{{ $isSw ? 'Tuma maoni' : 'Send feedback' }}</button>
                </div>

                <div class="grid sm:grid-cols-3 gap-3 text-sm">
                    @if ($tel)
                        <a href="tel:{{ $tel }}" class="glass-card rounded-2xl p-4">{{ __('site.partner_portal.support_call') }} · {{ $supportPhone }}</a>
                    @endif
                    @if ($wa !== '')
                        <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener" class="glass-card rounded-2xl p-4">{{ __('site.partner_portal.support_whatsapp') }}</a>
                    @endif
                    <a href="mailto:{{ $supportEmail }}" class="glass-card rounded-2xl p-4">{{ $supportEmail }}</a>
                </div>
            </div>

            <div x-show="section === 'active'" x-cloak class="space-y-4">
                @if ($supportConversation)
                    <a href="{{ $chatUrl }}" class="block rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm px-4 py-4 hover:bg-brand-muted/20">
                        <p class="text-[11px] uppercase tracking-widest text-brand font-semibold">{{ $isSw ? 'Endelea mazungumzo' : 'Continue conversation' }}</p>
                        <p class="text-sm font-bold text-gray-900 mt-1">Kopafasta Support · #{{ $supportConversation->id }}</p>
                    </a>
                @endif
                @if ($openTickets->isNotEmpty())
                    <section class="rounded-2xl bg-white ring-1 ring-brand/10 p-5">
                        <h2 class="font-semibold text-sm">{{ $isSw ? 'Tiketi zilizo wazi' : 'Open tickets' }}</h2>
                        <ul class="mt-3 divide-y divide-gray-100">
                            @foreach ($openTickets as $t)
                                <li class="py-2.5 text-sm">
                                    <p class="font-semibold">{{ $t->ticket_number }} · {{ $t->subject }}</p>
                                    <p class="text-xs text-gray-500">{{ ucfirst($t->status) }}</p>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
                @if (! $hasActive)
                    <div class="rounded-2xl bg-slate-50 ring-1 ring-slate-200 p-6 text-center space-y-3">
                        <p class="text-sm text-gray-600">{{ $isSw ? 'Hakuna usaidizi unaoendelea.' : 'No active support.' }}</p>
                        <a href="{{ $chatUrl }}" class="inline-flex rounded-2xl bg-brand-gold text-brand font-bold text-sm px-5 py-3">{{ $isSw ? 'Ongea na Timu' : 'Talk to Support' }}</a>
                    </div>
                @endif
            </div>

            <div x-show="section === 'history'" x-cloak class="space-y-4">
                @if ($supportHistory->isNotEmpty())
                    <section class="rounded-2xl bg-white ring-1 ring-brand/10 p-5">
                        <h2 class="font-semibold text-sm">{{ $isSw ? 'Historia' : 'History' }}</h2>
                        <ul class="mt-3 divide-y divide-gray-100">
                            @foreach ($supportHistory as $h)
                                <li class="py-2.5 flex items-center justify-between gap-3 text-sm">
                                    <div>
                                        <p class="font-semibold">{{ $h->topic ?: ('#'.$h->id) }}</p>
                                        <p class="text-xs text-gray-500">{{ format_app_datetime($h->last_message_at ?? $h->created_at, 'd M Y · H:i') }}
                                            @if ($h->rating) · ★ {{ $h->rating }} @endif
                                        </p>
                                    </div>
                                    @if ($h->awaitsRating())
                                        <a href="{{ $chatUrl.(str_contains($chatUrl, '?') ? '&' : '?').'section=history' }}" class="text-xs font-bold text-amber-700 hover:underline">{{ $isSw ? 'Tathmini ★' : 'Rate ★' }}</a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
                @if ($resolvedTickets->isNotEmpty())
                    <section class="rounded-2xl bg-white ring-1 ring-brand/10 p-5">
                        <h2 class="font-semibold text-sm">{{ $isSw ? 'Tiketi zilizofungwa' : 'Resolved tickets' }}</h2>
                        <ul class="mt-3 divide-y divide-gray-100">
                            @foreach ($resolvedTickets as $t)
                                <li class="py-2.5 text-sm">
                                    <p class="font-semibold">{{ $t->ticket_number }} · {{ $t->subject }}</p>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
                @if ($supportHistory->isEmpty() && $resolvedTickets->isEmpty())
                    <p class="text-sm text-gray-500 text-center py-8">{{ $isSw ? 'Hakuna historia bado.' : 'No history yet.' }}</p>
                @endif
                <a href="{{ $chatUrl }}" class="block rounded-2xl bg-brand-gold text-brand font-bold text-sm px-5 py-4 text-center">
                    {{ $isSw ? 'Ongea tena (mazungumzo mapya)' : 'Talk again (new conversation)' }}
                </a>
            </div>
        </div>

        <div x-show="human" x-cloak class="max-w-2xl">
            <div class="mb-3">
                <a href="{{ $homeUrl }}" class="text-sm font-semibold text-brand hover:underline">← {{ $isSw ? 'Rudi Kituo cha Usaidizi' : 'Back to Help Center' }}</a>
            </div>
            <x-site.ai-support-chat
                class="mb-4"
                :member-mode="true"
                :force-human="true"
                :speak-url="$speakUrl"
                :thread-url="$threadUrl"
                :conversation="$chatConversation"
                :existing-messages="$chatConversation?->messages"
                :rating-url="$rateUrl"
                :show-rating="$showRating"
            />
        </div>
    </div>

    <x-site.feedback-form-panel
        :show-trigger="false"
        :show-faq-link="false"
        :open-on-load="request()->boolean('feedback') || filled(session('status'))"
        from="partner"
    />
</x-site.vendor-layout>
