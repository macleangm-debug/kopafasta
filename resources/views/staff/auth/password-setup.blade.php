<x-site.console-auth-shell
    title="{{ brand_title('Choose your password') }}"
    aside-eyebrow="Staff activation"
    aside-title="Choose a password, then set up security verification."
    aside-body="This link is single-use and expires soon. Next you will enroll authenticator or security questions."
    error-title="Could not save password"
>
    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Kopafasta Staff</p>
    <h2 class="mt-1 text-2xl font-bold tracking-tight text-gray-900">Choose your password</h2>
    <p class="mt-2 text-sm text-gray-500">Hi {{ $name }}. This link is single-use and expires soon.</p>

    <form method="POST" action="{{ route('staff.password-setup.store') }}" class="mt-6 space-y-4" x-data="{ show: false, show2: false }">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="uid" value="{{ $uid }}">
        <input type="hidden" name="email" value="{{ $email }}">
        <div class="relative">
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 mb-1.5">New password</label>
            <input :type="show ? 'text' : 'password'" name="password" required minlength="8"
                   class="block w-full rounded-xl border-0 ring-1 ring-gray-200 focus:ring-2 focus:ring-brand text-base px-3.5 py-2.5 pr-14 bg-white" autocomplete="new-password">
            <button type="button" @click="show = !show" class="absolute right-3 bottom-2.5 text-xs font-semibold text-brand">Show</button>
        </div>
        <div class="relative">
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 mb-1.5">Confirm password</label>
            <input :type="show2 ? 'text' : 'password'" name="password_confirmation" required minlength="8"
                   class="block w-full rounded-xl border-0 ring-1 ring-gray-200 focus:ring-2 focus:ring-brand text-base px-3.5 py-2.5 pr-14 bg-white" autocomplete="new-password">
            <button type="button" @click="show2 = !show2" class="absolute right-3 bottom-2.5 text-xs font-semibold text-brand">Show</button>
        </div>
        <button type="submit" data-loading-label="Saving…"
                class="w-full rounded-xl bg-brand text-white font-bold text-sm py-3">
            Save password
        </button>
    </form>
</x-site.console-auth-shell>
