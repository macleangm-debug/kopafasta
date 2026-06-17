<x-site.layout :title="brand_title('Affiliate verification')">
    <div class="max-w-md mx-auto py-12 px-4 text-center">
        @if ($verified && $affiliate)
            <div class="rounded-2xl bg-emerald-50 ring-1 ring-emerald-200 p-8">
                <div class="mx-auto mb-4 size-16 rounded-full bg-emerald-100 grid place-items-center text-2xl">✓</div>
                <h1 class="text-xl font-bold text-emerald-900">Verified affiliate partner</h1>
                <p class="mt-2 text-sm text-emerald-800">{{ $affiliate->name }}</p>
                @if (! empty($notice))
                    <p class="mt-3 text-xs text-emerald-700">{{ $notice }}</p>
                @endif
                <p class="mt-1 font-mono text-sm text-emerald-700">{{ $affiliate->affiliate_code ?? $code }}</p>
                @if (! empty($verify_url))
                    <div class="mt-5 flex flex-col items-center gap-2">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&data={{ urlencode($verify_url) }}"
                             alt="Affiliate verification QR code" class="size-32 rounded-lg bg-white p-2 ring-1 ring-emerald-200">
                        <p class="text-xs text-emerald-700">Scan to verify this affiliate partner</p>
                    </div>
                @endif
                @if ($affiliate->phone)
                    <p class="mt-3 text-xs text-emerald-700">{{ $affiliate->phone }}</p>
                @endif
            </div>
        @elseif ($affiliate)
            <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 p-8">
                <h1 class="text-xl font-bold text-amber-900">Partner found</h1>
                <p class="mt-2 text-sm text-amber-800">{{ $affiliate->name }}</p>
                <p class="mt-1 font-mono text-sm">{{ $affiliate->affiliate_code ?? $code }}</p>
                @if (! empty($verify_url))
                    <div class="mt-4 flex flex-col items-center gap-2">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=140x140&data={{ urlencode($verify_url) }}"
                             alt="Affiliate verification QR code" class="size-28 rounded-lg bg-white p-2 ring-1 ring-amber-200">
                        <p class="text-xs text-amber-700">Partner code QR</p>
                    </div>
                @endif
                <p class="mt-3 text-xs text-amber-700">KYC status: {{ ucfirst($affiliate->affiliate_kyc_status ?? 'pending') }}</p>
            </div>
        @else
            <div class="rounded-2xl bg-gray-50 ring-1 ring-gray-200 p-8">
                <h1 class="text-xl font-bold text-gray-900">Affiliate not found</h1>
                <p class="mt-2 text-sm text-gray-600">No affiliate matches code <span class="font-mono">{{ $code }}</span>.</p>
            </div>
        @endif
    </div>
</x-site.layout>
