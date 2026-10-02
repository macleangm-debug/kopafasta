<x-site.console-auth-shell
    title="{{ brand_title('Sign in · Console') }}"
    aside-eyebrow="Staff console"
    aside-title="Operate loans, partners, and recoveries from one calm workspace."
    aside-body="Secure access for admin, credit, collections, and operations teams."
    error-title="Sign in failed"
>
    <h2 class="text-2xl font-bold tracking-tight text-gray-900">Welcome back</h2>
    <p class="mt-1 text-sm text-gray-500">Sign in with your staff account to continue</p>

    <form method="POST" action="{{ route('admin.login') }}" class="mt-6 space-y-4 form-scroll-lock" autocomplete="off">
        @csrf
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 mb-1.5">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="block w-full rounded-xl border-0 ring-1 ring-gray-200 focus:ring-2 focus:ring-brand text-base px-3.5 py-2.5 bg-white">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 mb-1.5">Password</label>
            <input type="password" name="password" required autocomplete="current-password"
                   class="block w-full rounded-xl border-0 ring-1 ring-gray-200 focus:ring-2 focus:ring-brand text-base px-3.5 py-2.5 bg-white">
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-600">
            <input type="checkbox" name="remember" class="rounded border-gray-300 text-brand focus:ring-brand">
            Remember me
        </label>
        <x-site.turnstile action="admin-login" inline />
        <button type="submit"
                class="w-full bg-brand hover:bg-brand-light text-white font-semibold rounded-xl py-3 transition shadow-sm">
            Sign in
        </button>
    </form>
</x-site.console-auth-shell>
