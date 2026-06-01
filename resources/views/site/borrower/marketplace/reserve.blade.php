<x-site.borrower-layout :title="brand_title('Asset application')" active="marketplace">

    <div class="mb-4">
        <a href="{{ route('site.borrower.marketplace.show', $asset['id']) }}" class="text-xs text-gray-500 hover:text-gray-700">← Back to asset</a>
    </div>

    <h1 class="text-2xl font-bold mb-1">Apply for asset</h1>
    <p class="text-sm text-gray-500 mb-6">{{ $asset['title'] }}</p>

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <ol class="space-y-3 mb-8">
        @foreach ($steps as $step)
            <li class="flex items-center gap-3 rounded-xl px-4 py-3 {{ $step['current'] ? 'bg-amber-50 ring-1 ring-amber-200' : ($step['done'] ? 'bg-emerald-50' : 'bg-gray-50') }}">
                <span class="w-6 h-6 rounded-full grid place-items-center text-xs font-bold {{ $step['done'] ? 'bg-emerald-500 text-white' : ($step['current'] ? 'bg-amber-500 text-gray-900' : 'bg-gray-200 text-gray-500') }}">{{ $step['done'] ? '✓' : '•' }}</span>
                <span class="text-sm {{ $step['done'] ? 'text-emerald-900' : 'text-gray-700' }}">{{ $step['label'] }}</span>
            </li>
        @endforeach
    </ol>

    <div class="bg-white rounded-2xl border border-gray-200 p-6 max-w-xl space-y-4">
        <div>
            <h2 class="font-semibold">Application details</h2>
            <p class="text-sm text-gray-500 mt-1">Viewing: {{ optional($reservation->viewing_date)->format('d M Y') ?? '—' }} at {{ $reservation->viewing_time ?? '—' }}</p>
            <p class="text-sm text-gray-500">Application fee: <strong>TZS {{ number_format($reservation->reservation_fee_amount) }}</strong> · Deposit: <strong>TZS {{ number_format($reservation->deposit_amount) }}</strong></p>
        </div>

        @php
            $onPaymentStep = $reservation->status === 'interest_confirmed'
                || ($reservation->status === 'viewing_completed' && $reservation->reservation_fee_status === 'paid')
                || ($reservation->status === 'reservation_fee_paid' && $reservation->viewing_completed_at);
            $needsRequirements = $onPaymentStep && ! ($applyRequirements['can_apply'] ?? false);
        @endphp

        @if ($needsRequirements)
            <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 p-4">
                <p class="text-sm font-semibold text-amber-900">Complete these requirements before payment</p>
                <ul class="mt-2 space-y-1 text-sm text-amber-800">
                    @foreach (($applyRequirements['items'] ?? []) as $item)
                        @if (! ($item['complete'] ?? false))
                            <li class="flex items-start gap-2">
                                <span>•</span>
                                <span>
                                    {{ $item['label'] }}
                                    @if (! empty($item['action_url']))
                                        — <a href="{{ $item['action_url'] }}" class="font-semibold underline">Complete</a>
                                    @endif
                                </span>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($reservation->status === 'viewing_scheduled')
            <form method="POST" action="{{ route('site.borrower.marketplace.reservation.advance', $asset['id']) }}">
                @csrf
                <input type="hidden" name="action" value="complete_viewing">
                <button class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-2.5 rounded-full text-sm">Mark viewing completed</button>
            </form>
        @elseif ($reservation->status === 'viewing_completed' && $reservation->reservation_fee_status === 'paid')
            @if ($applyRequirements['can_apply'] ?? false)
                <form method="POST" action="{{ route('site.borrower.marketplace.reservation.advance', $asset['id']) }}">
                    @csrf
                    <input type="hidden" name="action" value="pay_deposit">
                    <button class="bg-gray-900 hover:bg-gray-800 text-white font-semibold px-5 py-2.5 rounded-full text-sm">Confirm deposit paid</button>
                </form>
            @endif
        @elseif ($reservation->status === 'viewing_completed')
            <form method="POST" action="{{ route('site.borrower.marketplace.reservation.advance', $asset['id']) }}">
                @csrf
                <input type="hidden" name="action" value="confirm_interest">
                <button class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">Confirm I want this asset</button>
            </form>
        @elseif ($reservation->status === 'interest_confirmed')
            @if ($applyRequirements['can_apply'] ?? false)
                <form method="POST" action="{{ route('site.borrower.marketplace.reservation.advance', $asset['id']) }}">
                    @csrf
                    <input type="hidden" name="action" value="pay_reservation_fee">
                    <button class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">Confirm application fee paid</button>
                </form>
            @endif
        @elseif ($reservation->status === 'reservation_fee_paid')
            @if (! $reservation->viewing_completed_at)
                <form method="POST" action="{{ route('site.borrower.marketplace.reservation.advance', $asset['id']) }}">
                    @csrf
                    <input type="hidden" name="action" value="complete_viewing">
                    <button class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-2.5 rounded-full text-sm">Mark viewing completed</button>
                </form>
            @elseif ($applyRequirements['can_apply'] ?? false)
                <form method="POST" action="{{ route('site.borrower.marketplace.reservation.advance', $asset['id']) }}">
                    @csrf
                    <input type="hidden" name="action" value="pay_deposit">
                    <button class="bg-gray-900 hover:bg-gray-800 text-white font-semibold px-5 py-2.5 rounded-full text-sm">Confirm deposit paid</button>
                </form>
            @endif
        @elseif ($reservation->status === 'deposit_paid')
            <a href="{{ route('site.borrower.apply', ['product' => config('asset_marketplace.asset_loan_product_code', 'AL'), 'reservation' => $reservation->id]) }}" class="inline-flex bg-gray-900 hover:bg-gray-800 text-white font-semibold px-5 py-2.5 rounded-full text-sm">
                Apply for asset loan →
            </a>
        @else
            <p class="text-sm text-emerald-700 font-semibold">Application in progress. Track status from your applications page.</p>
        @endif
    </div>

</x-site.borrower-layout>
