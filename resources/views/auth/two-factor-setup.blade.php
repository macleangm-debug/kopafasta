{{-- Set up two-factor with scannable QR. Expects: $secret, $provisioning_uri, $recovery_codes, $context --}}
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set up two-factor · Kopafasta</title>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full bg-gray-100 grid place-items-center antialiased">
<div class="w-full max-w-lg p-8 bg-white rounded-2xl shadow-lg ring-1 ring-gray-200">
    <h1 class="text-lg font-semibold mb-2">Set up two-factor authentication</h1>
    <p class="text-sm text-gray-500 mb-4">Scan this QR with Google Authenticator, Authy, or 1Password. This is not a website link — it only pairs your authenticator app.</p>

    <div class="flex flex-col sm:flex-row gap-4 items-center mb-4">
        <div class="shrink-0 rounded-xl bg-white ring-1 ring-gray-200 p-3">
            <img
                src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&ecc=M&data={{ urlencode($provisioning_uri) }}"
                width="180"
                height="180"
                alt="Two-factor QR code"
                class="block rounded-lg"
            >
        </div>
        <div class="min-w-0 flex-1 w-full">
            <p class="text-xs text-gray-500 uppercase mb-1">Or enter this secret key</p>
            <p class="font-mono text-sm break-all bg-gray-50 rounded-lg px-3 py-2 ring-1 ring-gray-200">{{ $secret }}</p>
            <p class="text-[11px] text-gray-500 mt-2">Account: your email · Issuer: {{ config('app.name', 'Kopafasta') }}</p>
        </div>
    </div>

    <div class="mb-4 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3">
        <p class="text-xs font-semibold text-amber-900 mb-2">Recovery codes — save these now (shown once)</p>
        <ul class="text-xs font-mono grid grid-cols-2 gap-1 text-amber-950">
            @foreach ($recovery_codes as $code)
                <li>{{ $code }}</li>
            @endforeach
        </ul>
        <p class="text-[11px] text-amber-800 mt-2">Store them offline. Each code works once if you lose your phone.</p>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('auth.two-factor.confirm-setup') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="context" value="{{ $context }}">
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Confirm with a 6-digit code from your app</label>
            <input type="text" name="code" inputmode="numeric" required autocomplete="one-time-code"
                   class="block w-full rounded-lg border-gray-300 text-sm px-3 py-2 border tracking-widest">
        </div>
        <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-gray-900 font-semibold rounded-lg py-2.5">Enable 2FA</button>
    </form>
</div>
</body>
</html>
