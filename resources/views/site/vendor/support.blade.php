<x-site.vendor-layout :title="__('site.partner_portal.nav_support')" active="support">
    @php
        $wa = preg_replace('/\D+/', '', (string) $supportWhatsapp) ?: '255700000000';
        $tel = preg_replace('/\s+/', '', (string) $supportPhone);
        $openHumanChat = $openHumanChat ?? false;
        $supportConversation = $supportConversation ?? null;
    @endphp

    <div x-data="{ human: @js((bool) $openHumanChat) }">
        <div x-show="!human" x-cloak>
            <div class="grid sm:grid-cols-3 gap-4 mb-8">
                <a href="tel:{{ $tel }}" class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 hover:shadow-sm">
                    <div class="size-10 rounded-full bg-indigo-100 text-brand grid place-items-center mb-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.86 19.86 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.86 19.86 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 13 13 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 13 13 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    </div>
                    <p class="font-bold">{{ __('site.partner_portal.support_call') }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ $supportPhone }}</p>
                </a>
                <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener" class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 hover:shadow-sm">
                    <div class="size-10 rounded-full bg-emerald-100 text-emerald-700 grid place-items-center mb-3">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20 4a10 10 0 0 0-15 13l-1 4 4-1a10 10 0 0 0 12-16z"/></svg>
                    </div>
                    <p class="font-bold">{{ __('site.partner_portal.support_whatsapp') }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ __('site.partner_portal.support_whatsapp_hint') }}</p>
                </a>
                <a href="mailto:{{ $supportEmail }}" class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 hover:shadow-sm">
                    <div class="size-10 rounded-full bg-sky-100 text-sky-700 grid place-items-center mb-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16v16H4z"/><path d="m22 6-10 7L2 6"/></svg>
                    </div>
                    <p class="font-bold">{{ __('site.partner_portal.support_email') }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ $supportEmail }}</p>
                </a>
            </div>

            <section class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 sm:p-6 mb-6">
                <h2 class="text-lg font-bold text-gray-900 mb-1">{{ __('site.partner_portal.faq_title') }}</h2>
                <p class="text-sm text-gray-500 mb-4">{{ __('site.partner_portal.faq_subtitle') }}</p>
                <div class="divide-y divide-gray-100" x-data="{ open: null }">
                    @foreach ($faqs as $i => $item)
                        <div class="py-3">
                            <button type="button" class="w-full flex items-center justify-between gap-3 text-left"
                                    @click="open = open === {{ $i }} ? null : {{ $i }}">
                                <span class="font-semibold text-sm text-gray-900">{{ $item['q'] ?? '' }}</span>
                                <svg class="w-4 h-4 text-gray-400 shrink-0 transition" :class="open === {{ $i }} && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
                            </button>
                            <p class="text-sm text-gray-600 mt-2" x-show="open === {{ $i }}" x-cloak>{{ $item['a'] ?? '' }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 sm:p-6">
                <p class="text-sm font-semibold text-gray-900">{{ __('borrower.support_page.still_need_help') }}</p>
                <p class="text-sm text-gray-500 mt-1">{{ __('borrower.support_page.still_need_help_hint') }}</p>
                <button type="button" @click="human = true"
                        class="mt-4 inline-flex rounded-xl bg-brand-gold text-brand font-bold text-sm px-5 py-2.5 hover:brightness-95">
                    {{ __('borrower.support_page.talk_to_team') }}
                </button>
            </div>
        </div>

        <div x-show="human"
             x-cloak
             class="max-w-2xl"
             @support-back-to-faqs.window="human = false">
            <x-site.ai-support-chat
                class="mb-4"
                :member-mode="true"
                :force-human="true"
                :speak-url="$speakUrl"
                :thread-url="$threadUrl"
                :conversation="$supportConversation"
                :existing-messages="$supportConversation?->messages"
                :show-back-to-faqs="! ($supportConversation && $supportConversation->messages->isNotEmpty())"
            />
        </div>
    </div>
</x-site.vendor-layout>
