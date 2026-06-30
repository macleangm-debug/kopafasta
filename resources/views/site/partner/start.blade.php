<x-site.layout :title="brand_title('Partner portal')">
    <div class="max-w-md mx-auto py-10 px-4">
        <h1 class="text-2xl font-bold mb-2">Partner portal</h1>
        <p class="text-sm text-gray-600 mb-6">Enter your partner code and registered phone number. You will create a 4-digit PIN, then sign in to the partner portal.</p>

        @if ($errors->any())
            <div class="mb-4 rounded-lg bg-rose-50 ring-1 ring-rose-200 px-4 py-3 text-sm text-rose-700">
                @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('site.partner.start.lookup') }}" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Partner code</label>
                <input type="text" name="partner_code" value="{{ old('partner_code') }}" required class="w-full rounded-lg border-gray-300 px-3 py-2 text-sm font-mono uppercase">
            </div>
            <div>
                <x-site.phone-input name="phone" label="Phone number" :value="old('phone')" required id="partner-start-phone" />
            </div>
            <p class="text-xs text-gray-500">Use the phone number registered on your partner account.</p>
            <button type="submit" class="w-full bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-3 rounded-full text-sm">
                Continue to activation
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-gray-500">
            Already activated?
            <a href="{{ route('site.login', ['portal' => 'partner']) }}" class="font-semibold text-amber-700 hover:underline">Sign in with phone & PIN</a>
        </p>
    </div>
</x-site.layout>
