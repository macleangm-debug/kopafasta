<x-site.layout :title="brand_title('Activate Partner Account')">
    <div class="max-w-md mx-auto py-10 px-4">
        <p class="text-xs uppercase tracking-widest text-amber-600 mb-1">Partner portal</p>
        <h1 class="text-2xl font-bold mb-2">Create your PIN</h1>
        <p class="text-sm text-gray-600 mb-2">Partner: <strong>{{ $vendor->name }}</strong></p>
        @if ($vendor->vendor_number)
            <p class="text-sm text-gray-500 mb-6">Partner code: <span class="font-mono font-semibold">{{ $vendor->vendor_number }}</span></p>
        @else
            <div class="mb-6"></div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded-lg bg-rose-50 ring-1 ring-rose-200 px-4 py-3 text-sm text-rose-700">
                @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('site.partner.activate.post', $vendor) }}" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input type="password" name="password" required class="w-full rounded-lg border-gray-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Confirm password</label>
                <input type="password" name="password_confirmation" required class="w-full rounded-lg border-gray-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">4-digit PIN</label>
                <input type="password" name="pin" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" required class="w-full rounded-lg border-gray-300 px-3 py-2 text-sm font-mono tracking-widest">
            </div>

            <button type="submit" class="w-full bg-gray-900 hover:bg-gray-800 text-white font-semibold px-5 py-3 rounded-full text-sm">
                Activate account
            </button>
        </form>
    </div>
</x-site.layout>
