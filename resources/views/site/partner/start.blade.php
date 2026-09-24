@php
    $partner = $partner ?? null;
    $isSupplier = (bool) $partner?->isSupplier();
    $heading = $isSupplier
        ? __('site.auth.partner_activate_heading_supplier')
        : __('site.auth.partner_activate_heading');
    $badge = $isSupplier
        ? __('site.auth.partner_activate_badge_supplier')
        : __('site.auth.partner_activate_badge');
@endphp
<x-site.layout :title="brand_title(__('site.auth.partner_activate_brand'))">
    <section class="min-h-[calc(100vh-4rem)] grid lg:grid-cols-2 premium-gradient">
        <aside class="hidden lg:flex relative overflow-hidden bg-brand text-white p-10 xl:p-12 flex-col justify-between">
            <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_bottom_left,_#f5c842,_transparent_50%)]"></div>
            <a href="{{ route('site.home') }}" class="relative"><x-site.brand-mark variant="light" /></a>
            <div class="relative">
                <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.auth.partner_activate_brand') }}</p>
                <h2 class="mt-3 text-3xl xl:text-4xl font-bold tracking-tight leading-tight">{{ $heading }}</h2>
                @if ($partner?->name)
                    <p class="mt-4 text-lg font-semibold text-white">{{ $partner->name }}</p>
                @endif
                <p class="mt-4 text-white/70 max-w-md text-sm leading-relaxed">{{ __('site.auth.partner_activate_support') }}</p>
                <p class="mt-6 text-xs text-white/50">{{ __('site.auth.partner_activate_trust') }}</p>
            </div>
            <p class="relative text-xs text-white/50">&copy; {{ date('Y') }} {{ brand('legal_name') }}</p>
        </aside>

        <div class="flex items-center justify-center px-4 py-10 sm:px-12">
            <div class="w-full max-w-md glass-card p-7 sm:p-9">
                <a href="{{ route('site.home') }}" class="lg:hidden mb-6 inline-block">
                    <x-site.brand-mark size="md" />
                </a>

                <p class="lg:hidden text-[11px] uppercase tracking-widest text-brand font-semibold">{{ __('site.auth.partner_activate_brand') }}</p>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center rounded-full bg-brand/8 text-brand ring-1 ring-brand/15 px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide">{{ $badge }}</span>
                    <span class="text-[11px] font-medium text-gray-400">{{ __('site.auth.partner_activate_step_phone') }}</span>
                </div>

                <h1 class="mt-3 text-2xl font-bold tracking-tight text-gray-900">{{ $heading }}</h1>
                @if ($partner?->name)
                    <p class="mt-1.5 text-base font-semibold text-gray-900">{{ $partner->name }}</p>
                @endif
                <p class="mt-2 text-sm text-gray-600">{{ __('site.auth.partner_activate_support') }}</p>

                @if ($errors->any())
                    <div class="mt-5 p-3.5 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('site.partner.start.lookup') }}" class="mt-6 space-y-4" autocomplete="off">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('site.auth.partner_account_number') }}</label>
                        <input type="text" name="partner_code" value="{{ old('partner_code', request('partner_code')) }}" required
                               autocomplete="off" autocapitalize="characters" spellcheck="false"
                               placeholder="PT-XX-TZ-XXXX"
                               class="w-full px-3 py-2.5 rounded-xl bg-gray-50 border border-gray-200 focus:bg-white focus:border-brand focus:ring-2 focus:ring-brand/10 text-sm font-mono uppercase outline-none">
                    </div>
                    <x-site.phone-input name="phone" :label="__('site.auth.partner_phone_label')" :value="old('phone')" variant="rounded" :required="true" />
                    @if (! empty($maskedPhone) && ! $errors->has('phone'))
                        <p class="text-xs text-gray-500 -mt-2">{{ __('site.auth.partner_phone_ends_in', ['last' => $maskedPhone]) }}</p>
                    @endif
                    <x-site.turnstile action="partner-activate" />
                    <button type="submit" class="w-full bg-brand hover:bg-brand-light text-white font-bold py-3.5 rounded-xl transition shadow-md"
                            data-loading-label="{{ __('site.auth.activating') }}">
                        {{ __('site.auth.continue_activation') }}
                    </button>
                </form>

                <div class="mt-6 pt-5 border-t border-gray-100 text-center text-sm text-gray-500">
                    {{ __('site.auth.already_activated') }}
                    <a href="{{ route('site.login.partner') }}" class="text-brand font-semibold hover:underline">{{ __('site.auth.sign_in') }}</a>
                </div>
            </div>
        </div>
    </section>
</x-site.layout>
