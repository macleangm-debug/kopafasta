<x-site.layout :title="brand_title(__('site.auth.partner_recovery_setup_title'))">
    <section class="min-h-[calc(100dvh-4rem)] md:min-h-[calc(100dvh-6.5rem)] grid lg:grid-cols-2 premium-gradient">
        <aside class="hidden lg:flex relative overflow-hidden bg-brand text-white p-12 flex-col justify-between">
            <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_bottom_left,_#f5c842,_transparent_50%)]"></div>
            <a href="{{ route('site.home') }}" class="relative"><x-site.brand-mark variant="light" /></a>
            <div class="relative">
                <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.auth.partner_portal') }}</p>
                <h2 class="mt-2 text-4xl font-bold tracking-tight leading-tight">{{ __('site.auth.partner_recovery_setup_title') }}</h2>
                <p class="mt-4 text-white/70 max-w-md">{{ __('site.auth.partner_recovery_setup_body') }}</p>
            </div>
            <p class="relative text-xs text-white/50">© {{ date('Y') }} {{ brand_name() }}</p>
        </aside>
        <div class="flex items-center justify-center px-4 py-10 sm:px-12">
            <div class="w-full max-w-md">
                <div class="glass-card p-8 sm:p-10">
                    @if (session('status'))
                        <div class="mb-5 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
                    @endif
                    <h1 class="text-2xl font-bold text-gray-900">{{ __('site.auth.partner_recovery_setup_title') }}</h1>
                    <p class="mt-1 text-sm text-gray-600">{{ __('site.auth.partner_recovery_setup_body') }}</p>
                    <form method="POST" action="{{ route('site.partner.setup-recovery.post') }}" class="mt-6 space-y-4" autocomplete="off" data-no-draft>
                        @csrf
                        @include('site.partner._recovery-questions', ['questions' => $questions])
                        <p class="text-xs text-gray-500">{{ __('site.auth.pin_recovery.setup_hint') }}</p>
                        <button class="w-full bg-brand-gold hover:bg-yellow-400 text-brand font-bold py-3.5 rounded-xl text-sm shadow-sm"
                                data-loading-label="{{ __('site.auth.partner_recovery_setup_cta') }}…">
                            {{ __('site.auth.partner_recovery_setup_cta') }}
                        </button>
                    </form>
                    <p class="mt-4 text-center text-xs text-gray-500">
                        <a href="{{ route('site.partner.dashboard') }}" class="font-semibold text-brand hover:underline">{{ __('site.auth.pin_recovery.back_login') }}</a>
                    </p>
                </div>
            </div>
        </div>
    </section>
</x-site.layout>
