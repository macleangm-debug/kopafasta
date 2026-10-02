{{--
  Shared premium Staff/Admin authentication experience.
  Same composition as Borrower/Partner x-site.auth-shell:
  desktop split-screen + composed glass card (premium panel hero + form).
  Mobile: compact brand → focused card. Presentation only — no auth logic.
--}}
@props([
    'title' => null,
    'badge' => 'Secure staff access',
    'heading' => 'Welcome back',
    'support' => null,
    'identity' => null,
    'asideEyebrow' => 'Secure staff access',
    'asideTitle' => 'Operate loans, partners, and recoveries from one calm workspace.',
    'asideBody' => 'Secure access for credit, collections, and operations teams.',
    'cardClass' => 'max-w-md',
    'errorTitle' => 'Something went wrong',
])

@php
    $isSw = str_starts_with(app()->getLocale(), 'sw');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" translate="no" class="notranslate h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $pageTitle = $title ?: brand_title('Sign in');
        $seoDocument = app(\App\Services\SeoService::class)->privateDocument(request(), $pageTitle);
    @endphp
    <x-site.seo :document="$seoDocument" />
    <link rel="icon" href="{{ asset(ltrim((string) brand('logo_mark_url', 'images/brand/kopafasta-mark.png'), '/')) }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset(ltrim((string) brand('logo_mark_url', 'images/brand/kopafasta-mark.png'), '/')) }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="h-full antialiased" {{ $attributes->except(['class']) }}>
<div class="min-h-full grid lg:grid-cols-2 premium-gradient">
    <aside class="relative hidden lg:flex flex-col justify-between overflow-hidden bg-brand text-white px-10 py-12 xl:px-12"
           style="background-color: #0B3D32; color: #fff;">
        <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_bottom_left,_#f5c842,_transparent_50%)]"></div>
        <div class="relative">
            <div class="inline-flex items-center gap-3">
                <x-site.brand-mark size="lg" variant="light" />
            </div>
            <p class="mt-4 text-xs uppercase tracking-[0.2em] text-brand-gold font-semibold">{{ $asideEyebrow }}</p>
            <h1 class="mt-10 text-3xl xl:text-4xl font-bold leading-tight tracking-tight max-w-md">{{ $asideTitle }}</h1>
            @if (filled($asideBody))
                <p class="mt-4 text-sm text-white/70 max-w-sm leading-relaxed">{{ $asideBody }}</p>
            @endif
            @isset($asideExtra)
                <div class="relative mt-6">{{ $asideExtra }}</div>
            @endisset
        </div>
        <p class="relative text-xs text-white/50">© {{ date('Y') }} {{ brand('legal_name') }}</p>
    </aside>

    <main class="relative h-full min-h-0 overflow-y-auto overscroll-y-contain flex items-start lg:items-center justify-center px-4 py-6 sm:px-8 sm:py-10">
        <div class="absolute inset-0 opacity-50 pointer-events-none" style="background-image: linear-gradient(180deg, rgba(11,61,50,0.04), transparent 40%), radial-gradient(circle at 80% 10%, rgba(251,191,36,0.12), transparent 35%);"></div>
        <div class="relative w-full {{ $cardClass }} glass-card overflow-hidden">
            <div class="lg:hidden px-5 pt-5">
                <x-site.brand-mark size="md" />
            </div>

            <div class="kf-premium-panel mx-4 mt-4 mb-0 rounded-2xl px-5 py-5 sm:mx-5 sm:px-6 sm:py-5">
                <div class="relative">
                    <span class="inline-flex items-center rounded-full bg-brand-gold/15 text-brand-gold ring-1 ring-brand-gold/40 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-[0.18em]">{{ $badge }}</span>
                    <h1 class="mt-2.5 text-xl sm:text-2xl font-bold tracking-tight leading-tight">{{ $heading }}</h1>
                    @if (filled($identity))
                        <p class="mt-1.5 text-base font-semibold text-white">{{ $identity }}</p>
                    @endif
                    @if (filled($support))
                        <p class="mt-1.5 text-sm text-white/80 leading-relaxed">{{ $support }}</p>
                    @endif
                </div>
            </div>

            <div class="px-5 pt-5 pb-6 sm:px-6 sm:pb-7">
                {{ $slot }}
            </div>
        </div>
    </main>
</div>

<x-site.feedback-modal name="default" />
@if ($errors->any())
    <script>
        document.addEventListener('alpine:initialized', () => {
            window.dispatchEvent(new CustomEvent('open-feedback-default', {
                detail: {
                    tone: 'error',
                    title: @js($errorTitle),
                    message: @js($errors->first()),
                },
            }));
        });
    </script>
@endif
@vite('resources/js/alpine-init.js')
</body>
</html>
