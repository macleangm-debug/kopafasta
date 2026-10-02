@php
    $isSw = str_starts_with(app()->getLocale(), 'sw');
@endphp
<x-site.console-auth-shell
    title="{{ brand_title($isSw ? 'Ingia · Wafanyakazi' : 'Staff sign in') }}"
    :badge="$isSw ? 'Ufikiaji salama wa wafanyakazi' : 'Secure staff access'"
    :heading="$isSw ? 'Karibu tena' : 'Welcome back'"
    :support="$isSw ? 'Ingia salama ili uendelee kwenye nafasi yako ya kazi.' : 'Sign in securely to continue to your workspace.'"
    :aside-eyebrow="$isSw ? 'Nafasi ya wafanyakazi' : 'Staff workspace'"
    aside-title="Operate loans, partners, and recoveries from one calm workspace."
    aside-body="Secure access for credit, collections, and operations teams."
    :error-title="$isSw ? 'Kuingia kumeshindikana' : 'Sign in failed'"
>
    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-3 py-2 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('staff.login') }}" class="kf-auth-form form-scroll-lock">
        @csrf
        <div>
            <label class="kf-auth-label">{{ $isSw ? 'Barua pepe au simu' : 'Email or phone' }}</label>
            <input type="text" name="login" value="{{ old('login', old('email')) }}" required autofocus autocomplete="username"
                   class="kf-auth-input"
                   placeholder="staff@example.com or +255…">
            <p class="kf-auth-help">{{ $isSw ? 'Tumia barua pepe ikiwa imewekwa. Simu inafanya kazi wakati akaunti haina barua pepe.' : 'Use email when set. Phone works when the account has no email.' }}</p>
        </div>
        <x-site.password-field
            name="password"
            :label="$isSw ? 'Nenosiri' : 'Password'"
            autocomplete="current-password"
            :show-label="$isSw ? 'Onyesha nenosiri' : 'Show password'"
            :hide-label="$isSw ? 'Ficha nenosiri' : 'Hide password'"
        />
        <label class="flex items-center gap-2 text-sm text-gray-600">
            <input type="checkbox" name="remember" class="rounded border-gray-300 text-brand focus:ring-brand">
            {{ $isSw ? 'Nikumbuke' : 'Remember me' }}
        </label>
        <x-site.turnstile action="staff-login" inline />
        <button type="submit"
                data-loading-label="{{ $isSw ? 'Inaingia…' : 'Signing in…' }}"
                class="kf-auth-btn">
            {{ $isSw ? 'Ingia' : 'Sign in' }}
        </button>
    </form>

    <p class="mt-5 text-xs text-gray-500 text-center">
        {{ $isSw ? 'Watumiaji wa koni ya admin wanaweza pia' : 'Full admin console users can also use' }}
        <a href="{{ route('admin.login') }}" class="text-brand font-semibold hover:underline">{{ $isSw ? 'kuingia kama admin' : 'admin sign in' }}</a>.
    </p>
</x-site.console-auth-shell>
