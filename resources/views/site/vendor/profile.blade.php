<x-site.vendor-layout title="Profile" active="profile">
    <h1 class="text-2xl font-extrabold mb-1">Profile</h1>
    <p class="text-sm text-gray-500 mb-5">Vendor: <span class="font-mono">{{ $vendor->vendor_number }}</span> · Category: {{ ucfirst(str_replace('_',' ', $vendor->category)) }}</p>

    <form method="POST" action="{{ route('site.vendor.profile.update') }}" class="max-w-2xl rounded-2xl border border-gray-200 bg-white p-6 space-y-4">
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
        <button class="rounded-lg bg-indigo-600 text-white text-sm font-semibold px-5 py-2 hover:bg-indigo-700">Save changes</button>
    </form>
</x-site.vendor-layout>
