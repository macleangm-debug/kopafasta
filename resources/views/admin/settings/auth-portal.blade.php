<x-admin.layout title="Authentication" heading="Authentication" subheading="Two-factor enforcement for admin, staff, and partner web login">
    @include('admin.settings._tabs', ['active' => 'auth-portal'])

    <x-admin.settings-editor
        action="{{ route('admin.settings.auth-portal.save') }}"
        submit-label="Save authentication settings"
        :tabs="[
            'twofactor' => 'Two-factor',
            'methods' => 'Staff methods',
            'session' => 'Session',
            'pin' => 'PIN reset',
            'turnstile' => 'Turnstile',
        ]"
    >
        <x-admin.settings-panel id="twofactor">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-1">Require second-step verification</h3>
                <p class="text-xs text-gray-500 mb-4">
                    When enabled, users must enroll a verification method on first sign-in, then complete it on <strong>every new login</strong>.
                    Staff may use authenticator or security questions (see Staff methods). Privileged Admin keeps authenticator as the stronger option.
                </p>

                <div class="space-y-3">
                    <label class="flex items-start gap-3 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-3">
                        <input type="hidden" name="require_2fa_admin" value="0">
                        <input type="checkbox" name="require_2fa_admin" value="1"
                               @checked(! empty($values['require_2fa_admin']))
                               class="mt-0.5 size-4 rounded border-gray-300 text-brand focus:ring-brand">
                        <span>
                            <span class="font-medium text-gray-900">Admin console</span>
                            <span class="block text-gray-500 text-xs mt-0.5">Applies to <code class="text-xs">/admin/login</code> and full back-office users.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-3">
                        <input type="hidden" name="require_2fa_staff" value="0">
                        <input type="checkbox" name="require_2fa_staff" value="1"
                               @checked(! empty($values['require_2fa_staff']))
                               class="mt-0.5 size-4 rounded border-gray-300 text-brand focus:ring-brand">
                        <span>
                            <span class="font-medium text-gray-900">Staff workspace</span>
                            <span class="block text-gray-500 text-xs mt-0.5">Applies to <code class="text-xs">/staff/login</code>.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-3">
                        <input type="hidden" name="require_2fa_partner" value="0">
                        <input type="checkbox" name="require_2fa_partner" value="1"
                               @checked(! empty($values['require_2fa_partner']))
                               class="mt-0.5 size-4 rounded border-gray-300 text-brand focus:ring-brand">
                        <span>
                            <span class="font-medium text-gray-900">Partner portal</span>
                            <span class="block text-gray-500 text-xs mt-0.5">Partner email/password sign-in only — Borrower/Member PIN is unchanged.</span>
                        </span>
                    </label>
                </div>
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="methods">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-5">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 mb-1">Staff verification methods</h3>
                    <p class="text-xs text-gray-500">
                        Ordinary Staff may enroll one of the enabled methods. Do not disable authentication entirely.
                    </p>
                </div>
                <div class="space-y-3">
                    <label class="flex items-start gap-3 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-3">
                        <input type="hidden" name="staff_allow_authenticator" value="0">
                        <input type="checkbox" name="staff_allow_authenticator" value="1"
                               @checked(! empty($values['staff_allow_authenticator']))
                               class="mt-0.5 size-4 rounded border-gray-300 text-brand focus:ring-brand">
                        <span>
                            <span class="font-medium text-gray-900">Authenticator app</span>
                            <span class="block text-gray-500 text-xs mt-0.5">TOTP (Google Authenticator, Authy, etc.).</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-3">
                        <input type="hidden" name="staff_allow_security_questions" value="0">
                        <input type="checkbox" name="staff_allow_security_questions" value="1"
                               @checked(! empty($values['staff_allow_security_questions']))
                               class="mt-0.5 size-4 rounded border-gray-300 text-brand focus:ring-brand">
                        <span>
                            <span class="font-medium text-gray-900">Security questions</span>
                            <span class="block text-gray-500 text-xs mt-0.5">Reuses the existing Kopafasta question bank. Enroll 3; challenge 1 per login.</span>
                        </span>
                    </label>
                </div>

                <div class="pt-2 border-t border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-900 mb-1">Privileged Admin minimum</h3>
                    <p class="text-xs text-gray-500 mb-3">
                        Administrator / Super Administrator should not silently fall to weaker verification.
                    </p>
                    <label class="flex items-start gap-3 text-sm bg-amber-50 ring-1 ring-amber-200 rounded-lg px-3 py-3">
                        <input type="hidden" name="privileged_require_authenticator" value="0">
                        <input type="checkbox" name="privileged_require_authenticator" value="1"
                               @checked(! empty($values['privileged_require_authenticator']))
                               class="mt-0.5 size-4 rounded border-gray-300 text-brand focus:ring-brand">
                        <span>
                            <span class="font-medium text-gray-900">Require authenticator for privileged Admin</span>
                            <span class="block text-gray-500 text-xs mt-0.5">Keeps authenticator MFA available as the stronger option for Admin / Super Admin.</span>
                        </span>
                    </label>
                </div>
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="session">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-1">Session trust window</h3>
                <p class="text-xs text-gray-500 mb-4">
                    After a successful 2FA verification, this browser session stays verified for this many hours (no re-prompt while still signed in).
                    Logging out always requires a fresh 2FA code on the next sign-in.
                </p>
                <div class="max-w-xs">
                    <x-admin.input name="two_factor_session_hours" label="Hours" type="number" min="1" max="168"
                                   :value="$values['two_factor_session_hours'] ?? 12" required />
                </div>
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="pin">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-1">PIN reset challenge timer</h3>
                <p class="text-xs text-gray-500 mb-4">
                    How long a member has to answer security questions (and set a new PIN) after starting Forgot PIN.
                    A countdown shows on the form. Recommended: <strong>90 seconds</strong> (1.5 minutes) — enough for three short answers without leaving the session open too long.
                </p>
                <div class="max-w-xs">
                    <x-admin.input name="pin_recovery_session_seconds" label="Seconds" type="number" min="30" max="900"
                                   :value="$values['pin_recovery_session_seconds'] ?? 90" required />
                    <p class="mt-1.5 text-[11px] text-gray-500">Min 30 · Max 900 (15 minutes)</p>
                </div>
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="turnstile">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-1">Cloudflare Turnstile (bot protection)</h3>
                <p class="text-xs text-gray-500 mb-4">
                    Optional. When both keys are set, every public login and registration form requires a Cloudflare Turnstile challenge: borrower, partner, investor, capital partner, staff, and admin. Partner activation and PIN recovery also require it.
                    Create a widget at Cloudflare → Turnstile, then paste the site and secret keys here.
                </p>
                <div class="grid sm:grid-cols-2 gap-4">
                    <x-admin.input name="turnstile_site_key" label="Site key" :value="$values['turnstile_site_key'] ?? ''" />
                    <x-admin.input name="turnstile_secret_key" label="Secret key" :value="$values['turnstile_secret_key'] ?? ''" />
                </div>
            </div>
        </x-admin.settings-panel>
    </x-admin.settings-editor>
</x-admin.layout>
