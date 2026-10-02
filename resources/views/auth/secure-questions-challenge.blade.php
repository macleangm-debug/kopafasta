<x-site.console-auth-shell
    title="{{ brand_title('Verify it’s you') }}"
    aside-eyebrow="Every sign-in"
    aside-title="Confirm it’s you before opening your workspace."
    aside-body="Answer the security question enrolled on your account. Failed attempts are rate-limited."
    error-title="Verification failed"
>
    <h2 class="text-2xl font-bold tracking-tight text-gray-900">Verify it’s you</h2>
    <p class="mt-2 text-sm text-gray-500">{{ $question['prompt'] }}</p>

    <form method="POST" action="{{ route('auth.secure.questions.challenge.verify') }}" class="mt-6 space-y-5">
        @csrf
        <input type="hidden" name="context" value="{{ $context }}">
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 mb-1.5">Your answer</label>
            <input type="text" name="answer" required autofocus autocomplete="off" maxlength="120"
                   class="block w-full rounded-xl border-0 ring-1 ring-gray-200 focus:ring-2 focus:ring-brand text-base px-3.5 py-2.5 bg-white"
                   @if (($question['input'] ?? '') === 'digits') inputmode="numeric" pattern="[0-9]*" @endif>
        </div>
        <button type="submit" data-loading-label="Verifying…"
                class="w-full bg-brand-gold hover:bg-yellow-400 text-brand font-bold rounded-xl py-3 shadow-sm transition">
            Verify
        </button>
    </form>
</x-site.console-auth-shell>
