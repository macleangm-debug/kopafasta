<x-site.console-auth-shell
    title="{{ brand_title('Staff sign in') }}"
    aside-eyebrow="Staff workspace"
    aside-title="Operate loans, partners, and recoveries from one calm workspace."
    aside-body="Secure access for credit, collections, and operations teams."
    error-title="Sign in failed"
>
    <h2 class="text-2xl font-bold tracking-tight text-gray-900">Staff sign in</h2>
    <p class="mt-1 text-sm text-gray-500">Sign in with your staff account to continue</p>

    @if (session('status'))
        <div class="mt-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-3 py-2 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('staff.login') }}" class="mt-6 space-y-4 form-scroll-lock">
        @csrf
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 mb-1.5">Email or phone</label>
            <input type="text" name="login" value="{{ old('login', old('email')) }}" required autofocus autocomplete="username"
                   class="block w-full rounded-xl border-0 ring-1 ring-gray-200 focus:ring-2 focus:ring-brand text-base px-3.5 py-2.5 bg-white"
                   placeholder="staff@example.com or +255…">
            <p class="mt-1 text-[11px] text-gray-500">Use email when set. Phone works when the account has no email.</p>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 mb-1.5">Password</label>
            <input type="password" name="password" required
                   class="block w-full rounded-xl border-0 ring-1 ring-gray-200 focus:ring-2 focus:ring-brand text-base px-3.5 py-2.5 bg-white">
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-600">
            <input type="checkbox" name="remember" class="rounded border-gray-300 text-brand focus:ring-brand">
            Remember me
        </label>
        <x-site.turnstile action="staff-login" inline />
        <button type="submit"
                class="w-full bg-brand hover:bg-brand-light text-white font-semibold rounded-xl py-3 transition shadow-sm">
            Sign in
        </button>
    </form>

    <p class="mt-6 text-xs text-gray-500 text-center">
        Full admin console users can also use <a href="{{ route('admin.login') }}" class="text-brand underline">admin sign in</a>.
    </p>
</x-site.console-auth-shell>
