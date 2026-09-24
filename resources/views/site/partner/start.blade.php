@php
    $partner = $partner ?? null;
    $isSupplier = (bool) $partner?->isSupplier();
    $heading = $isSupplier
        ? __('site.auth.partner_activate_heading_supplier')
        : __('site.auth.partner_activate_heading');
    $badge = $isSupplier
        ? __('site.auth.partner_activate_badge_supplier')
        : __('site.auth.partner_activate_badge');
    $keepTypedPhone = $errors->has('phone');
@endphp
<x-site.layout :title="brand_title(__('site.auth.partner_activate_brand'))">
    <section class="min-h-[calc(100dvh-4rem)] grid lg:grid-cols-2 premium-gradient">
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
            </div>
            <p class="relative text-xs text-white/50">&copy; {{ date('Y') }} {{ brand('legal_name') }}</p>
        </aside>

        <div class="flex items-center justify-center px-4 py-6 sm:px-12 sm:py-10">
            <div class="w-full max-w-md glass-card overflow-hidden">
                <div class="lg:hidden px-5 pt-5">
                    <a href="{{ route('site.home') }}" class="inline-block"><x-site.brand-mark size="md" /></a>
                </div>

                <div class="kf-premium-panel mx-4 mt-4 mb-0 rounded-2xl px-5 py-5 sm:mx-5 sm:px-6 sm:py-6">
                    <div class="relative">
                        <span class="inline-flex items-center rounded-full bg-brand-gold/15 text-brand-gold ring-1 ring-brand-gold/40 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-[0.18em]">{{ $badge }}</span>
                        <h1 class="mt-3 text-xl sm:text-2xl font-bold tracking-tight leading-tight">{{ $heading }}</h1>
                        @if ($partner?->name)
                            <p class="mt-2 text-base font-semibold text-white">{{ $partner->name }}</p>
                        @endif
                        <p class="mt-2 text-sm text-white/80 leading-relaxed">{{ __('site.auth.partner_activate_support') }}</p>
                    </div>
                </div>

                <div class="px-5 pt-5 pb-6 sm:px-6 sm:pb-7">
                    @if ($errors->any())
                        <div class="mb-4 p-3 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700">{{ $errors->first() }}</div>
                    @endif

                    <form method="POST" action="{{ route('site.partner.start.lookup') }}"
                          class="space-y-4" autocomplete="off" data-no-draft
                          x-data
                          x-init="
                            const keep = {{ $keepTypedPhone ? 'true' : 'false' }};
                            const wipe = () => {
                                if (keep) return;
                                $root.querySelectorAll('[data-phone-local]').forEach((el) => { el.value = ''; el.dispatchEvent(new Event('input')); });
                                $root.querySelectorAll('[data-phone-hidden]').forEach((el) => { el.value = ''; el.setAttribute('value', ''); });
                            };
                            wipe();
                            $nextTick(wipe);
                            window.addEventListener('pageshow', wipe);
                          ">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('site.auth.partner_account_number') }}</label>
                            @if ($partner?->partner_number)
                                <input type="text" name="partner_code" value="{{ $partner->partner_number }}" readonly
                                       autocomplete="off" tabindex="-1"
                                       class="w-full px-3 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-sm font-mono uppercase outline-none text-gray-700">
                            @else
                                <input type="text" name="partner_code" value="{{ old('partner_code', request('partner_code')) }}" required
                                       autocomplete="off" autocapitalize="characters" spellcheck="false"
                                       placeholder="PT-XX-TZ-XXXX"
                                       class="w-full px-3 py-2.5 rounded-xl bg-white border border-gray-200 focus:border-brand focus:ring-2 focus:ring-brand/10 text-sm font-mono uppercase outline-none">
                            @endif
                        </div>
                        <x-site.phone-input
                            name="phone"
                            :label="__('site.auth.partner_phone_label')"
                            :value="$keepTypedPhone ? old('phone') : null"
                            :help="false"
                            :lock-empty="! $keepTypedPhone"
                            :placeholder="__('site.auth.partner_phone_placeholder')"
                            variant="rounded"
                            :required="true"
                        />
                        @if (! empty($maskedPhone) && ! $errors->has('phone'))
                            <p class="text-xs text-gray-500 -mt-2">{{ __('site.auth.partner_phone_ends_in', ['last' => $maskedPhone]) }}</p>
                        @endif
                        <x-site.turnstile action="partner-activate" />
                        <button type="submit" class="w-full bg-brand hover:bg-brand-light text-white font-bold py-3.5 rounded-xl transition shadow-md"
                                data-loading-label="{{ __('site.auth.activating') }}">
                            {{ __('site.auth.partner_verify_continue') }}
                        </button>
                    </form>

                    <div class="mt-5 pt-4 border-t border-gray-100 text-center text-sm text-gray-500">
                        {{ __('site.auth.already_activated') }}
                        <a href="{{ route('site.login.partner') }}" class="text-brand font-semibold hover:underline">{{ __('site.auth.sign_in') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-site.layout>
