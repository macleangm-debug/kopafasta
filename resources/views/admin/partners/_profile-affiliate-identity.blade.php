    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Affiliate lifecycle</h3>
        @php $lifecycle = app(\App\Services\AffiliateLifecycleService::class); @endphp
        <p class="text-sm text-gray-600 mb-2">
            Status:
            <span class="font-semibold">{{ $lifecycle->label($lifecycle->statusFor($record)) }}</span>
        </p>
        @if ($record->affiliate_leaderboard_rank)
            <p class="text-sm text-gray-600 mb-2">Leaderboard rank: <span class="font-semibold">#{{ $record->affiliate_leaderboard_rank }}</span></p>
        @endif
        @if ($record->affiliate_lifecycle_note)
            <p class="text-xs text-gray-500 mb-2">Note: {{ $record->affiliate_lifecycle_note }}</p>
        @endif
        <p class="text-xs text-gray-500">Lifecycle is set by the system from KYC, volume, and risk. It cannot be changed here.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Affiliate KYC</h3>
        <p class="text-sm text-gray-600 mb-4">
            Status:
            <span class="font-semibold {{ in_array($record->affiliate_kyc_status, ['verified', 'approved'], true) ? 'text-emerald-700' : 'text-amber-700' }}">
                {{ ucfirst($record->affiliate_kyc_status ?? 'pending') }}
            </span>
        </p>
        <div class="grid sm:grid-cols-3 gap-4 text-sm mb-4">
            @foreach ([
                'Selfie' => $record->affiliate_selfie_path,
                'ID document' => $record->affiliate_id_path,
                'Profile photo' => $record->affiliate_photo_path,
            ] as $label => $path)
                <div class="rounded-lg bg-gray-50 p-3">
                    <p class="text-xs text-gray-500 mb-2">{{ $label }}</p>
                    @if ($path)
                        <a href="{{ asset('storage/'.$path) }}" target="_blank" class="text-brand hover:underline text-xs">View file</a>
                    @else
                        <p class="text-xs text-gray-400">Not uploaded</p>
                    @endif
                </div>
            @endforeach
        </div>
        @if ($record->affiliate_code)
            <p class="text-xs text-gray-500 mb-4">Public verification: <a href="{{ route('site.affiliate.verify', $record->affiliate_code) }}" class="text-brand hover:underline" target="_blank">{{ route('site.affiliate.verify', $record->affiliate_code) }}</a></p>
        @endif
        @if (in_array($record->affiliate_kyc_status, ['submitted', 'pending', 'rejected'], true) || filled($record->affiliate_selfie_path))
            <div class="flex flex-wrap gap-3">
                <form method="POST" action="{{ route('admin.partners.affiliate-kyc.approve', $record) }}">
                    @csrf
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">Approve KYC</button>
                </form>
                <form method="POST" action="{{ route('admin.partners.affiliate-kyc.reject', $record) }}">
                    @csrf
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">Reject KYC</button>
                </form>
            </div>
        @endif
    </div>

    @if ($membership ?? null)
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Affiliate membership</h3>
            <div class="grid sm:grid-cols-3 gap-4 text-sm mb-4">
                <div>
                    <span class="text-gray-500">Status</span>
                    <p class="text-lg font-bold {{ $membership['active'] ? 'text-emerald-700' : 'text-amber-700' }}">{{ $membership['label'] }}</p>
                </div>
                <div>
                    <span class="text-gray-500">Annual fee</span>
                    <p class="text-lg font-bold">{{ format_money($membership['fee']) }}</p>
                </div>
                <div>
                    <span class="text-gray-500">Expires</span>
                    <p class="text-lg font-bold">{{ $membership['expires_at']?->format('d M Y') ?? '—' }}</p>
                </div>
            </div>
            @if ($membership['reference'])
                <p class="text-xs text-gray-500 mb-2">Payment reference: <span class="font-mono">{{ $membership['reference'] }}</span></p>
            @endif
            @if ($membership['due_at'])
                <p class="text-xs text-gray-500 mb-4">Pay-by window: {{ $membership['due_at']->format('d M Y H:i') }}</p>
            @endif
            <p class="text-xs text-gray-500">Payment status is updated when the fee clears. Affiliates do not approve membership payments.</p>
        </div>
    @endif
