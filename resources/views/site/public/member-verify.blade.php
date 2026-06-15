<x-site.layout :title="brand_title('Member verification')">
    <div class="max-w-md mx-auto py-12 px-4 text-center">
        @if ($verified && $customer)
            <div class="rounded-2xl bg-emerald-50 ring-1 ring-emerald-200 p-8">
                <div class="mx-auto mb-4 size-16 rounded-full bg-emerald-100 grid place-items-center text-2xl">✓</div>
                <h1 class="text-xl font-bold text-emerald-900">Verified member</h1>
                <p class="mt-2 text-sm text-emerald-800">{{ strtoupper(trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''))) }}</p>
                <p class="mt-1 font-mono text-sm text-emerald-700">{{ $memberNo }}</p>
                <p class="mt-4 text-xs text-emerald-700">Active membership · expires {{ optional($customer->membership_expires_at)->format('d M Y') ?? '—' }}</p>
            </div>
        @elseif ($customer)
            <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 p-8">
                <h1 class="text-xl font-bold text-amber-900">Member found</h1>
                <p class="mt-2 text-sm text-amber-800">Membership is not currently active.</p>
                <p class="mt-1 font-mono text-sm">{{ $memberNo }}</p>
            </div>
        @else
            <div class="rounded-2xl bg-gray-50 ring-1 ring-gray-200 p-8">
                <h1 class="text-xl font-bold text-gray-900">Member not found</h1>
                <p class="mt-2 text-sm text-gray-600">No member matches <span class="font-mono">{{ $memberNo }}</span>.</p>
            </div>
        @endif
    </div>
</x-site.layout>
