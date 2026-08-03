<x-admin.layout title="Authentication" heading="Authentication" subheading="Two-factor enforcement for admin, staff, and partner web login">
    @include('admin.settings._tabs', ['active' => 'auth-portal'])

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.settings.auth-portal.save') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-1">Two-factor authentication (TOTP)</h3>
            <p class="text-xs text-gray-500 mb-4">
                When enabled, users must enroll an authenticator app on first sign-in, then enter a code (or recovery code) on <strong>every new login</strong>.
                Trusted devices cannot skip 2FA. Within an active session, the code is not re-asked until logout or the session trust window expires.
                Each person manages their own authenticator under <a href="{{ route('admin.settings.account-security') }}" class="text-amber-700 underline">Account security</a>.
            </p>

            <div class="space-y-3">
                <label class="flex items-start gap-3 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-3">
                    <input type="hidden" name="require_2fa_admin" value="0">
                    <input type="checkbox" name="require_2fa_admin" value="1"
                           @checked(! empty($values['require_2fa_admin']))
                           class="mt-0.5 size-4 rounded border-gray-300 text-brand focus:ring-brand">
                    <span>
                        <span class="font-medium text-gray-900">Admin console</span>
                        <span class="block text-gray-500 text-xs mt-0.5">Applies to <code class="text-xs">/admin/login</code> and full back-office users (officer, manager, admin).</span>
                    </span>
                </label>

                <label class="flex items-start gap-3 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-3">
                    <input type="hidden" name="require_2fa_staff" value="0">
                    <input type="checkbox" name="require_2fa_staff" value="1"
                           @checked(! empty($values['require_2fa_staff']))
                           class="mt-0.5 size-4 rounded border-gray-300 text-brand focus:ring-brand">
                    <span>
                        <span class="font-medium text-gray-900">Staff workspace</span>
                        <span class="block text-gray-500 text-xs mt-0.5">Applies to <code class="text-xs">/staff/login</code> and limited-permission staff deep-linking into admin routes.</span>
                    </span>
                </label>

                <label class="flex items-start gap-3 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-3">
                    <input type="hidden" name="require_2fa_partner" value="0">
                    <input type="checkbox" name="require_2fa_partner" value="1"
                           @checked(! empty($values['require_2fa_partner']))
                           class="mt-0.5 size-4 rounded border-gray-300 text-brand focus:ring-brand">
                    <span>
                        <span class="font-medium text-gray-900">Partner portal</span>
                        <span class="block text-gray-500 text-xs mt-0.5">Applies to partner email/password sign-in at <code class="text-xs">/login</code> and authenticated partner routes.</span>
                    </span>
                </label>
            </div>
        </div>

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

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-1">Cloudflare Turnstile (bot protection)</h3>
            <p class="text-xs text-gray-500 mb-4">
                Optional. When both keys are set, public login/register and admin login require a Turnstile challenge.
                Create a widget at Cloudflare → Turnstile, then paste the site and secret keys here.
            </p>
            <div class="grid sm:grid-cols-2 gap-4">
                <x-admin.input name="turnstile_site_key" label="Site key" :value="$values['turnstile_site_key'] ?? ''" />
                <x-admin.input name="turnstile_secret_key" label="Secret key" :value="$values['turnstile_secret_key'] ?? ''" />
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-5 py-2 rounded-lg shadow-sm">
                Save authentication settings
            </button>
        </div>
    </form>
</x-admin.layout>
