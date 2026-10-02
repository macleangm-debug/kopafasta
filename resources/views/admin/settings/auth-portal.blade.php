<x-admin.layout title="Authentication" heading="Authentication" subheading="Second-step verification, Staff methods, and session security">
    @include('admin.settings._tabs', ['active' => 'auth-portal'])

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-950">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-rose-50 ring-1 ring-rose-200 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>
    @endif

    @php
        $selectedAuthRoles = old('authenticator_required_roles', $values['authenticator_required_roles'] ?? ['admin', 'super_admin']);
        $staffRoleOptions = $staffRoleOptions ?? [];
        $questionBank = $questionBank ?? [];
    @endphp

    <x-admin.settings-editor
        action="{{ route('admin.settings.auth-portal.save') }}"
        submit-label="Save verification settings"
        :tabs="[
            'verification' => 'Verification',
            'questions' => 'Security questions',
            'session' => 'Session',
            'pin' => 'PIN reset',
            'turnstile' => 'Turnstile',
        ]"
        default-tab="verification"
    >
        <x-admin.settings-panel id="verification">
            <div class="space-y-6">
                <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-1">A. Who requires second-step verification?</h3>
                    <p class="text-xs text-gray-500 mb-4">
                        These portals decide <strong>whether</strong> a second step is required — not which method is allowed.
                        When enabled, users enroll on first access, then verify on every new login.
                    </p>
                    <div class="space-y-3">
                        <label class="flex items-start gap-3 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-3">
                            <input type="hidden" name="require_2fa_admin" value="0">
                            <input type="checkbox" name="require_2fa_admin" value="1"
                                   @checked(! empty($values['require_2fa_admin']))
                                   class="mt-0.5 size-4 rounded border-gray-300 text-brand focus:ring-brand">
                            <span>
                                <span class="font-medium text-gray-900">Admin console</span>
                                <span class="block text-gray-500 text-xs mt-0.5"><code class="text-xs">/admin/login</code></span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-3">
                            <input type="hidden" name="require_2fa_staff" value="0">
                            <input type="checkbox" name="require_2fa_staff" value="1"
                                   @checked(! empty($values['require_2fa_staff']))
                                   class="mt-0.5 size-4 rounded border-gray-300 text-brand focus:ring-brand">
                            <span>
                                <span class="font-medium text-gray-900">Staff workspace</span>
                                <span class="block text-gray-500 text-xs mt-0.5"><code class="text-xs">/staff/login</code> and Staff activation links</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-3">
                            <input type="hidden" name="require_2fa_partner" value="0">
                            <input type="checkbox" name="require_2fa_partner" value="1"
                                   @checked(! empty($values['require_2fa_partner']))
                                   class="mt-0.5 size-4 rounded border-gray-300 text-brand focus:ring-brand">
                            <span>
                                <span class="font-medium text-gray-900">Partner portal</span>
                                <span class="block text-gray-500 text-xs mt-0.5">Partner email/password only — Borrower PIN unchanged</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-1">B. Staff verification methods</h3>
                    <p class="text-xs text-gray-500 mb-4">
                        When Staff workspace verification is enabled, ordinary Staff may use the methods below.
                        Do not save with zero methods while Staff verification is required.
                    </p>
                    <div class="space-y-3">
                        <label class="flex items-start gap-3 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-3">
                            <input type="hidden" name="staff_allow_authenticator" value="0">
                            <input type="checkbox" name="staff_allow_authenticator" value="1"
                                   @checked(! empty($values['staff_allow_authenticator']))
                                   class="mt-0.5 size-4 rounded border-gray-300 text-brand focus:ring-brand">
                            <span>
                                <span class="font-medium text-gray-900">Authenticator app</span>
                                <span class="block text-gray-500 text-xs mt-0.5">TOTP / two-factor authentication (Google Authenticator, Authy, etc.)</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-3">
                            <input type="hidden" name="staff_allow_security_questions" value="0">
                            <input type="checkbox" name="staff_allow_security_questions" value="1"
                                   @checked(! empty($values['staff_allow_security_questions']))
                                   class="mt-0.5 size-4 rounded border-gray-300 text-brand focus:ring-brand">
                            <span>
                                <span class="font-medium text-gray-900">Security questions</span>
                                <span class="block text-gray-500 text-xs mt-0.5">Knowledge questions — enroll several; challenge one per login</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-1">C. Roles requiring authenticator</h3>
                    <p class="text-xs text-gray-500 mb-4">
                        Selected Staff roles must use the authenticator app — they cannot fall back to security questions.
                        Defaults protect Administrator and Super Administrator.
                    </p>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
                        @foreach ($staffRoleOptions as $code => $label)
                            <label class="flex items-center gap-2 text-sm bg-amber-50/60 ring-1 ring-amber-100 rounded-lg px-3 py-2">
                                <input type="checkbox" name="authenticator_required_roles[]" value="{{ $code }}"
                                       @checked(in_array($code, $selectedAuthRoles, true))
                                       class="size-4 rounded border-gray-300 text-brand focus:ring-brand">
                                <span class="font-medium text-gray-900">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="questions">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-5">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 mb-1">Security question bank</h3>
                    <p class="text-xs text-gray-500">
                        Reuses the existing KBA engine. Answers stay hashed. Login always challenges <strong>one</strong> enrolled question.
                    </p>
                </div>
                <div class="max-w-xs">
                    <x-admin.input name="security_questions_enroll_count" label="Questions to enroll" type="number" min="2" max="5"
                                   :value="old('security_questions_enroll_count', $values['security_questions_enroll_count'] ?? 3)" required />
                    <p class="mt-1.5 text-[11px] text-gray-500">Default 3 · Min 2 · Max 5</p>
                </div>
                <div class="overflow-x-auto rounded-xl ring-1 ring-gray-100">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-3 py-2.5">Active</th>
                                <th class="px-3 py-2.5">English</th>
                                <th class="px-3 py-2.5">Kiswahili</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($questionBank as $row)
                                <tr>
                                    <td class="px-3 py-2.5 align-top">
                                        <input type="hidden" name="question_bank[{{ $row['key'] }}][active]" value="0">
                                        <input type="checkbox" name="question_bank[{{ $row['key'] }}][active]" value="1"
                                               @checked(! empty($row['active']))
                                               class="size-4 rounded border-gray-300 text-brand focus:ring-brand">
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <input type="text" name="question_bank[{{ $row['key'] }}][prompt_en]"
                                               value="{{ old('question_bank.'.$row['key'].'.prompt_en', $row['prompt_en']) }}"
                                               class="w-full rounded-lg border-gray-200 text-sm">
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <input type="text" name="question_bank[{{ $row['key'] }}][prompt_sw]"
                                               value="{{ old('question_bank.'.$row['key'].'.prompt_sw', $row['prompt_sw']) }}"
                                               class="w-full rounded-lg border-gray-200 text-sm">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </x-admin.settings-panel>

        <x-admin.settings-panel id="session">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-1">Session trust window</h3>
                <p class="text-xs text-gray-500 mb-4">
                    After a successful second-step verification, this browser session stays verified for this many hours.
                    Logging out always requires a fresh verification on the next sign-in.
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
                    Recommended: <strong>90 seconds</strong>.
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
                    Optional. When both keys are set, public login and registration forms require Turnstile.
                </p>
                <div class="grid sm:grid-cols-2 gap-4">
                    <x-admin.input name="turnstile_site_key" label="Site key" :value="$values['turnstile_site_key'] ?? ''" />
                    <x-admin.input name="turnstile_secret_key" label="Secret key" :value="$values['turnstile_secret_key'] ?? ''" />
                </div>
            </div>
        </x-admin.settings-panel>
    </x-admin.settings-editor>
</x-admin.layout>
