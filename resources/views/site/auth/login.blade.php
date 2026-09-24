@php
    $authMethod = old('auth_method', request('auth_method', ($partnerPortal ?? false) ? ($defaultMethod ?? 'password') : 'pin'));
    $prefillPhone = old('phone', $prefillPhone ?? request('phone'));
    $isPartnerPortal = (bool) ($partnerPortal ?? false);
    $finishRegistration = (bool) request('finish_registration') || session()->has('login_inline');
    $prefillLogin = old('login', request('login', $prefillPhone));
    $badge = $isPartnerPortal ? __('site.auth.shell.partner') : __('site.auth.shell.member');
    $heading = $isPartnerPortal ? __('site.auth.partner_sign_in') : __('site.auth.welcome_back');
    $support = $isPartnerPortal ? __('site.auth.shell.partner_login_support') : __('site.auth.shell.login_support');
@endphp
<x-site.auth-shell
    :title="brand_title(__('site.auth.sign_in'))"
    :badge="$badge"
    :heading="$heading"
    :support="$support"
    :aside-eyebrow="$isPartnerPortal ? __('site.auth.partner_portal') : __('site.auth.shell.member')"
    :aside-title="$heading"
    :aside-body="$support"
    id="login-method-switcher"
    data-method="{{ $authMethod }}"
    x-data="{ partnerOpen: false }"
