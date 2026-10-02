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
        $openTickets = $openTickets ?? collect();
        $resolvedTickets = $resolvedTickets ?? collect();
        $helpGroups = $helpGroups ?? [];
        $helpResults = $helpResults ?? [];
        $helpQuery = $helpQuery ?? '';
        $helpSection = $helpSection ?? 'help';
        $isSw = str_starts_with(app()->getLocale(), 'sw');
        $hasActive = (bool) ($supportConversation ?? null)
            || (($openSupportConversations ?? collect())->isNotEmpty());
        $rateUrl = $supportConversation && $supportConversation->awaitsRating()
            ? route('site.borrower.support.conversation.rate', $supportConversation)
            : null;
        // Closed thread awaiting rating still shows chat ★ card; Talk opens a NEW conversation after closure.
        $chatConversation = $supportConversation;
        if (! $chatConversation && request()->boolean('chat') && ($openSupportConversations ?? collect())->count() <= 1) {
            $chatConversation = app(\App\Services\Support\SupportConversationService::class)
                ->memberFacingConversation($customer->id);
            $rateUrl = $chatConversation && $chatConversation->awaitsRating()
                ? route('site.borrower.support.conversation.rate', $chatConversation)
                : null;
        }
        $supportChatUrl = $supportChatUrl ?? route('site.borrower.support', ['chat' => 1]);
    @endphp

    <div x-data="{ human: @js((bool) $openHumanChat), q: @js($helpQuery), section: @js($helpSection) }">
        @if ($nidaLocked)
            <div id="identity-appeal" class="mb-6 rounded-2xl border border-amber-300 bg-gradient-to-r from-amber-50 to-white p-6">
                <p class="text-xs uppercase tracking-widest text-amber-700 font-semibold">{{ __('borrower.nida.title') }}</p>
                <h2 class="text-lg font-bold text-amber-950 mt-1">{{ __('borrower.support_page.identity_appeal_title') }}</h2>
                <p class="text-sm text-amber-900 mt-2">{{ __('borrower.nida.verification_locked_appeal') }}</p>
            </div>
        @endif

        <div x-show="!human" x-cloak class="space-y-6">
            <section class="relative overflow-hidden rounded-3xl kf-premium-panel">
                <div class="absolute inset-0 opacity-[0.14]" style="background-image: radial-gradient(circle at 18% 20%, #fff 0, transparent 42%), radial-gradient(circle at 88% 0%, #fbbf24 0, transparent 38%);"></div>
                <div class="relative px-5 sm:px-8 py-7 sm:py-9">
                    <p class="text-[10px] uppercase tracking-[0.22em] font-semibold text-white/70">{{ $isSw ? 'Kituo cha Usaidizi' : 'Help Centre' }}</p>
                    <h1 class="mt-2 text-2xl sm:text-3xl font-bold tracking-tight">{{ $isSw ? 'Unahitaji msaada gani?' : 'How can we help?' }}</h1>
                    <form method="GET" action="{{ route('site.borrower.support') }}" class="mt-5 max-w-xl"
                          @submit="if (!String(q || '').trim()) { $event.preventDefault(); window.dispatchEvent(new CustomEvent('kf-help-query', { detail: { q: '' } })); }">
                        <input type="hidden" name="section" value="help">
                        <input type="search" name="q" x-model="q"
                               @input="
                                    const v = String(q || '').trim();
                                    window.dispatchEvent(new CustomEvent('kf-help-query', { detail: { q: v } }));
                                    if (!v) {
                                        try {
                                            const u = new URL(window.location.href);
                                            u.searchParams.delete('q');
                                            history.replaceState({}, '', u.pathname + u.search + u.hash);
                                        } catch (e) {}
                                    }
                               "
                               placeholder="{{ $isSw ? 'mdhamini, ada ya maombi, malipo yamekwama, kubadilisha PIN…' : 'guarantor, application fee, payment pending, reset PIN…' }}"
                               class="w-full rounded-2xl border-0 bg-white/95 text-gray-900 text-sm px-4 py-3 shadow-sm focus:ring-2 focus:ring-brand-gold/50">
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
                        @if ($key === 'active' && $hasActive)
                            <span class="ml-1 inline-flex size-5 items-center justify-center rounded-full bg-brand-gold text-[10px] font-bold text-brand">!</span>
                        @endif
                    </button>
                @endforeach
            </nav>

            {{-- HELP --}}
            <div x-show="section === 'help'" x-cloak class="space-y-6">
                @include('site.help._landing-body', [
                    'isSw' => $isSw,
                    'helpCategories' => $helpCategories ?? [],
                    'helpGroups' => $helpGroups ?? [],
                    'helpResults' => $helpResults ?? [],
                    'helpQuery' => $helpQuery ?? '',
                    'helpTopic' => $helpTopic ?? '',
                    'helpArticle' => $helpArticle ?? '',
                    'homeUrl' => route('site.borrower.support'),
                    'chatUrl' => $supportChatUrl,
                    'phones' => $phones ?? support_phones(),
                    'primaryPhone' => $primaryPhone ?? null,
                ])
            </div>

            {{-- ACTIVE — at most one ongoing conversation (legacy multi → chooser only) --}}
            <div x-show="section === 'active'" x-cloak class="space-y-4">
                <x-site.support-open-conversations
                    :conversations="$openSupportConversations ?? []"
                    continue-route="site.borrower.support"
                    :is-sw="$isSw"
                />
                @if ($supportConversation && ($openSupportConversations ?? collect())->count() <= 1)
                    @php
                        $agentName = app(\App\Services\Support\SupportConversationService::class)
                            ->personFirstName((string) ($supportConversation->assignedTo?->name ?? '')) ?: null;
                        $desk = app(\App\Services\Support\SupportConversationService::class)->deskState($supportConversation, app()->getLocale());
                        $topicLabel = app(\App\Services\Support\SupportAutomationService::class)
                            ->customerFacingTopicLabel((string) ($supportConversation->topic ?? ''), 'member', app()->getLocale());
                    @endphp
                    <div class="rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm px-4 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="text-[11px] uppercase tracking-widest text-brand font-semibold">{{ $supportConversation->publicNumber() }}</p>
                                    @php $activeUnread = app(\App\Services\Support\SupportConversationService::class)->unreadForCustomer($supportConversation); @endphp
                                    @if ($activeUnread > 0)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-brand text-white text-[10px] font-bold px-1.5 py-0.5">● {{ $activeUnread }}</span>
                                    @endif
                                </div>
                                <p class="text-sm font-bold text-gray-900 mt-1 truncate">{{ $topicLabel }}</p>
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ $desk }}
                                    @if ($agentName) · {{ $agentName }} @endif
                                    · {{ format_app_datetime($supportConversation->last_message_at ?? $supportConversation->updated_at, 'd M Y · H:i') }}
                                </p>
                            </div>
                            <a href="{{ route('site.borrower.support', ['chat' => 1]) }}"
                               class="shrink-0 rounded-xl bg-brand-gold text-brand text-xs font-bold px-3.5 py-2 hover:brightness-95">
                                {{ $isSw ? 'Endelea' : 'Continue' }}
                            </a>
                        </div>
                    </div>
                @endif

                @if (! $hasActive)
                    <div class="rounded-2xl bg-slate-50 ring-1 ring-slate-200 p-6 text-center space-y-3">
                        <p class="text-sm text-gray-600">{{ $isSw ? 'Hakuna usaidizi unaoendelea sasa.' : 'No active support right now.' }}</p>
                        <a href="{{ route('site.borrower.support', ['chat' => 1]) }}"
                           class="inline-flex rounded-2xl bg-brand-gold text-brand font-bold text-sm px-5 py-3 hover:brightness-95">
                            {{ $isSw ? 'Ongea na Timu' : 'Talk to Support' }}
                        </a>
                    </div>
                @endif
            </div>

            {{-- HISTORY — resolved; Talk after closure starts a NEW conversation --}}
            <div x-show="section === 'history'" x-cloak class="space-y-4">
                @foreach ($supportHistory as $h)
                    @php
                        $hAgent = app(\App\Services\Support\SupportConversationService::class)
                            ->personFirstName((string) ($h->assignedTo?->name ?? '')) ?: null;
                        $hMeta = is_array($h->automation_meta) ? $h->automation_meta : [];
                        $hPersona = (string) ($hMeta['persona_name'] ?? '');
                        $hTopic = app(\App\Services\Support\SupportAutomationService::class)
                            ->customerFacingTopicLabel((string) ($h->topic ?? ''), 'member', app()->getLocale());
                    @endphp
                    <div class="rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm px-4 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[11px] uppercase tracking-widest text-brand font-semibold">{{ $h->publicNumber() }}</p>
                                <p class="text-sm font-bold text-gray-900 mt-1 truncate">{{ $hTopic }}</p>
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ $isSw ? 'Imekamilishwa' : 'Resolved' }}
                                    · {{ format_app_datetime($h->closed_at ?? $h->resolved_at ?? $h->last_message_at ?? $h->created_at, 'd M Y · H:i') }}
                                    @if ($hPersona) · {{ $hPersona }}
                                    @elseif ($hAgent) · {{ $hAgent }}
                                    @endif
                                    @if ($h->rating) · ★{{ $h->rating }} @endif
                                </p>
                            </div>
                            <div class="flex flex-col items-end gap-1.5 shrink-0">
                                @if ($h->awaitsRating())
                                    <a href="{{ route('site.borrower.support', ['chat' => 1, 'section' => 'history']) }}"
                                       class="rounded-xl bg-amber-100 text-amber-900 text-xs font-bold px-3.5 py-2">{{ $isSw ? 'Tathmini ★' : 'Rate ★' }}</a>
                                @endif
                                <a href="{{ route('site.borrower.support.history', $h) }}"
                                   class="rounded-xl ring-1 ring-brand/25 text-brand text-xs font-bold px-3.5 py-2 hover:bg-brand-muted/40">
                                    {{ $isSw ? 'Angalia' : 'View' }}
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach

                @foreach ($resolvedTickets as $t)
                    @php
                        $tStatus = app(\App\Services\Support\SupportConversationService::class)
                            ->customerFacingStatusLabel((string) $t->status, app()->getLocale());
                    @endphp
                    <div class="rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm px-4 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[11px] uppercase tracking-widest text-brand font-semibold">{{ $t->publicNumber() }}</p>
                                <p class="text-sm font-bold text-gray-900 mt-1 truncate">{{ $t->subject }}</p>
                                <p class="text-xs text-gray-500 mt-1">{{ $tStatus }} · {{ format_app_datetime($t->resolved_at ?? $t->updated_at, 'd M Y · H:i') }}</p>
                            </div>
                            <a href="{{ route('site.borrower.support.ticket.show', $t) }}"
                               class="shrink-0 rounded-xl ring-1 ring-brand/25 text-brand text-xs font-bold px-3.5 py-2 hover:bg-brand-muted/40">
                                {{ $isSw ? 'Angalia' : 'View' }}
                            </a>
                        </div>
                    </div>
                @endforeach

                @if ($supportHistory->isEmpty() && $resolvedTickets->isEmpty())
                    <p class="text-sm text-gray-500 text-center py-8">{{ $isSw ? 'Hakuna historia bado.' : 'No history yet.' }}</p>
                @endif

                <a href="{{ route('site.borrower.support', ['chat' => 1]) }}"
                   class="block rounded-2xl bg-brand-gold text-brand font-bold text-sm px-5 py-4 text-center hover:brightness-95">
                    {{ $isSw ? 'Ongea tena (mazungumzo mapya)' : 'Talk again (new conversation)' }}
                </a>
            </div>
        </div>

        {{-- LIVE CHAT — centered focused column (wizard width), not left-aligned under wide shell --}}
        <div id="support-human-chat"
             x-show="human"
             x-cloak
             class="scroll-mt-24 mb-8 max-w-3xl mx-auto w-full"
             @support-back-to-faqs.window="human = false; window.location = @js(route('site.borrower.support'))">
            <div class="mb-3">
                <a href="{{ route('site.borrower.support') }}" class="text-sm font-semibold text-brand hover:underline">← {{ $isSw ? 'Rudi Kituo cha Usaidizi' : 'Back to Help Center' }}</a>
            </div>
            <x-site.ai-support-chat
                class="mb-4"
                :member-mode="true"
                :automation-mode="true"
                :force-human="false"
                :automation-url="route('site.borrower.support.automation')"
                :speak-url="route('site.borrower.support.speak')"
                :thread-url="route('site.borrower.support.thread')"
                :conversation="$chatConversation"
                :existing-messages="$chatConversation?->messages"
                :rating-url="$rateUrl"
                :show-rating="$chatConversation?->awaitsRating() ?? false"
                :agent-label="$isSw ? 'Msaidizi wa Kopafasta' : 'Kopafasta Assistant'"
                :agent-subtitle="$isSw ? 'Msaada otomatiki saa 24' : 'Automated help 24/7'"
            />
        </div>
    </div>

</x-site.borrower-layout>
