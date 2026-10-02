@php
    $isSw = str_starts_with(app()->getLocale(), 'sw');
@endphp
<x-site.console-auth-shell
    title="{{ brand_title($isSw ? 'Ingia · Koni' : 'Sign in · Console') }}"
    :badge="$isSw ? 'Ufikiaji salama wa wafanyakazi' : 'Secure staff access'"
    :heading="$isSw ? 'Karibu tena' : 'Welcome back'"
    :support="$isSw ? 'Ingia salama ili uendelee kwenye nafasi yako ya kazi.' : 'Sign in securely to continue to your workspace.'"
    :aside-eyebrow="$isSw ? 'Koni ya wafanyakazi' : 'Staff console'"
    aside-title="Operate loans, partners, and recoveries from one calm workspace."
    aside-body="Secure access for admin, credit, collections, and operations teams."
    :error-title="$isSw ? 'Kuingia kumeshindikana' : 'Sign in failed'"
>
    <form method="POST" action="{{ route('admin.login') }}" class="kf-auth-form form-scroll-lock" autocomplete="off">
        @csrf
        <div>
            <label class="kf-auth-label">{{ $isSw ? 'Barua pepe' : 'Email' }}</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="kf-auth-input">
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
        <x-site.turnstile action="admin-login" inline />
        <button type="submit"
                data-loading-label="{{ $isSw ? 'Inaingia…' : 'Signing in…' }}"
                class="kf-auth-btn">
            {{ $isSw ? 'Ingia' : 'Sign in' }}
        </button>
    </form>
</x-site.console-auth-shell>
