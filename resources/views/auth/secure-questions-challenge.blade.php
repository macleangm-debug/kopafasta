@php
    $isSw = str_starts_with(app()->getLocale(), 'sw');
@endphp
<x-site.console-auth-shell
    title="{{ brand_title($isSw ? 'Thibitisha ni wewe' : 'Verify it’s you') }}"
    :badge="$isSw ? 'Kila kuingia' : 'Every sign-in'"
    :heading="$isSw ? 'Thibitisha ni wewe' : 'Verify it’s you'"
    :support="$isSw ? 'Jibu swali lako la usalama ili uendelee.' : 'Answer your security question to continue.'"
    :aside-eyebrow="$isSw ? 'Kila kuingia' : 'Every sign-in'"
    aside-title="Confirm it’s you before opening your workspace."
    aside-body="Answer the security question enrolled on your account. Failed attempts are rate-limited."
    :error-title="$isSw ? 'Uthibitishaji umeshindikana' : 'Verification failed'"
>
    <p class="text-sm font-medium text-gray-800 leading-relaxed">{{ $question['prompt'] }}</p>

    <form method="POST" action="{{ route('auth.secure.questions.challenge.verify') }}" class="kf-auth-form mt-4">
        @csrf
        <input type="hidden" name="context" value="{{ $context }}">
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label class="kf-auth-label">{{ $isSw ? 'Jibu lako' : 'Your answer' }}</label>
            <input type="text" name="answer" required autofocus autocomplete="off" maxlength="120"
                   class="kf-auth-input"
                   @if (($question['input'] ?? '') === 'digits') inputmode="numeric" pattern="[0-9]*" @endif>
        </div>
        <button type="submit"
                data-loading-label="{{ $isSw ? 'Inathibitisha…' : 'Verifying…' }}"
                class="kf-auth-btn-gold">
            {{ $isSw ? 'Thibitisha' : 'Verify' }}
        </button>
    </form>
</x-site.console-auth-shell>
