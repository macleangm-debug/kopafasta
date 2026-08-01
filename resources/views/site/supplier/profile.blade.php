<x-site.supplier-layout title="Supplier profile" active="profile">
    <div class="mb-6">
        <p class="text-xs uppercase tracking-widest text-brand font-semibold">Account</p>
        <h1 class="text-2xl font-bold text-gray-900 mt-1">Supplier profile</h1>
        <p class="text-sm text-gray-500 mt-1">
            Partner code:
            <span class="font-mono text-brand">{{ $vendor->vendor_number ?? $vendor->partner_number ?? '—' }}</span>
        </p>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('site.supplier.profile.update') }}" class="max-w-2xl glass-card p-6 space-y-4 ring-1 ring-brand/10">
        @csrf
        @method('PUT')
        <div>
            <label class="block text-xs font-semibold text-brand mb-1">Business / display name</label>
            <input name="name" value="{{ old('name', $vendor->name) }}" required
                   class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:ring-brand focus:border-brand">
        </div>
        <div class="grid sm:grid-cols-2 gap-3">
            <x-site.phone-input name="phone" label="Phone" :value="old('phone', $vendor->phone)" variant="rounded" />
            <div>
                <label class="block text-xs font-semibold text-brand mb-1">Email</label>
                <input name="email" type="email" value="{{ old('email', $vendor->email) }}"
                       class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:ring-brand focus:border-brand">
            </div>
        </div>
        <div>
            <label class="block text-xs font-semibold text-brand mb-1">Address</label>
            <textarea name="address" rows="3"
                      class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:ring-brand focus:border-brand">{{ old('address', $vendor->address) }}</textarea>
        </div>
        <button type="submit" class="rounded-xl bg-brand hover:bg-brand-light text-white text-sm font-semibold px-5 py-2.5">
            Save profile
        </button>
    </form>
</x-site.supplier-layout>
