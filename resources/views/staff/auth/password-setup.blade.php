<x-site.layout :title="brand_title('Set password')">
    <section class="max-w-md mx-auto px-4 py-12">
        <div class="rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm p-6 space-y-4">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Kopafasta Staff</p>
            <h1 class="text-xl font-bold text-gray-900">Choose your password</h1>
            <p class="text-sm text-gray-600">Hi {{ $name }}. This link is single-use and expires soon.</p>

            @if ($errors->any())
                <div class="rounded-xl bg-rose-50 ring-1 ring-rose-200 px-3 py-2 text-sm text-rose-800">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('staff.password-setup.store') }}" class="space-y-3" x-data="{ show: false, show2: false }">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="uid" value="{{ $uid }}">
                <input type="hidden" name="email" value="{{ $email }}">
                <div class="relative">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">New password</label>
                    <input :type="show ? 'text' : 'password'" name="password" required minlength="8"
                           class="w-full rounded-xl border-gray-200 text-sm pr-14" autocomplete="new-password">
                    <button type="button" @click="show = !show" class="absolute right-3 bottom-2.5 text-xs font-semibold text-brand">Show</button>
                </div>
                <div class="relative">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Confirm password</label>
                    <input :type="show2 ? 'text' : 'password'" name="password_confirmation" required minlength="8"
                           class="w-full rounded-xl border-gray-200 text-sm pr-14" autocomplete="new-password">
                    <button type="button" @click="show2 = !show2" class="absolute right-3 bottom-2.5 text-xs font-semibold text-brand">Show</button>
                </div>
                <button type="submit" data-loading-label="Saving…"
                        class="w-full rounded-xl bg-brand text-white font-bold text-sm py-3">
                    Save password
                </button>
            </form>
        </div>
    </section>
</x-site.layout>
