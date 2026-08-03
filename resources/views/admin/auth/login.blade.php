<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in · {{ brand_name() }} Console</title>
    @vite(['resources/css/app.css'])
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="h-full antialiased" x-data>
<div class="min-h-full grid lg:grid-cols-2">
    <aside class="relative hidden lg:flex flex-col justify-between overflow-hidden bg-[#0B3D32] text-white px-10 py-12">
        <div class="absolute inset-0 opacity-40" style="background-image: radial-gradient(circle at 20% 20%, rgba(251,191,36,0.35), transparent 42%), radial-gradient(circle at 90% 80%, rgba(255,255,255,0.12), transparent 40%);"></div>
        <div class="relative">
            <div class="inline-flex items-center gap-3">
                <img src="{{ asset(brand('logo_url_light') ?: brand('logo_url') ?: 'images/brand/kopafasta-logo-light.svg') }}"
                     alt="{{ brand_name() }}"
                     class="h-10 w-auto object-contain">
            </div>
            <p class="mt-3 text-xs uppercase tracking-[0.2em] text-white/60">Staff console</p>
            <h1 class="mt-16 text-4xl font-bold leading-tight max-w-md">Operate loans, partners, and recoveries from one calm workspace.</h1>
            <p class="mt-4 text-sm text-white/70 max-w-sm">Secure access for admin, credit, collections, and operations teams.</p>
        </div>
        <p class="relative text-xs text-white/50">© {{ date('Y') }} Kopafasta Microfinance Ltd</p>
    </aside>

    <main class="relative flex items-center justify-center px-6 py-12 bg-[#F4F7F5]">
        <div class="absolute inset-0 opacity-60" style="background-image: linear-gradient(180deg, rgba(11,61,50,0.04), transparent 40%), radial-gradient(circle at 80% 10%, rgba(251,191,36,0.12), transparent 35%);"></div>
        <div class="relative w-full max-w-md rounded-3xl bg-white/95 p-8 shadow-[0_24px_80px_rgba(11,61,50,0.12)] ring-1 ring-[#0B3D32]/10">
            <div class="mb-8">
                <div class="lg:hidden mb-6">
                    <img src="{{ asset(brand('logo_url') ?: 'images/brand/kopafasta-logo.svg') }}"
                         alt="{{ brand_name() }}"
                         class="h-9 w-auto object-contain">
                </div>
                <h2 class="text-2xl font-bold tracking-tight text-gray-900">Welcome back</h2>
                <p class="mt-1 text-sm text-gray-500">Sign in with your staff account to continue</p>
            </div>

            <form method="POST" action="{{ route('admin.login') }}" class="space-y-4 form-scroll-lock">
                @csrf

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 mb-1.5">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus
                           class="block w-full rounded-xl border-0 ring-1 ring-gray-200 focus:ring-2 focus:ring-brand text-base px-3.5 py-2.5 bg-white">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 mb-1.5">Password</label>
                    <input type="password" name="password" required
                           class="block w-full rounded-xl border-0 ring-1 ring-gray-200 focus:ring-2 focus:ring-brand text-base px-3.5 py-2.5 bg-white">
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" name="remember" class="rounded border-gray-300 text-brand focus:ring-brand">
                    Remember me
                </label>

                @php $turnstile = app(\App\Services\TurnstileService::class); @endphp
                @if ($turnstile->enabled())
                    <div class="cf-turnstile" data-sitekey="{{ $turnstile->siteKey() }}" data-action="admin-login"></div>
                    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                    @error('cf-turnstile-response')
                        <p class="text-xs text-red-600">{{ $message }}</p>
                    @enderror
                @endif

                <button type="submit"
                        class="w-full bg-brand hover:bg-brand-light text-white font-semibold rounded-xl py-3 transition shadow-sm">
                    Sign in
                </button>
            </form>
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
                    title: 'Sign in failed',
                    message: @js($errors->first()),
                },
            }));
        });
    </script>
@endif
@vite('resources/js/alpine-init.js')
</body>
</html>
