<x-site.borrower-layout :title="brand_title(__('borrower.marketplace.reserve_title'))" active="marketplace">

    <div class="mb-4">
        <a href="{{ route('site.borrower.marketplace.show', $asset['id']) }}" class="text-xs text-gray-500 hover:text-gray-700">← {{ __('borrower.marketplace.back_to_asset') }}</a>
    </div>

    <h1 class="text-2xl font-bold mb-1">{{ __('borrower.marketplace.reserve_title') }}</h1>
    <p class="text-sm text-gray-500 mb-6">{{ $asset['title'] }}</p>

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif
    @if (session('warning'))
        <div class="mb-4 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-800">{{ session('warning') }}</div>
    @endif

    <ol class="space-y-3 mb-8">
        @foreach ($steps as $step)
            <li class="flex items-center gap-3 rounded-xl px-4 py-3 {{ $step['current'] ? 'bg-amber-50 ring-1 ring-amber-200' : ($step['done'] ? 'bg-emerald-50' : 'bg-gray-50') }}">
                <span class="w-6 h-6 rounded-full grid place-items-center text-xs font-bold {{ $step['done'] ? 'bg-emerald-500 text-white' : ($step['current'] ? 'bg-amber-500 text-gray-900' : 'bg-gray-200 text-gray-500') }}">{{ $step['done'] ? '✓' : '•' }}</span>
                <span class="text-sm {{ $step['done'] ? 'text-emerald-900' : 'text-gray-700' }}">{{ $step['label'] }}</span>
            </li>
        @endforeach
    </ol>

    <div class="grid lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
            <h2 class="font-semibold">{{ __('borrower.marketplace.asset_summary') }}</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-gray-500">{{ __('borrower.marketplace.asset_value') }}</dt><dd class="font-semibold">TZS {{ number_format($asset['asset_value'] ?? 0) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">{{ __('borrower.marketplace.loan_amount') }}</dt><dd class="font-semibold">TZS {{ number_format($asset['remaining_loan'] ?? 0) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">{{ __('borrower.marketplace.deposit') }}</dt><dd class="font-semibold">TZS {{ number_format($feeBreakdown['deposit']) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">{{ __('borrower.marketplace.weekly_installment') }}</dt><dd class="font-semibold">TZS {{ number_format($asset['weekly_installment'] ?? 0) }}</dd></div>
                @if (! empty($asset['max_tenure_months']))
                    <div class="flex justify-between gap-3"><dt class="text-gray-500">{{ __('borrower.marketplace.max_tenure') }}</dt><dd class="font-semibold">{{ $asset['max_tenure_months'] }} {{ __('borrower.apply.quote.months') }}</dd></div>
                @endif
            </dl>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
            <h2 class="font-semibold">{{ __('borrower.marketplace.fees_heading') }}</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">{{ $feeBreakdown['application_fee_label'] }}</dt>
                    <dd class="font-semibold">TZS {{ number_format($feeBreakdown['application_fee']) }}</dd>
                </div>
                @if (! empty($feeBreakdown['application_fee_detail']))
                    <p class="text-xs text-gray-500">{{ $feeBreakdown['application_fee_detail'] }}</p>
                @endif
                <div class="flex justify-between gap-3 pt-2 border-t border-gray-100">
                    <dt class="text-gray-500">{{ $feeBreakdown['deposit_label'] }}</dt>
                    <dd class="font-semibold">TZS {{ number_format($feeBreakdown['deposit']) }}</dd>
                </div>
            </dl>
            @if (! empty($feeBreakdown['post_approval']))
                <div class="pt-3 border-t border-gray-100">
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">{{ __('borrower.marketplace.fees.after_approval') }}</p>
                    <ul class="space-y-2 text-sm">
                        @foreach ($feeBreakdown['post_approval'] as $line)
                            <li class="flex justify-between gap-3">
                                <span class="text-gray-600">{{ $line['name'] }}</span>
                                <span class="font-medium">{{ $line['amount_label'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <p class="text-xs text-gray-500">{{ __('borrower.marketplace.fees.payment_note') }}</p>
        </div>
    </div>

    <div class="mt-8 bg-white rounded-2xl border border-gray-200 p-6 max-w-2xl space-y-4">
        <h2 class="font-semibold">{{ __('borrower.marketplace.next_action') }}</h2>

        @php
            $needsRequirements = in_array($reservation->status, ['interest_confirmed', 'reservation_fee_paid'], true)
                && ! ($applyRequirements['can_apply'] ?? false);
        @endphp

        @if ($needsRequirements)
            <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 p-4">
                <p class="text-sm font-semibold text-amber-900">{{ __('borrower.marketplace.requirements_before_payment') }}</p>
                <ul class="mt-2 space-y-1 text-sm text-amber-800">
                    @foreach (($applyRequirements['items'] ?? []) as $item)
                        @if (! ($item['complete'] ?? false))
                            <li class="flex items-start gap-2">
                                <span>•</span>
                                <span>
                                    {{ $item['label'] }}
                                    @if (! empty($item['action_url']))
                                        — <a href="{{ $item['action_url'] }}" class="font-semibold underline">{{ __('borrower.marketplace.complete_item') }}</a>
                                    @endif
                                </span>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($reservation->status === 'application_started')
            <div class="space-y-4">
                <p class="text-sm text-gray-600">{{ __('borrower.marketplace.schedule_viewing_hint') }}</p>
                <form method="POST" action="{{ route('site.borrower.marketplace.reserve.post', $asset['id']) }}" class="grid sm:grid-cols-2 gap-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.marketplace.viewing_date') }}</label>
                        <input type="date" name="viewing_date" required min="{{ now()->addDay()->format('Y-m-d') }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.marketplace.viewing_time') }}</label>
                        <input type="time" name="viewing_time" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <button class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">{{ __('borrower.marketplace.schedule_viewing') }}</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('site.borrower.marketplace.reservation.advance', $asset['id']) }}">
                    @csrf
                    <input type="hidden" name="action" value="skip_viewing">
                    <button type="submit" class="text-sm font-semibold text-gray-600 hover:text-gray-900 underline">{{ __('borrower.marketplace.skip_viewing') }}</button>
                </form>
            </div>
        @elseif ($reservation->status === 'viewing_scheduled')
            <p class="text-sm text-gray-600">{{ __('borrower.marketplace.viewing_scheduled_for', ['date' => optional($reservation->viewing_date)->format('d M Y') ?? '—', 'time' => $reservation->viewing_time ?? '—']) }}</p>
            <form method="POST" action="{{ route('site.borrower.marketplace.reservation.advance', $asset['id']) }}">
                @csrf
                <input type="hidden" name="action" value="complete_viewing">
                <button class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-2.5 rounded-full text-sm">{{ __('borrower.marketplace.mark_viewing_done') }}</button>
            </form>
        @elseif ($reservation->status === 'viewing_completed')
            <form method="POST" action="{{ route('site.borrower.marketplace.reservation.advance', $asset['id']) }}">
                @csrf
                <input type="hidden" name="action" value="confirm_interest">
                <button class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">{{ __('borrower.marketplace.confirm_interest') }}</button>
            </form>
        @elseif ($reservation->status === 'interest_confirmed')
            @if ($applyRequirements['can_apply'] ?? false)
                <p class="text-sm text-gray-600">{{ __('borrower.marketplace.pay_application_fee_hint', ['amount' => number_format($reservation->reservation_fee_amount)]) }}</p>
                <form method="POST" action="{{ route('site.borrower.marketplace.reservation.advance', $asset['id']) }}">
                    @csrf
                    <input type="hidden" name="action" value="pay_reservation_fee">
                    <button class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">{{ __('borrower.marketplace.confirm_application_fee') }}</button>
                </form>
            @endif
        @elseif ($reservation->status === 'reservation_fee_paid')
            @if ($applyRequirements['can_apply'] ?? false)
                <p class="text-sm text-gray-600">{{ __('borrower.marketplace.pay_deposit_hint', ['amount' => number_format($reservation->deposit_amount)]) }}</p>
                <form method="POST" action="{{ route('site.borrower.marketplace.reservation.advance', $asset['id']) }}">
                    @csrf
                    <input type="hidden" name="action" value="pay_deposit">
                    <button class="bg-gray-900 hover:bg-gray-800 text-white font-semibold px-5 py-2.5 rounded-full text-sm">{{ __('borrower.marketplace.confirm_deposit') }}</button>
                </form>
            @endif
        @elseif ($reservation->status === 'deposit_paid')
            <p class="text-sm text-emerald-800 font-medium mb-3">{{ __('borrower.marketplace.deposit_ready') }}</p>
            <a href="{{ route('site.borrower.apply', ['product' => config('asset_marketplace.asset_loan_product_code', 'AL'), 'reservation' => $reservation->id]) }}" class="inline-flex bg-gray-900 hover:bg-gray-800 text-white font-semibold px-5 py-2.5 rounded-full text-sm">
                {{ __('borrower.marketplace.start_loan_application') }} →
            </a>
        @else
            <p class="text-sm text-emerald-700 font-semibold">{{ __('borrower.marketplace.in_progress') }}</p>
            <a href="{{ route('site.borrower.applications') }}" class="text-sm font-semibold text-amber-700 hover:underline mt-2 inline-block">{{ __('borrower.dashboard.view_all') }}</a>
        @endif
    </div>

</x-site.borrower-layout>
