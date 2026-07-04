<x-site.layout :title="brand_title(__('site.auth.partner_sign_in'))">
    <section class="min-h-[calc(100vh-4rem)] grid lg:grid-cols-2 premium-gradient">
        <aside class="hidden lg:flex relative overflow-hidden bg-brand text-white p-12 flex-col justify-between">
            <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_bottom_left,_#f5c842,_transparent_50%)]"></div>
            <a href="{{ route('site.home') }}" class="relative"><x-site.brand-mark variant="light" /></a>
            <div class="relative">
                <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold mb-2">{{ __('site.auth.partner_portal') }}</p>
                <h2 class="text-4xl font-bold tracking-tight leading-tight">{{ __('site.auth.activate_account') }}</h2>
                <p class="mt-4 text-white/70 max-w-md">{{ __('site.auth.partner_activate_subtitle') }}</p>
            </div>
            <p class="relative text-xs text-white/50">&copy; {{ date('Y') }} {{ brand('legal_name') }}</p>
        </aside>

        <div class="flex items-center justify-center px-4 py-12 sm:px-12">
            <div class="w-full max-w-md glass-card p-8 sm:p-10">
                <a href="{{ route('site.home') }}" class="lg:hidden mb-8 inline-block">
                    <x-site.brand-mark size="md" />
                </a>

                <h1 class="text-3xl font-bold tracking-tight text-gray-900">{{ __('site.auth.activate_account') }}</h1>
                <p class="mt-2 text-sm text-gray-600">{{ __('site.auth.partner_activate_form_hint') }}</p>

                @if ($errors->any())
                    <div class="mt-6 p-3.5 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('site.partner.start.lookup') }}" class="mt-6 space-y-5">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('site.auth.partner_code_label') }}</label>
                        <input type="text" name="partner_code" value="{{ old('partner_code') }}" required
                               class="w-full px-3 py-3 rounded-xl bg-white border border-gray-200 focus:border-brand focus:ring-2 focus:ring-brand/10 text-sm font-mono uppercase outline-none">
                    </div>
                    <x-site.phone-input name="phone" :label="__('site.feedback.phone')" :value="old('phone')" variant="rounded" :required="true" />
                    <button type="submit" class="w-full bg-brand hover:bg-brand-light text-white font-bold py-3.5 rounded-xl transition shadow-md">
                        {{ __('site.auth.continue_activation') }}
                    </button>
                </form>

                <div class="mt-6 pt-6 border-t border-gray-100 text-center text-sm text-gray-500">
                    {{ __('site.auth.already_activated') }}
                    <a href="{{ route('site.login', ['portal' => 'partner']) }}" class="text-brand font-semibold hover:underline">{{ __('site.auth.sign_in') }}</a>
                </div>
            </div>
        </div>
    </section>
</x-site.layout>