>
    @if (session('status'))
        <div class="mb-4 p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if (session('login_inline'))
        <div class="mb-4 p-3 rounded-xl bg-brand/5 ring-1 ring-brand/15 text-sm text-brand font-medium">{{ session('login_inline') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 p-3 rounded-xl bg-rose-50 ring-1 ring-rose-200 text-sm text-rose-700 space-y-1">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @unless ($isPartnerPortal)
        @if ($finishRegistration || $authMethod === 'password')
            <form method="POST" action="{{ route('site.login.post') }}" class="kf-auth-form">
                @csrf
                <input type="hidden" name="auth_method" value="password">
                <div>
                    <label class="kf-auth-label">{{ __('site.auth.email_or_phone') }}</label>
                    <input type="text" name="login" value="{{ $prefillLogin }}" autocomplete="username"
                           placeholder="{{ __('site.auth.email_or_phone_placeholder') }}"
                           required class="kf-auth-input">
                </div>
                <div>
                    <label class="kf-auth-label">{{ __('site.auth.password') }}</label>
                    <input type="password" name="password" autocomplete="current-password" required class="kf-auth-input">
                </div>
                <x-site.turnstile action="login" />
                <button class="kf-auth-btn">{{ __('site.auth.sign_in') }}</button>
            </form>
        @else
            <form method="POST" action="{{ route('site.login.post') }}" class="kf-auth-form">
                @csrf
                <input type="hidden" name="auth_method" value="pin">
                <x-site.phone-input name="phone" :label="__('site.auth.partner_phone_label')" :value="$prefillPhone" variant="rounded" :required="true" :show-errors="false" />
                <x-site.auth-pin name="pin" :forgot-href="route('site.forgot-pin', array_filter(['phone' => $prefillPhone]))" />
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" name="trust_device" value="1" class="rounded border-gray-300 text-brand focus:ring-brand">
                    {{ __('site.auth.trust_device', ['days' => app(\App\Services\TrustedDeviceService::class)->ttlDays()]) }}
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" name="remember" value="1" class="rounded border-gray-300 text-brand focus:ring-brand">
                    {{ __('site.auth.remember_me') }}
                </label>
                <x-site.turnstile action="login" />
                <button class="kf-auth-btn">{{ __('site.auth.sign_in') }}</button>
            </form>
        @endif

        <div class="mt-5 pt-4 border-t border-gray-100 text-center text-sm text-gray-500">
            {{ __('site.auth.new_here') }}
            <a href="{{ route('site.register') }}" class="text-brand font-semibold hover:underline">{{ __('site.auth.create_account') }}</a>
        </div>
        <button type="button" @click="partnerOpen = true" class="mt-3 w-full text-center text-sm font-semibold text-brand hover:underline">
            {{ __('site.auth.partner_login_cta') }}
        </button>

        <x-site.action-panel open="partnerOpen" :title="__('site.auth.partner_sign_in')" size="md">
            <form method="POST" action="{{ route('site.login.post') }}" class="kf-auth-form">
                @csrf
                <input type="hidden" name="auth_method" value="password">
                <div>
                    <label class="kf-auth-label">{{ __('site.auth.email_or_phone') }}</label>
                    <input type="text" name="login" value="{{ old('login') }}" autocomplete="username"
                           placeholder="{{ __('site.auth.email_or_phone_placeholder') }}"
                           required class="kf-auth-input">
                </div>
                <div>
                    <div class="flex items-center justify-between gap-3 mb-1">
                        <label class="kf-auth-label mb-0">{{ __('site.auth.password') }}</label>
                        <a href="{{ route('site.forgot-pin') }}" class="text-xs text-brand font-medium hover:underline">{{ __('site.auth.forgot_password') }}</a>
                    </div>
                    <input type="password" name="password" autocomplete="current-password" required class="kf-auth-input">
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" name="trust_device" value="1" class="rounded border-gray-300 text-brand focus:ring-brand">
                    {{ __('site.auth.trust_device', ['days' => app(\App\Services\TrustedDeviceService::class)->ttlDays()]) }}
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" name="remember" value="1" class="rounded border-gray-300 text-brand focus:ring-brand">
                    {{ __('site.auth.remember_me') }}
                </label>
                <x-site.turnstile action="login" />
                <button class="kf-auth-btn">{{ __('site.auth.sign_in') }}</button>
            </form>
            <p class="mt-4 text-center text-xs text-gray-500">
                <a href="{{ route('site.partner.start') }}" class="text-brand font-semibold hover:underline">{{ __('site.auth.activate_account') }}</a>
            </p>
        </x-site.action-panel>
    @else
        <form method="POST" action="{{ route('site.login.post') }}" class="kf-auth-form">
            @csrf
            <input type="hidden" name="auth_method" id="login-auth-method" value="{{ $authMethod }}">

            <div data-method-panel="password" @class(['hidden' => $authMethod !== 'password'])>
                <div>
                    <label class="kf-auth-label">{{ __('site.auth.email_or_phone') }}</label>
                    <input type="text" name="login" value="{{ old('login') }}" autocomplete="username"
                           placeholder="{{ __('site.auth.email_or_phone_placeholder') }}"
                           data-required-when="password"
                           @required($authMethod === 'password')
                           class="kf-auth-input">
                </div>
                <div class="mt-3.5">
                    <div class="flex items-center justify-between gap-3 mb-1">
                        <label class="kf-auth-label mb-0">{{ __('site.auth.password') }}</label>
                        <a href="{{ route('site.forgot-pin', array_filter(['phone' => $prefillPhone])) }}" class="text-xs text-brand font-medium hover:underline">{{ __('site.auth.forgot_password') }}</a>
                    </div>
                    <input type="password" name="password" autocomplete="current-password"
                           data-required-when="password"
                           @required($authMethod === 'password')
                           class="kf-auth-input">
                </div>
            </div>

            <div data-method-panel="pin" @class(['hidden' => $authMethod !== 'pin'])>
                <x-site.phone-input name="phone" :label="__('site.auth.partner_phone_label')" :value="$prefillPhone" variant="rounded" :required="$authMethod === 'pin'" required-when="pin" :show-errors="false" />
                <div class="mt-3.5">
                    <x-site.auth-pin name="pin" :required="$authMethod === 'pin'" data-required-when="pin" :forgot-href="route('site.partner.forgot-pin', array_filter(['phone' => $prefillPhone]))" />
                </div>
            </div>

            <div class="flex rounded-xl ring-1 ring-gray-200/80 bg-gray-50/80 p-1 text-sm w-full" role="tablist">
                <button type="button" data-set-method="password" role="tab"
                        aria-selected="{{ $authMethod === 'password' ? 'true' : 'false' }}"
                        class="login-method-tab flex-1 rounded-lg py-2.5 px-2 text-sm text-center transition {{ $authMethod === 'password' ? 'bg-white text-brand shadow-sm font-semibold' : 'text-gray-600 hover:bg-white/50' }}">{{ __('site.auth.email_password') }}</button>
                <button type="button" data-set-method="pin" role="tab"
                        aria-selected="{{ $authMethod === 'pin' ? 'true' : 'false' }}"
                        class="login-method-tab flex-1 rounded-lg py-2.5 px-2 text-sm text-center transition {{ $authMethod === 'pin' ? 'bg-white text-brand shadow-sm font-semibold' : 'text-gray-600 hover:bg-white/50' }}">{{ __('site.auth.phone_pin') }}</button>
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" name="trust_device" value="1" class="rounded border-gray-300 text-brand focus:ring-brand">
                {{ __('site.auth.trust_device', ['days' => app(\App\Services\TrustedDeviceService::class)->ttlDays()]) }}
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" name="remember" value="1" class="rounded border-gray-300 text-brand focus:ring-brand">
                {{ __('site.auth.remember_me') }}
            </label>
            <x-site.turnstile action="login" />
            <button class="kf-auth-btn">{{ __('site.auth.sign_in') }}</button>
        </form>

        <div class="mt-5 pt-4 border-t border-gray-100 text-center text-sm text-gray-500 space-y-2">
            {{ __('site.auth.new_here') }}
            <a href="{{ route('site.partner.start') }}" class="text-brand font-semibold hover:underline">{{ __('site.auth.activate_account') }}</a>
            <div>
                <a href="{{ route('site.login') }}" class="text-brand font-semibold hover:underline">{{ __('site.auth.borrower_login_link') }} →</a>
            </div>
        </div>
    @endunless
</x-site.auth-shell>

@if ($isPartnerPortal)
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

        setMethod(root.dataset.method || 'password');
    })();
</script>
@endif
