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
    <p class="text-sm text-gray-500 mb-4">Scan this secret in Google Authenticator, 1Password, or any TOTP app.</p>

    <div class="rounded-lg bg-gray-50 p-3 mb-4">
        <p class="text-xs text-gray-500 uppercase mb-1">Secret key</p>
        <p class="font-mono text-sm break-all">{{ $secret }}</p>
        <p class="text-xs text-gray-500 mt-3 break-all">{{ $provisioning_uri }}</p>
    </div>

    <div class="mb-4">
        <p class="text-xs font-semibold text-gray-700 mb-2">Recovery codes (save these)</p>
        <ul class="text-xs font-mono grid grid-cols-2 gap-1">
            @foreach ($recovery_codes as $code)
                <li>{{ $code }}</li>
            @endforeach
        </ul>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('auth.two-factor.confirm-setup') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="context" value="{{ $context }}">
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Confirm with a code from your app</label>
            <input type="text" name="code" inputmode="numeric" required
                   class="block w-full rounded-lg border-gray-300 text-sm px-3 py-2 border">
        </div>
        <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-gray-900 font-semibold rounded-lg py-2.5">Enable 2FA</button>
    </form>
</div>
</body>
</html>
