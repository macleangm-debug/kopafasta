<x-site.vendor-layout title="Profile" active="profile">
    <h1 class="text-2xl font-extrabold mb-1">Profile</h1>
    <p class="text-sm text-gray-500 mb-5">Partner code: <span class="font-mono">{{ $vendor->vendor_number }}</span> · {{ ucfirst(str_replace('_',' ', $vendor->category)) }}</p>

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('site.vendor.profile.update') }}" enctype="multipart/form-data" class="max-w-2xl rounded-2xl border border-gray-200 bg-white p-6 space-y-4">
        @csrf @method('PUT')
        <div>
            <label class="block text-xs text-gray-500 mb-1">Business / display name</label>
            <input name="name" value="{{ old('name', $vendor->name) }}" required class="w-full rounded-lg border-gray-300 text-sm">
        </div>
        <div class="grid sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Phone</label>
                <input name="phone" value="{{ old('phone', $vendor->phone) }}" class="w-full rounded-lg border-gray-300 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Email</label>
                <input name="email" type="email" value="{{ old('email', $vendor->email) }}" class="w-full rounded-lg border-gray-300 text-sm">
            </div>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Address</label>
            <textarea name="address" rows="3" class="w-full rounded-lg border-gray-300 text-sm">{{ old('address', $vendor->address) }}</textarea>
        </div>

        @if ($vendor->isAffiliate())
            <div class="pt-4 border-t border-gray-100 space-y-3">
                <h2 class="font-semibold text-gray-900">Affiliate KYC</h2>
                <p class="text-xs text-gray-500">Status: {{ ucfirst($vendor->affiliate_kyc_status ?? 'pending') }}</p>
                @if ($vendor->affiliate_code)
                    @php $verifyUrl = route('site.affiliate.verify', $vendor->affiliate_code); @endphp
                    <div class="flex items-center gap-4 rounded-xl bg-gray-50 p-3">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data={{ urlencode($verifyUrl) }}" alt="Affiliate QR" class="size-16 rounded bg-white p-1">
                        <div class="text-xs text-gray-600">
                            <p class="font-semibold text-gray-800">Public verification</p>
                            <a href="{{ $verifyUrl }}" class="text-indigo-700 hover:underline break-all" target="_blank">{{ $verifyUrl }}</a>
                        </div>
                    </div>
                @endif
                <div class="grid sm:grid-cols-3 gap-3">
                    <label class="block text-xs text-gray-500">Selfie<input type="file" name="affiliate_selfie" accept="image/*" class="mt-1 w-full text-xs"></label>
                    <label class="block text-xs text-gray-500">National ID<input type="file" name="affiliate_id" accept="image/*" class="mt-1 w-full text-xs"></label>
                    <label class="block text-xs text-gray-500">Photo<input type="file" name="affiliate_photo" accept="image/*" class="mt-1 w-full text-xs"></label>
                </div>
            </div>
        @endif

        <button class="rounded-lg bg-indigo-600 text-white text-sm font-semibold px-5 py-2 hover:bg-indigo-700">Save changes</button>
    </form>
</x-site.vendor-layout>
