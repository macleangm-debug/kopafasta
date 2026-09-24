@props([
    'title',
    'badge',
    'heading',
    'support' => null,
    'identity' => null,
    'asideEyebrow' => null,
    'asideTitle' => null,
    'asideBody' => null,
    'auth' => true,
])

<x-site.layout :auth="$auth" :title="$title">
    <section class="h-full min-h-0 grid lg:grid-cols-2 premium-gradient overflow-hidden">
        <aside class="hidden lg:flex relative overflow-hidden bg-brand text-white p-10 xl:p-12 flex-col justify-between">
            <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_bottom_left,_#f5c842,_transparent_50%)]"></div>
            <a href="{{ route('site.home') }}" class="relative"><x-site.brand-mark variant="light" /></a>
            <div class="relative">
                <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">{{ $asideEyebrow ?: $badge }}</p>
                <h2 class="mt-3 text-3xl xl:text-4xl font-bold tracking-tight leading-tight">{{ $asideTitle ?: $heading }}</h2>
                @if ($identity)
                    <p class="mt-3 text-lg font-semibold text-white">{{ $identity }}</p>
                @endif
                @if ($asideBody || $support)
                    <p class="mt-3 text-white/70 max-w-md text-sm leading-relaxed">{{ $asideBody ?: $support }}</p>
                @endif
                @isset($asideExtra)
                    <div class="relative mt-6">{{ $asideExtra }}</div>
                @endisset
            </div>
            <p class="relative text-xs text-white/50">&copy; {{ date('Y') }} {{ brand('legal_name') }}</p>
        </aside>

        <div {{ $attributes->merge(['class' => 'h-full min-h-0 overflow-y-auto overscroll-y-contain flex items-center justify-center px-4 py-6 sm:px-12 sm:py-10 form-scroll-lock']) }}>
            <div class="w-full max-w-md glass-card overflow-hidden">
                <div class="lg:hidden px-5 pt-5">
                    <a href="{{ route('site.home') }}" class="inline-block"><x-site.brand-mark size="md" /></a>
                </div>

                <div class="kf-premium-panel mx-4 mt-4 mb-0 rounded-2xl px-5 py-5 sm:mx-5 sm:px-6 sm:py-5">
                    <div class="relative">
                        <span class="inline-flex items-center rounded-full bg-brand-gold/15 text-brand-gold ring-1 ring-brand-gold/40 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-[0.18em]">{{ $badge }}</span>
                        <h1 class="mt-2.5 text-xl sm:text-2xl font-bold tracking-tight leading-tight">{{ $heading }}</h1>
                        @if ($identity)
                            <p class="mt-1.5 text-base font-semibold text-white">{{ $identity }}</p>
                        @endif
                        @if ($support)
                            <p class="mt-1.5 text-sm text-white/80 leading-relaxed">{{ $support }}</p>
                        @endif
                    </div>
                </div>

                <div class="px-5 pt-5 pb-6 sm:px-6 sm:pb-7">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </section>
</x-site.layout>
