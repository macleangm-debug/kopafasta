<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in · Kopafasta Console</title>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full bg-gray-100 grid place-items-center antialiased">

<div class="w-full max-w-md p-8 bg-white rounded-2xl shadow-lg ring-1 ring-gray-200">
    <div class="flex items-center gap-3 mb-6">
        <div class="size-11 rounded-xl bg-amber-500 grid place-items-center font-bold text-gray-900 text-lg">K</div>
        <div>
            <div class="text-base font-semibold">Kopafasta Console</div>
            <div class="text-xs text-gray-500">Sign in to continue</div>
        </div>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.login') }}" class="space-y-4">
        @csrf

        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="block w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500 text-sm px-3 py-2 border">
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Password</label>
            <input type="password" name="password" required
                   class="block w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500 text-sm px-3 py-2 border">
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-600">
            <input type="checkbox" name="remember" class="rounded border-gray-300 text-amber-500 focus:ring-amber-500">
            Remember me
        </label>

        <button type="submit"
                class="w-full bg-amber-500 hover:bg-amber-600 text-gray-900 font-semibold rounded-lg py-2.5 transition">
            Sign in
        </button>
    </form>
</div>

</body>
</html>
