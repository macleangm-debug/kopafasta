<x-site.layout :title="__('legal.hub.title').' — Kopafasta'">
    <x-site.legal-shell active="index" :heading="__('legal.hub.title')" :subheading="__('legal.hub.subtitle', ['brand' => brand('legal_name')])">
        <div class="space-y-3">
            @foreach ([
                ['legal.nav.terms', 'site.legal.terms', 'legal.cards.terms'],
                ['legal.nav.privacy', 'site.legal.privacy', 'legal.cards.privacy'],
                ['legal.nav.aml', 'site.legal.aml', 'legal.cards.aml'],
                ['legal.nav.complaints', 'site.legal.complaints', 'legal.cards.complaints'],
                ['legal.nav.cookies', 'site.legal.cookies', 'legal.cards.cookies'],
            ] as [$titleKey, $route, $hintKey])
                <div class="rounded-xl ring-1 ring-gray-200 px-4 py-3.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <p class="font-semibold text-gray-900">{{ __($titleKey) }}</p>
                        <p class="text-sm text-gray-500 mt-0.5">{{ __($hintKey) }}</p>
                    </div>
                    <a href="{{ route($route) }}" class="shrink-0 rounded-xl bg-brand text-white text-sm font-semibold px-4 py-2.5 hover:bg-brand-light">{{ __('legal.hub.read') }}</a>
                </div>
            @endforeach

            @foreach ([
                ['Loan agreement & offer letters', 'legal.cards.loan'],
                ['Credit disclosure / key facts', 'legal.cards.disclosure'],
                ['Data processing / consent notice', 'legal.cards.consent'],
            ] as [$title, $hintKey])
                <div class="rounded-xl ring-1 ring-gray-100 bg-gray-50 px-4 py-3.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <p class="font-semibold text-gray-800">{{ $title }}</p>
                        <p class="text-sm text-gray-500 mt-0.5">{{ __($hintKey) }}</p>
                    </div>
                    <span class="shrink-0 text-[10px] font-bold uppercase tracking-wide text-gray-400">{{ __('legal.hub.in_product') }}</span>
                </div>
            @endforeach
        </div>
    </x-site.legal-shell>
</x-site.layout>
