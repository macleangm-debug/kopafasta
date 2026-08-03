<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-factor verification · Kopafasta</title>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full bg-gray-100 grid place-items-center antialiased">
<div class="w-full max-w-md p-8 bg-white rounded-2xl shadow-lg ring-1 ring-gray-200">
    <h1 class="text-lg font-semibold mb-2">Two-factor verification</h1>
    <p class="text-sm text-gray-500 mb-6">Enter the 6-digit code from your authenticator app, or a recovery code.</p>

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('auth.two-factor.verify') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="context" value="{{ $context }}">
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Authentication code</label>
            <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus
                   class="block w-full rounded-lg border-gray-300 text-sm px-3 py-2 border tracking-widest">
        </div>
        <p class="text-xs text-gray-500">Required on every sign-in. Recovery codes can be used if you lose your authenticator app.</p>
        <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-gray-900 font-semibold rounded-lg py-2.5">Verify</button>
    </form>
</div>
</body>
</html>
