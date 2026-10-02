{{--
  Shared premium split-screen shell for Staff/Admin secure access.
  States change inside the card; aside branding stays consistent.
  Desktop: split-screen. Mobile: focused card only.
--}}
@props([
    'title' => null,
    'asideEyebrow' => 'Secure access',
    'asideTitle' => 'Operate loans, partners, and recoveries from one calm workspace.',
    'asideBody' => 'Secure access for credit, collections, and operations teams.',
    'cardClass' => 'max-w-md',
    'errorTitle' => 'Something went wrong',
])

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
    @vite(['resources/css/app.css'])
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="h-full antialiased" {{ $attributes->except(['class']) }}>
<div class="min-h-full grid lg:grid-cols-2">
    <aside class="relative hidden lg:flex flex-col justify-between overflow-hidden bg-brand text-white px-10 py-12"
           style="background-color: #0B3D32; color: #fff;">
        <div class="absolute inset-0 opacity-40 pointer-events-none" style="background-image: radial-gradient(circle at 20% 20%, rgba(251,191,36,0.35), transparent 42%), radial-gradient(circle at 90% 80%, rgba(255,255,255,0.12), transparent 40%);"></div>
        <div class="relative">
            <div class="inline-flex items-center gap-3">
                <x-site.brand-mark size="lg" variant="light" />
            </div>
            <p class="mt-3 text-xs uppercase tracking-[0.2em] text-white/60">{{ $asideEyebrow }}</p>
            <h1 class="mt-16 text-4xl font-bold leading-tight max-w-md">{{ $asideTitle }}</h1>
            @if (filled($asideBody))
                <p class="mt-4 text-sm text-white/70 max-w-sm">{{ $asideBody }}</p>
            @endif
            @isset($asideExtra)
                <div class="relative mt-6">{{ $asideExtra }}</div>
            @endisset
        </div>
        <p class="relative text-xs text-white/50">© {{ date('Y') }} {{ brand('legal_name') }}</p>
    </aside>

    <main class="relative flex items-center justify-center px-4 py-10 sm:px-6 bg-[#F4F7F5]">
        <div class="absolute inset-0 opacity-60" style="background-image: linear-gradient(180deg, rgba(11,61,50,0.04), transparent 40%), radial-gradient(circle at 80% 10%, rgba(251,191,36,0.12), transparent 35%);"></div>
        <div class="relative w-full {{ $cardClass }} rounded-3xl bg-white/95 p-6 sm:p-8 shadow-[0_24px_80px_rgba(11,61,50,0.12)] ring-1 ring-[#0B3D32]/10">
            <div class="lg:hidden mb-6">
                <x-site.brand-mark size="md" />
            </div>
            {{ $slot }}
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
