@php
    $isSw = str_starts_with(app()->getLocale(), 'sw');
    $hello = $firstName
        ? ($isSw ? 'Habari, '.$firstName : 'Hello, '.$firstName)
        : ($isSw ? 'Habari' : 'Hello');
@endphp
<x-site.console-auth-shell
    title="{{ brand_title($isSw ? 'Chagua nenosiri' : 'Choose your password') }}"
    :badge="$isSw ? 'Uanzishaji wa wafanyakazi' : 'Staff activation'"
    :heading="$hello"
    :support="$isSw ? 'Unda nenosiri lako ili uamilishe akaunti yako ya Wafanyakazi.' : 'Create your password to activate your Staff account.'"
    :aside-eyebrow="$isSw ? 'Uanzishaji wa wafanyakazi' : 'Staff activation'"
    :aside-title="$firstName ? ($isSw ? 'Habari, '.$firstName.'.' : 'Hello, '.$firstName.'.').' '.($isSw ? 'Chagua nenosiri lako.' : 'Choose your password.') : ($isSw ? 'Chagua nenosiri lako.' : 'Choose your password.')"
    :aside-body="$isSw ? 'Unda nenosiri la akaunti yako ya Kopafasta. Ifuatayo, tutaweka uthibitishaji wa usalama.' : 'Create a password for your Kopafasta Staff account. Next, we will set up your security verification.'"
    :error-title="$isSw ? 'Imeshindikana kuhifadhi nenosiri' : 'Could not save password'"
>
    <form method="POST" action="{{ route('staff.password-setup.store') }}" class="kf-auth-form">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="uid" value="{{ $uid }}">
        <input type="hidden" name="email" value="{{ $email }}">
        <x-site.password-field
            name="password"
            :label="$isSw ? 'Nenosiri jipya' : 'New password'"
            autocomplete="new-password"
            :minlength="8"
            :show-label="$isSw ? 'Onyesha nenosiri' : 'Show password'"
            :hide-label="$isSw ? 'Ficha nenosiri' : 'Hide password'"
        />
        <x-site.password-field
            name="password_confirmation"
            :label="$isSw ? 'Thibitisha nenosiri' : 'Confirm password'"
            autocomplete="new-password"
            :minlength="8"
            :show-label="$isSw ? 'Onyesha nenosiri' : 'Show password'"
            :hide-label="$isSw ? 'Ficha nenosiri' : 'Hide password'"
        />
        <button type="submit"
                data-loading-label="{{ $isSw ? 'Inahifadhi nenosiri…' : 'Saving password…' }}"
                class="kf-auth-btn">
            {{ $isSw ? 'Hifadhi nenosiri' : 'Save password' }}
        </button>
    </form>
</x-site.console-auth-shell>
