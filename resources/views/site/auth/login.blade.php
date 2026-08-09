@php
    $authMethod = old('auth_method', $defaultMethod ?? 'pin');
    $prefillPhone = old('phone', $prefillPhone ?? null);
@endphp
{{-- Premium login — borrower / partner portal --}}
<x-site.layout :auth="true" :title="brand_title(__('site.auth.sign_in'))">
    <section class="min-h-full grid lg:grid-cols-2 premium-gradient">
        <aside class="hidden lg:flex relative overflow-hidden bg-brand text-white p-12 flex-col justify-between">
            <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_bottom_left,_#f5c842,_transparent_50%)]"></div>
            <a href="{{ route('site.home') }}" class="relative"><x-site.brand-mark variant="light" /></a>
            <div class="relative">
                @if ($partnerPortal ?? false)
                    <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold mb-2">{{ __('site.auth.partner_portal') }}</p>
                    <h2 class="text-4xl font-bold tracking-tight leading-tight">{{ __('site.auth.partner_sign_in') }}</h2>
                    <p class="mt-4 text-white/70 max-w-md">{{ __('site.auth.partner_types') }}</p>
                @else
                    <h2 class="text-4xl font-bold tracking-tight leading-tight">{{ __('site.auth.sign_in_title') }}</h2>
                    <p class="mt-4 text-white/70 max-w-md">{{ brand('tagline') }}. {{ __('site.auth.sign_in_subtitle') }}</p>
                @endif
            </div>
            <p class="relative text-xs text-white/50">&copy; {{ date('Y') }} {{ brand('legal_name') }}</p>
        </aside>

        <div class="flex items-center justify-center px-4 py-8 sm:px-12">
            <div id="login-method-switcher" class="w-full max-w-md glass-card p-6 sm:p-10" data-method="{{ $authMethod }}">
                <a href="{{ route('site.home') }}" class="lg:hidden mb-8 inline-block">
                    <x-site.brand-mark size="md" />
                </a>

                <h1 class="text-3xl font-bold tracking-tight text-gray-900">{{ ($partnerPortal ?? false) ? __('site.auth.partner_sign_in') : __('site.auth.welcome_back') }}</h1>
                <p class="mt-2 text-sm text-gray-600">
                    @if ($partnerPortal ?? false)
                        {{ __('site.auth.new_here') }}
                        <a href="{{ route('site.partner.start') }}" class="text-brand font-semibold hover:underline">{{ __('site.auth.activate_account') }}</a>
                    @else
                        {{ __('site.auth.new_here') }}
                        <a href="{{ route('site.register') }}" class="text-brand font-semibold hover:underline">{{ __('site.auth.create_account') }}</a>
                    @endif
                </p>

                @if (session('status'))
                    <div class="mt-6 p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-sm text-emerald-700">{{ session('status') }}</div>
                @endif

                <div class="mt-6 inline-flex rounded-xl ring-1 ring-gray-200/80 bg-gray-50/80 p-1 text-sm w-full" role="tablist" aria-label="{{ __('site.auth.sign_in') }}">
                    <button type="button" data-set-method="pin" role="tab"
                            aria-selected="{{ $authMethod === 'pin' ? 'true' : 'false' }}"
                            class="login-method-tab flex-1 rounded-lg py-2.5 transition {{ $authMethod === 'pin' ? 'bg-white text-brand shadow-sm font-semibold' : 'text-gray-600 hover:bg-white/50' }}">{{ __('site.auth.phone_pin') }}</button>
                    <button type="button" data-set-method="password" role="tab"
                            aria-selected="{{ $authMethod === 'password' ? 'true' : 'false' }}"
                            class="login-method-tab flex-1 rounded-lg py-2.5 transition {{ $authMethod === 'password' ? 'bg-white text-brand shadow-sm font-semibold' : 'text-gray-600 hover:bg-white/50' }}">{{ __('site.auth.email_password') }}</button>
                </div>

                <form method="POST" action="{{ route('site.login.post') }}" class="mt-6 space-y-5 form-scroll-lock">
                    @csrf
                    <input type="hidden" name="auth_method" id="login-auth-method" value="{{ $authMethod }}">

                    <div data-method-panel="pin" @class(['hidden' => $authMethod !== 'pin'])>
                        <x-site.phone-input name="phone" label="{{ __('site.feedback.phone') }}" :value="$prefillPhone" variant="rounded" :required="$authMethod === 'pin'" required-when="pin" :show-errors="false" />
                        <div class="mt-4">
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-sm font-medium text-gray-700">{{ __('site.auth.pin_label') }}</label>
                                <a href="{{ route('site.forgot-pin', array_filter(['phone' => $prefillPhone])) }}" class="text-xs text-brand font-medium hover:underline">{{ __('site.auth.forgot_pin') }}</a>
                            </div>
                            <input type="password" name="pin" inputmode="numeric" maxlength="4" pattern="\d{4}" autocomplete="off"
                                   placeholder="••••"
                                   data-required-when="pin"
                                   @required($authMethod === 'pin')
                                   class="w-full px-3 py-3 rounded-xl bg-white border border-gray-200 focus:border-brand focus:ring-2 focus:ring-brand/10 tracking-[0.5em] font-mono text-center text-base outline-none">
                        </div>
                    </div>

                    <div data-method-panel="password" @class(['hidden' => $authMethod !== 'password'])>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Email or phone</label>
                            <input type="text" name="login" value="{{ old('login') }}" autocomplete="username"
                                   placeholder="you@example.com or phone"
                                   data-required-when="password"
                                   @required($authMethod === 'password')
                                   class="w-full px-3 py-3 rounded-xl bg-white border border-gray-200 focus:border-brand focus:ring-2 focus:ring-brand/10 text-base outline-none">
                        </div>
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                            <input type="password" name="password" autocomplete="current-password"
                                   data-required-when="password"
                                   @required($authMethod === 'password')
                                   class="w-full px-3 py-3 rounded-xl bg-white border border-gray-200 focus:border-brand focus:ring-2 focus:ring-brand/10 text-base outline-none">
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" name="trust_device" value="1" class="rounded border-gray-300 text-brand focus:ring-brand">
                        {{ __('site.auth.trust_device') }}
                    </label>

                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" name="remember" value="1" class="rounded border-gray-300 text-brand focus:ring-brand">
                        {{ __('site.auth.remember_me') }}
                    </label>

                    <x-site.turnstile action="login" :show-errors="false" />

                    <button class="w-full bg-brand hover:bg-brand-light text-white font-bold py-3.5 rounded-xl transition shadow-md">
                        {{ __('site.auth.sign_in') }}
                    </button>
                </form>

                @if ($partnerPortal ?? false)
                    <div class="mt-6 pt-6 border-t border-gray-100 text-center text-sm text-gray-500">
                        <a href="{{ route('site.affiliate') }}" class="text-brand font-semibold hover:underline">{{ __('site.nav.affiliate') }}</a>
                        ·
                        <a href="{{ route('site.partners') }}" class="text-brand font-semibold hover:underline">{{ __('site.partners.title') }}</a>
                    </div>
                    <div class="mt-4 text-center text-sm text-gray-500">
                        {{ __('site.auth.use_borrower_login') }}
                        <a href="{{ route('site.login') }}" class="text-brand font-semibold hover:underline">{{ __('site.auth.borrower_login_link') }}</a>
                    </div>
                @else
                    <div class="mt-6 pt-6 border-t border-gray-100 text-center text-sm text-gray-500">
                        {{ __('site.auth.use_partner_login') }}
                        <a href="{{ route('site.login.partner') }}" class="text-brand font-semibold hover:underline">{{ __('site.auth.partner_login_link') }}</a>
                    </div>
                @endif

                <div class="mt-6 pt-6 border-t border-gray-100 text-center text-xs text-gray-500">
                    {{ __('site.auth.staff_login') }}
                    <a href="{{ route('admin.login') }}" class="text-gray-700 font-semibold hover:underline">{{ __('site.auth.admin_console') }} →</a>
                </div>
            </div>
        </div>
    </section>

    <script>
        (function () {
            const root = document.getElementById('login-method-switcher');
            if (!root) return;

            const hiddenInput = document.getElementById('login-auth-method');
            const panels = root.querySelectorAll('[data-method-panel]');
            const tabs = root.querySelectorAll('[data-set-method]');
            const requiredFields = root.querySelectorAll('[data-required-when]');
            const activeTabClasses = ['bg-white', 'text-brand', 'shadow-sm', 'font-semibold'];
            const inactiveTabClasses = ['text-gray-600', 'hover:bg-white/50'];

            function setMethod(method) {
                root.dataset.method = method;
                if (hiddenInput) hiddenInput.value = method;

                panels.forEach((panel) => {
                    panel.classList.toggle('hidden', panel.dataset.methodPanel !== method);
                });

                tabs.forEach((tab) => {
                    const isActive = tab.dataset.setMethod === method;
                    tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
                    activeTabClasses.forEach((cls) => tab.classList.toggle(cls, isActive));
                    inactiveTabClasses.forEach((cls) => tab.classList.toggle(cls, !isActive));
                });

                requiredFields.forEach((field) => {
                    field.required = field.dataset.requiredWhen === method;
                });

                const phoneLocal = root.querySelector('[data-method-panel="pin"] [data-phone-local]');
                if (phoneLocal) {
                    phoneLocal.required = method === 'pin';
                }
            }

            tabs.forEach((tab) => {
                tab.addEventListener('click', () => setMethod(tab.dataset.setMethod));
            });

            setMethod(root.dataset.method || 'pin');
        })();
    </script>
    @if ($errors->any())
        <script>
            document.addEventListener('alpine:initialized', () => {
                window.dispatchEvent(new CustomEvent('open-feedback-default', {
                    detail: {
                        tone: 'error',
                        title: @js(__('site.auth.sign_in_failed_title')),
                        message: @js($errors->first()),
                    },
                }));
            });
        </script>
    @endif
</x-site.layout>
