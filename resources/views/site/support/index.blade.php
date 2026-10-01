<x-site.layout :title="brand_title(__('site.support.title'))">
    @php
        $isSw = $isSw ?? str_starts_with(app()->getLocale(), 'sw');
        $phones = $phones ?? support_phones();
        $primaryPhone = $primaryPhone ?? ($phones[0] ?? null);
    @endphp

    <section class="relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-brand via-[#0f6b54] to-[#082f27]"></div>
        <div class="absolute inset-0 opacity-[0.18] pointer-events-none" style="background-image:url(&quot;data:image/svg+xml,%3Csvg width='72' height='48' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M6 36l14-24 14 24M30 36l14-24 14 24' fill='none' stroke='%23f5c842' stroke-opacity='0.55' stroke-width='2'/%3E%3C/svg%3E&quot;); background-size:72px 48px;"></div>
        <div class="absolute -right-16 -top-20 h-64 w-64 rounded-full bg-brand-gold/15 pointer-events-none"></div>
        <div class="absolute -left-20 bottom-0 h-52 w-52 rounded-full bg-white/5 pointer-events-none"></div>
        <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16 lg:py-20">
            <p class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.22em] text-brand-gold">
                <span class="text-lg tracking-[-0.18em] leading-none" aria-hidden="true">›››</span>
                {{ $isSw ? 'Msaada' : 'Support' }}
            </p>
            <h1 class="mt-3 text-3xl sm:text-5xl font-black tracking-tight text-white leading-[1.05]">
                {{ $isSw ? 'Unahitaji msaada gani?' : 'How can we help?' }}
            </h1>
            <p class="mt-4 text-base sm:text-lg text-white/80 max-w-2xl leading-relaxed">
                {{ $isSw
                    ? 'Pata majibu haraka kuhusu akaunti, mikopo, malipo na huduma za Kopafasta.'
                    : 'Get quick answers about your account, loans, payments and Kopafasta services.' }}
            </p>
            <form method="GET" action="{{ route('site.support') }}" class="mt-7 max-w-xl">
                <label class="sr-only" for="public-support-q">{{ $isSw ? 'Tafuta' : 'Search' }}</label>
                <input id="public-support-q" type="search" name="q" value="{{ $helpQuery ?? '' }}"
                       placeholder="{{ $isSw ? 'kujisajili, bidhaa, PIN, mdhamini…' : 'registration, products, PIN, guarantor…' }}"
                       class="w-full rounded-2xl border-0 bg-white text-gray-900 text-sm sm:text-base px-5 py-3.5 shadow-lg shadow-black/10 focus:ring-2 focus:ring-brand-gold/60">
            </form>
        </div>
    </section>

    <section class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-10 space-y-6">
        @include('site.help._landing-body', [
            'isSw' => $isSw,
            'helpCategories' => $helpCategories ?? [],
            'helpGroups' => $helpGroups ?? [],
            'helpResults' => $helpResults ?? [],
            'helpQuery' => $helpQuery ?? '',
            'helpTopic' => $helpTopic ?? '',
            'helpArticle' => $helpArticle ?? '',
            'homeUrl' => route('site.support'),
            'chatUrl' => route('site.support.chat'),
            'showChatCard' => true,
            'phones' => $phones,
            'primaryPhone' => $primaryPhone,
        ])
    </section>

    <x-site.feedback-form-panel
        :show-trigger="false"
        :show-faq-link="false"
        :open-on-load="request()->boolean('feedback')"
        from="public"
    />
</x-site.layout>
