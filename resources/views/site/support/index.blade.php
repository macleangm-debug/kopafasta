<x-site.layout :title="brand_title(__('site.support.title'))">
    @php
        $isSw = $isSw ?? str_starts_with(app()->getLocale(), 'sw');
        $phones = $phones ?? support_phones();
        $primaryPhone = $primaryPhone ?? ($phones[0] ?? null);
    @endphp

    <section class="relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-brand via-brand to-brand-light"></div>
        <div class="absolute inset-0 opacity-[0.14]" style="background-image: radial-gradient(circle at 18% 20%, #fff 0, transparent 42%), radial-gradient(circle at 88% 0%, #fbbf24 0, transparent 38%);"></div>
        <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14">
            <p class="text-[10px] uppercase tracking-[0.22em] font-semibold text-white/70">{{ $isSw ? 'Msaada' : 'Support' }}</p>
            <h1 class="mt-2 text-2xl sm:text-4xl font-bold tracking-tight text-white">{{ $isSw ? 'Unahitaji msaada gani?' : 'How can we help?' }}</h1>
            <form method="GET" action="{{ route('site.support') }}" class="mt-5 max-w-xl">
                <input type="search" name="q" value="{{ $helpQuery ?? '' }}"
                       placeholder="{{ $isSw ? 'kujisajili, bidhaa, PIN, mdhamini…' : 'registration, products, PIN, guarantor…' }}"
                       class="w-full rounded-2xl border-0 bg-white/95 text-gray-900 text-sm px-4 py-3 shadow-sm focus:ring-2 focus:ring-brand-gold/50">
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
            'chatUrl' => route('site.feedback', ['open' => 1]),
            'showChatCard' => false,
            'phones' => $phones,
            'primaryPhone' => $primaryPhone,
        ])
    </section>
</x-site.layout>
