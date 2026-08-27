@props(['title' => 'Staff workspace'])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} · Kopafasta</title>
    <link rel="icon" href="{{ asset(ltrim((string) brand('logo_mark_url', 'images/brand/kopafasta-mark.png'), '/')) }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset(ltrim((string) brand('logo_mark_url', 'images/brand/kopafasta-mark.png'), '/')) }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="bg-slate-100 text-gray-900 antialiased min-h-screen" x-data>
    <header class="bg-slate-900 text-white">
        <div class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between gap-4">
            <div>
                <p class="font-semibold">Kopafasta Staff</p>
                <p class="text-xs text-slate-400">{{ auth('admin')->user()?->name }}</p>
            </div>
            <nav class="flex flex-wrap gap-2 text-sm">
                <a href="{{ route('staff.dashboard') }}" class="px-3 py-1.5 rounded-full {{ request()->routeIs('staff.dashboard') ? 'bg-amber-500 text-gray-900' : 'bg-slate-800' }}">Dashboard</a>
                <a href="{{ route('admin.settings.account-security') }}" class="px-3 py-1.5 rounded-full bg-slate-800">Account security</a>
                <form method="POST" action="{{ route('staff.logout') }}">
                    @csrf
                    <button type="submit" class="px-3 py-1.5 rounded-full bg-slate-800">Sign out</button>
                </form>
            </nav>
        </div>
    </header>
    <main class="max-w-5xl mx-auto px-4 py-8">
        {{ $slot }}
    </main>
    <x-site.confirm-modal name="staff" />
    <script>
        window.confirmForm = (form, detail = {}) => {
            const tone = detail.tone
                || (String(detail.confirmClass || '').includes('red') ? 'warning' : 'confirm');
            window.dispatchEvent(new CustomEvent('open-confirm-staff', {
                detail: { form: form || null, tone, ...detail },
            }));
        };
        window.confirmAction = (detail = {}) => window.confirmForm(null, detail);
    </script>
    @vite('resources/js/alpine-init.js')
</body>
</html>
