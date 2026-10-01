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
        $hasActive = (bool) $supportConversation
            || (($openSupportConversations ?? collect())->isNotEmpty())
            || $openTickets->isNotEmpty();
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

            <div x-show="section === 'help'" x-cloak class="space-y-6">
                @include('site.help._landing-body', [
                    'isSw' => $isSw,
                    'helpCategories' => $helpCategories ?? [],
                    'helpGroups' => $helpGroups ?? [],
                    'helpResults' => $helpResults ?? [],
                    'helpQuery' => $helpQuery ?? '',
                    'helpTopic' => $helpTopic ?? '',
                    'helpArticle' => $helpArticle ?? '',
                    'homeUrl' => $homeUrl,
                    'chatUrl' => $chatUrl,
                    'phones' => support_phones(),
                    'primaryPhone' => $supportPhone ?? (support_phones()[0] ?? null),
                ])
            </div>

            <div x-show="section === 'active'" x-cloak class="space-y-4">
                <x-site.support-open-conversations
                    :conversations="$openSupportConversations ?? []"
                    :continue-route="request()->routeIs('site.vendor.*') ? 'site.vendor.support' : 'site.partner.support'"
                    :is-sw="$isSw"
                />
                @if ($supportConversation)
                    @php
                        $agentName = app(\App\Services\Support\SupportConversationService::class)
                            ->personFirstName((string) ($supportConversation->assignedTo?->name ?? '')) ?: null;
                        $desk = app(\App\Services\Support\SupportConversationService::class)->deskState($supportConversation);
                    @endphp
                    <div class="rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm px-4 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[11px] uppercase tracking-widest text-brand font-semibold">{{ $supportConversation->publicNumber() }}</p>
                                <p class="text-sm font-bold text-gray-900 mt-1 truncate">{{ $supportConversation->topic ?: ($isSw ? 'Suala la msaada' : 'Support issue') }}</p>
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ $desk }}
                                    @if ($agentName) · {{ $agentName }} @endif
                                    · {{ format_app_datetime($supportConversation->last_message_at ?? $supportConversation->updated_at, 'd M Y · H:i') }}
                                </p>
                            </div>
                            <a href="{{ $chatUrl }}"
                               class="shrink-0 rounded-xl bg-brand-gold text-brand text-xs font-bold px-3.5 py-2 hover:brightness-95">
                                {{ $isSw ? 'Endelea' : 'Continue' }}
                            </a>
                        </div>
                    </div>
                @endif
                @foreach ($openTickets as $t)
                    <div class="rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm px-4 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[11px] uppercase tracking-widest text-brand font-semibold">{{ $t->publicNumber() }}</p>
                                <p class="text-sm font-bold text-gray-900 mt-1 truncate">{{ $t->subject }}</p>
                                <p class="text-xs text-gray-500 mt-1">{{ ucfirst($t->status) }} · {{ format_app_datetime($t->updated_at, 'd M Y · H:i') }}</p>
                            </div>
                            <a href="{{ request()->routeIs('site.vendor.*') ? route('site.vendor.support.ticket.show', $t) : route('site.partner.support.ticket.show', $t) }}"
                               class="shrink-0 rounded-xl ring-1 ring-brand/25 text-brand text-xs font-bold px-3.5 py-2 hover:bg-brand-muted/40">
                                {{ $isSw ? 'Angalia tiketi' : 'View ticket' }}
                            </a>
                        </div>
                    </div>
                @endforeach
                @if (! $hasActive)
                    <div class="rounded-2xl bg-slate-50 ring-1 ring-slate-200 p-6 text-center space-y-3">
                        <p class="text-sm text-gray-600">{{ $isSw ? 'Hakuna usaidizi unaoendelea.' : 'No active support.' }}</p>
                        <a href="{{ $chatUrl }}" class="inline-flex rounded-2xl bg-brand-gold text-brand font-bold text-sm px-5 py-3">{{ $isSw ? 'Ongea na Timu' : 'Talk to Support' }}</a>
                    </div>
                @endif
            </div>

            <div x-show="section === 'history'" x-cloak class="space-y-4">
                @foreach ($supportHistory as $h)
                    @php
                        $hAgent = app(\App\Services\Support\SupportConversationService::class)
                            ->personFirstName((string) ($h->assignedTo?->name ?? '')) ?: null;
                        $historyViewUrl = $chatUrl.(str_contains($chatUrl, '?') ? '&' : '?').'section=history';
                    @endphp
                    <div class="rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm px-4 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[11px] uppercase tracking-widest text-brand font-semibold">{{ $h->publicNumber() }}</p>
                                <p class="text-sm font-bold text-gray-900 mt-1 truncate">{{ $h->topic ?: ($isSw ? 'Suala la msaada' : 'Support issue') }}</p>
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ $isSw ? 'Imekamilishwa' : 'Resolved' }}
                                    · {{ format_app_datetime($h->closed_at ?? $h->resolved_at ?? $h->last_message_at ?? $h->created_at, 'd M Y · H:i') }}
                                    @if ($hAgent) · {{ $hAgent }} @endif
                                    @if ($h->rating) · ★{{ $h->rating }} @endif
                                </p>
                            </div>
                            <div class="flex flex-col items-end gap-1.5 shrink-0">
                                @if ($h->awaitsRating())
                                    <a href="{{ $historyViewUrl }}" class="rounded-xl bg-amber-100 text-amber-900 text-xs font-bold px-3.5 py-2">{{ $isSw ? 'Tathmini ★' : 'Rate ★' }}</a>
                                @endif
                                <a href="{{ $historyViewUrl }}"
                                   class="rounded-xl ring-1 ring-brand/25 text-brand text-xs font-bold px-3.5 py-2 hover:bg-brand-muted/40">
                                    {{ $isSw ? 'Angalia' : 'View' }}
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
                @foreach ($resolvedTickets as $t)
                    <div class="rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm px-4 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[11px] uppercase tracking-widest text-brand font-semibold">{{ $t->publicNumber() }}</p>
                                <p class="text-sm font-bold text-gray-900 mt-1 truncate">{{ $t->subject }}</p>
                                <p class="text-xs text-gray-500 mt-1">{{ $isSw ? 'Imekamilishwa' : 'Resolved' }} · {{ format_app_datetime($t->resolved_at ?? $t->updated_at, 'd M Y · H:i') }}</p>
                            </div>
                            <a href="{{ request()->routeIs('site.vendor.*') ? route('site.vendor.support.ticket.show', $t) : route('site.partner.support.ticket.show', $t) }}"
                               class="shrink-0 rounded-xl ring-1 ring-brand/25 text-brand text-xs font-bold px-3.5 py-2 hover:bg-brand-muted/40">
                                {{ $isSw ? 'Angalia' : 'View' }}
                            </a>
                        </div>
                    </div>
                @endforeach
                @if ($supportHistory->isEmpty() && $resolvedTickets->isEmpty())
                    <p class="text-sm text-gray-500 text-center py-8">{{ $isSw ? 'Hakuna historia bado.' : 'No history yet.' }}</p>
                @endif
                <a href="{{ $chatUrl }}" class="block rounded-2xl bg-brand-gold text-brand font-bold text-sm px-5 py-4 text-center">
                    {{ $isSw ? 'Ongea tena (mazungumzo mapya)' : 'Talk again (new conversation)' }}
                </a>
            </div>
        </div>

        <div x-show="human" x-cloak class="max-w-3xl mx-auto w-full">
            <div class="mb-3">
                <a href="{{ $homeUrl }}" class="text-sm font-semibold text-brand hover:underline">← {{ $isSw ? 'Rudi Kituo cha Usaidizi' : 'Back to Help Center' }}</a>
            </div>
            <x-site.ai-support-chat
                class="mb-4"
                :member-mode="true"
                :automation-mode="true"
                :force-human="false"
                :automation-url="route('site.partner.support.automation')"
                :speak-url="$speakUrl"
                :thread-url="$threadUrl"
                :conversation="$chatConversation"
                :existing-messages="$chatConversation?->messages"
                :rating-url="$rateUrl"
                :show-rating="$showRating"
                :agent-label="$isSw ? 'Msaidizi wa Kopafasta' : 'Kopafasta Assistant'"
                :agent-subtitle="$isSw ? 'Msaada otomatiki saa 24' : 'Automated help 24/7'"
            />
        </div>
    </div>

</x-site.vendor-layout>
