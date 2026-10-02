@php
    $isSw = str_starts_with(app()->getLocale(), 'sw');
@endphp
<x-site.console-auth-shell
    title="{{ brand_title($isSw ? 'Weka usalama wako' : 'Set up your security') }}"
    :badge="$isSw ? 'Usalama wa akaunti' : 'Secure your account'"
    :heading="$isSw ? 'Weka usalama wako' : 'Set up your security'"
    :support="$isSw ? 'Chagua jinsi utakavyothibitisha ni wewe katika kila kuingia kipya.' : 'Pick how you will verify it is you on each new sign-in.'"
    :aside-eyebrow="$isSw ? 'Ufikiaji salama' : 'Secure access'"
    aside-title="Protect every staff sign-in with a second verification step."
    aside-body="Choose authenticator app or security questions according to your organisation’s policy."
    :error-title="$isSw ? 'Imeshindikana kuendelea' : 'Could not continue'"
>
    <div class="space-y-3">
        @if (in_array(\App\Services\ConsoleSecondFactorService::METHOD_AUTHENTICATOR, $methods, true))
            <a href="{{ route('auth.two-factor.setup', ['context' => $context]) }}"
               class="block rounded-2xl ring-1 ring-brand/15 bg-white hover:bg-brand-muted/30 hover:ring-brand/30 px-4 py-4 transition shadow-sm">
                <p class="text-sm font-bold text-gray-900">{{ $isSw ? 'Programu ya authenticator' : 'Authenticator app' }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $isSw ? 'Changanua QR na weka msimbo wa tarakimu 6 kila unapoingia. Ulindaji thabiti zaidi.' : 'Scan a QR code and enter a 6-digit code each login. Stronger protection.' }}</p>
            </a>
        @endif
        @if (in_array(\App\Services\ConsoleSecondFactorService::METHOD_SECURITY_QUESTIONS, $methods, true))
            <a href="{{ route('auth.secure.questions.setup', ['context' => $context]) }}"
               class="block rounded-2xl ring-1 ring-brand/15 bg-white hover:bg-brand-muted/30 hover:ring-brand/30 px-4 py-4 transition shadow-sm">
                <p class="text-sm font-bold text-gray-900">{{ $isSw ? 'Maswali ya usalama' : 'Security questions' }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $isSw ? 'Chagua maswali na majibu. Swali moja linaulizwa kila kuingia.' : 'Choose questions and answers. One question is asked each login.' }}</p>
            </a>
        @endif
    </div>
</x-site.console-auth-shell>
