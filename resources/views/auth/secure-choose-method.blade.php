<x-site.console-auth-shell
    title="{{ brand_title('Set up your security') }}"
    aside-eyebrow="Secure access"
    aside-title="Protect every staff sign-in with a second verification step."
    aside-body="Choose authenticator app or security questions according to your organisation’s policy."
    error-title="Could not continue"
>
    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">One-time setup</p>
    <h2 class="mt-1 text-2xl font-bold tracking-tight text-gray-900">Set up your security</h2>
    <p class="mt-2 text-sm text-gray-500">Pick how you will verify it’s you on each new sign-in.</p>

    <div class="mt-6 space-y-3">
        @if (in_array(\App\Services\ConsoleSecondFactorService::METHOD_AUTHENTICATOR, $methods, true))
            <a href="{{ route('auth.two-factor.setup', ['context' => $context]) }}"
               class="block rounded-2xl ring-1 ring-brand/15 bg-white hover:ring-brand/30 px-4 py-4 transition">
                <p class="text-sm font-bold text-gray-900">Authenticator app</p>
                <p class="text-xs text-gray-500 mt-1">Scan a QR code and enter a 6-digit code each login. Stronger protection.</p>
            </a>
        @endif
        @if (in_array(\App\Services\ConsoleSecondFactorService::METHOD_SECURITY_QUESTIONS, $methods, true))
            <a href="{{ route('auth.secure.questions.setup', ['context' => $context]) }}"
               class="block rounded-2xl ring-1 ring-brand/15 bg-white hover:ring-brand/30 px-4 py-4 transition">
                <p class="text-sm font-bold text-gray-900">Security questions</p>
                <p class="text-xs text-gray-500 mt-1">Choose three questions and answers. One question is asked each login.</p>
            </a>
        @endif
    </div>
</x-site.console-auth-shell>
