@php
    $docs = [
        ['key' => 'index', 'route' => 'site.legal', 'label' => __('legal.nav.hub')],
        ['key' => 'terms', 'route' => 'site.legal.terms', 'label' => __('legal.nav.terms')],
        ['key' => 'privacy', 'route' => 'site.legal.privacy', 'label' => __('legal.nav.privacy')],
        ['key' => 'aml', 'route' => 'site.legal.aml', 'label' => __('legal.nav.aml')],
        ['key' => 'complaints', 'route' => 'site.legal.complaints', 'label' => __('legal.nav.complaints')],
        ['key' => 'cookies', 'route' => 'site.legal.cookies', 'label' => __('legal.nav.cookies')],
    ];
    $active = $active ?? 'index';
@endphp

<div class="min-h-[calc(100dvh-4rem)] bg-gradient-to-b from-brand/[0.04] via-white to-slate-50">
    <section class="border-b border-brand/10 bg-brand text-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14">
            <p class="text-xs uppercase tracking-[0.2em] text-brand-gold font-semibold">{{ __('legal.hub.eyebrow') }}</p>
            <h1 class="mt-2 text-3xl sm:text-4xl font-bold tracking-tight">{{ $heading }}</h1>
            @if (! empty($subheading))
                <p class="mt-3 text-white/75 max-w-2xl text-sm sm:text-base">{{ $subheading }}</p>
            @endif
            <p class="mt-4 text-xs text-white/55">{{ __('legal.hub.effective', ['date' => $effective ?? now()->format('d F Y')]) }}</p>
        </div>
    </section>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-12">
        <div class="grid lg:grid-cols-12 gap-6 lg:gap-10">
            <aside class="lg:col-span-4 xl:col-span-3">
                <nav class="rounded-2xl bg-white ring-1 ring-gray-200 shadow-sm p-3 sticky top-24 space-y-1">
                    <p class="px-3 pt-2 pb-1 text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('legal.nav.documents') }}</p>
                    @foreach ($docs as $doc)
                        <a href="{{ route($doc['route']) }}"
                           @class([
                               'block rounded-xl px-3 py-2.5 text-sm font-medium transition',
                               'bg-brand text-white' => $active === $doc['key'],
                               'text-gray-700 hover:bg-brand-muted/60' => $active !== $doc['key'],
                           ])>
                            {{ $doc['label'] }}
                        </a>
                    @endforeach
                    <p class="px-3 pt-3 pb-2 text-[11px] text-gray-500 leading-relaxed">{{ __('legal.hub.locale_hint') }}</p>
                </nav>
            </aside>

            <div class="lg:col-span-8 xl:col-span-9">
                <article class="rounded-2xl bg-white ring-1 ring-gray-200 shadow-sm px-5 sm:px-8 py-7 sm:py-9">
                    {{ $slot }}
                </article>
            </div>
        </div>
    </div>
</div>
