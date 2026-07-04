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
    <x-site.ai-support-chat
        class="mb-8"
        :member-mode="true"
        :agent-label="__('borrower.support_page.assistant_title')"
        :agent-subtitle="__('borrower.support_page.assistant_subtitle')"
    />

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

</x-site.borrower-layout>
