<x-site.layout title="Reset PIN — Kopafasta">
    <section class="min-h-screen flex items-center justify-center bg-gray-50 px-4 py-12">
        <div class="w-full max-w-md bg-white rounded-2xl border border-gray-200 shadow-sm p-6 sm:p-8">
            <h1 class="text-2xl font-bold text-gray-900">Reset your PIN</h1>
            <p class="mt-2 text-sm text-gray-600">We will send a verification code to your registered phone number.</p>

            @if (session('status'))
                <div class="mt-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mt-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            @php $step = (int) ($step ?? old('step', 1)); @endphp

            @if ($step === 1)
                <form method="POST" action="{{ route('site.forgot-pin.send') }}" class="mt-6 space-y-4">
                    @csrf
                    <div>
                        <x-site.phone-input name="phone" label="Phone number" :value="old('phone')" variant="rounded" :required="true" />
                    </div>
                    <button class="w-full bg-amber-500 hover:bg-amber-400 text-gray-900 font-bold py-3 rounded-full">Send verification code</button>
                </form>
            @else
                <form method="POST" action="{{ route('site.forgot-pin.reset') }}" class="mt-6 space-y-4">
                    @csrf
                    <input type="hidden" name="phone" value="{{ old('phone') }}">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Verification code</label>
                        <input type="text" name="otp" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" required
                               class="w-full rounded-xl border-gray-300 px-3 py-3 text-sm font-mono tracking-widest text-center">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">New 4-digit PIN</label>
                        <input type="password" name="pin" inputmode="numeric" maxlength="4" pattern="\d{4}" required
                               class="w-full rounded-xl border-gray-300 px-3 py-3 text-center text-lg tracking-[0.5em] font-mono">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Confirm PIN</label>
                        <input type="password" name="pin_confirmation" inputmode="numeric" maxlength="4" pattern="\d{4}" required
                               class="w-full rounded-xl border-gray-300 px-3 py-3 text-center text-lg tracking-[0.5em] font-mono">
                    </div>
                    <button class="w-full bg-gray-900 hover:bg-gray-800 text-white font-bold py-3 rounded-full">Reset PIN</button>
                </form>
            @endif

            <p class="mt-6 text-center text-sm text-gray-500">
                <a href="{{ route('site.login') }}" class="text-amber-600 font-semibold hover:underline">← Back to sign in</a>
            </p>
        </div>
    </section>
</x-site.layout>
