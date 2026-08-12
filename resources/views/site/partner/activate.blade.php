<x-site.layout :title="brand_title('Activate Partner Account')">
    <div class="max-w-md mx-auto py-10 px-4">
        <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-1">Partner portal</p>
        <h1 class="text-2xl font-bold mb-2">Activate your account</h1>
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

        <form method="POST" action="{{ route('site.partner.activate.post', $vendor) }}" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4" autocomplete="off">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <p class="text-sm text-gray-600">Confirm activation to open your portal. You will create a 4-digit PIN next.</p>

            <label class="flex items-start gap-3 rounded-xl bg-amber-50 ring-1 ring-amber-100 px-3.5 py-3 text-sm text-gray-800 cursor-pointer">
                <input type="checkbox" name="collection_conduct_accepted" value="1" required
                       class="mt-1 size-4 rounded border-gray-300 text-brand focus:ring-brand">
                <span>
                    <span class="font-semibold text-gray-900">{{ __('site.partner_apply.conduct_title') }}</span>
                    <span class="block mt-1 text-xs text-gray-600 leading-relaxed">{{ __('site.partner_apply.conduct_body') }}</span>
                </span>
            </label>

            <button type="submit" class="w-full bg-brand hover:bg-brand-light text-white font-semibold px-5 py-3 rounded-xl text-sm">
                Activate account
            </button>
        </form>
    </div>
</x-site.layout>
