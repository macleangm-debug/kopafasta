<x-site.vendor-layout :title="__('site.partner_portal.nav_support')" active="support">
    @php
        $wa = preg_replace('/\D+/', '', (string) $supportWhatsapp) ?: '';
        $tel = preg_replace('/\s+/', '', (string) $supportPhone);
        $openHumanChat = $openHumanChat ?? false;
        $supportConversation = $supportConversation ?? null;
        $supportHistory = $supportHistory ?? collect();
        $helpGroups = $helpGroups ?? [];
        $helpResults = $helpResults ?? [];
        $helpQuery = $helpQuery ?? '';
        $chatUrl = $chatUrl ?? route('site.partner.support', ['chat' => 1]);
        $homeUrl = $supportPageUrl ?? route('site.partner.support');
        $feedbackUrl = $feedbackUrl ?? route('site.feedback', ['open' => 1, 'from' => 'partner']);
        $isSw = str_starts_with(app()->getLocale(), 'sw');
    @endphp

    <div x-data="{ human: @js((bool) $openHumanChat) }">
        <div x-show="!human" x-cloak class="space-y-6">
            <section class="relative overflow-hidden rounded-3xl kf-premium-panel">
                <div class="relative px-5 sm:px-8 py-7 sm:py-9">
                    <p class="text-[10px] uppercase tracking-[0.22em] font-semibold text-white/70">Kopafasta Support</p>
                    <h1 class="mt-2 text-2xl sm:text-3xl font-bold tracking-tight text-white">{{ $isSw ? 'Unahitaji msaada gani?' : 'How can we help?' }}</h1>
                    <form method="GET" action="{{ $homeUrl }}" class="mt-5 max-w-xl">
                        <input type="search" name="q" value="{{ $helpQuery }}"
                               placeholder="{{ $isSw ? 'Tafuta msaada wa Partner…' : 'Search Partner help…' }}"
                               class="w-full rounded-2xl border-0 bg-white/95 text-gray-900 text-sm px-4 py-3 shadow-sm">
                    </form>
                </div>
            </section>

            @if ($supportConversation)
                <a href="{{ $chatUrl }}" class="block rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm px-4 py-4 hover:bg-brand-muted/20">
                    <p class="text-[11px] uppercase tracking-widest text-brand font-semibold">{{ $isSw ? 'Endelea mazungumzo' : 'Continue conversation' }}</p>
                    <p class="text-sm font-bold text-gray-900 mt-1">Kopafasta Support · #{{ $supportConversation->id }}</p>
                </a>
            @endif

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

            @if ($supportHistory->isNotEmpty())
                <section class="rounded-2xl bg-white ring-1 ring-brand/10 p-5">
                    <h2 class="font-semibold text-sm">{{ $isSw ? 'Historia' : 'History' }}</h2>
                    <ul class="mt-3 divide-y divide-gray-100">
                        @foreach ($supportHistory as $h)
                            <li class="py-2.5 text-sm">
                                <p class="font-semibold">{{ $h->topic ?: ('#'.$h->id) }}</p>
                                <p class="text-xs text-gray-500">{{ format_app_datetime($h->last_message_at ?? $h->created_at, 'd M Y · H:i') }}</p>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <div class="grid sm:grid-cols-2 gap-3">
                <a href="{{ $chatUrl }}" class="rounded-2xl bg-brand-gold text-brand font-bold text-sm px-5 py-4 text-center">{{ $isSw ? 'Ongea na Timu ya Usaidizi' : 'Talk to Support' }}</a>
                <a href="{{ $feedbackUrl }}" class="rounded-2xl ring-1 ring-brand/20 text-brand font-bold text-sm px-5 py-4 text-center">{{ $isSw ? 'Tuma maoni' : 'Send feedback' }}</a>
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

        <div x-show="human" x-cloak class="max-w-2xl">
            <div class="mb-3">
                <a href="{{ $homeUrl }}" class="text-sm font-semibold text-brand hover:underline">← {{ $isSw ? 'Rudi Support Home' : 'Back to Support Home' }}</a>
            </div>
            <x-site.ai-support-chat
                class="mb-4"
                :member-mode="true"
                :force-human="true"
                :speak-url="$speakUrl"
                :thread-url="$threadUrl"
                :conversation="$supportConversation"
                :existing-messages="$supportConversation?->messages"
            />
        </div>
    </div>
</x-site.vendor-layout>
