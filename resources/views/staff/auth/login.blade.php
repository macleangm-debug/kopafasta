<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff sign in · Kopafasta</title>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full bg-slate-900 grid place-items-center antialiased text-white">
<div class="w-full max-w-md p-8 bg-slate-800 rounded-2xl shadow-xl ring-1 ring-slate-700">
    <div class="mb-6">
        <div class="text-lg font-semibold">Staff workspace</div>
        <div class="text-sm text-slate-400">Sign in with your staff account</div>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-950 border border-red-800 px-4 py-3 text-sm text-red-200">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('staff.login') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-xs font-medium text-slate-300 mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="block w-full rounded-lg border-slate-600 bg-slate-900 text-white text-sm px-3 py-2 border">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-300 mb-1">Password</label>
            <input type="password" name="password" required
                   class="block w-full rounded-lg border-slate-600 bg-slate-900 text-white text-sm px-3 py-2 border">
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-400">
            <input type="checkbox" name="remember" class="rounded border-slate-600 bg-slate-900 text-amber-500">
            Remember me
        </label>
        <button type="submit" class="w-full bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold rounded-lg py-2.5">Sign in</button>
    </form>

    <p class="mt-6 text-xs text-slate-500 text-center">
        Full admin console users can also use <a href="{{ route('admin.login') }}" class="text-amber-400 underline">admin sign in</a>.
    </p>
</div>
</body>
</html>
