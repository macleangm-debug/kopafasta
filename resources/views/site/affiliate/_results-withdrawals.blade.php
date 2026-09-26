<div class="px-5 py-3 border-b border-gray-100">
    <h2 class="font-semibold text-gray-900">{{ __('site.affiliate_portal.withdrawal_requests') }}</h2>
</div>
@if (($withdrawals ?? collect())->isEmpty())
    <x-site.empty-state
        icon="🏦"
        :title="__('site.affiliate_portal.no_withdrawals')"
        :description="__('site.affiliate_portal.no_withdrawals_hint')"
    />
@else
    <div class="hidden lg:block overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-[10px] uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-5 py-3">{{ __('site.affiliate_portal.col_request_id') }}</th>
                    <th class="px-5 py-3">{{ __('site.affiliate_portal.col_requested') }}</th>
                    <th class="px-5 py-3">{{ __('site.affiliate_portal.col_amount') }}</th>
                    <th class="px-5 py-3">{{ __('site.affiliate_portal.col_destination') }}</th>
                    <th class="px-5 py-3">{{ __('site.affiliate_portal.col_status') }}</th>
                    <th class="px-5 py-3">{{ __('site.affiliate_portal.col_payout_payment_id') }}</th>
                    <th class="px-5 py-3">{{ __('site.affiliate_portal.col_settled') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($withdrawals as $request)
                    <tr>
                        <td class="px-5 py-3 font-mono text-xs">{{ $request->requestNumber() }}</td>
                        <td class="px-5 py-3 text-xs text-gray-600">{{ $request->created_at?->timezone(config('app.timezone'))->translatedFormat('d M Y') }}</td>
                        <td class="px-5 py-3 font-semibold tabular-nums">{{ format_money($request->amount) }}</td>
                        <td class="px-5 py-3 text-sm">{{ $request->payout_account_label ?: ($payoutAccountLabel ?? '—') }}</td>
                        <td class="px-5 py-3">
                            <p class="text-xs font-semibold">{{ __('site.affiliate_portal.withdrawal_status_'.$request->publicStatus()) }}</p>
                            @if ($request->publicReason())
                                <p class="mt-1 text-xs text-gray-600">{{ __('site.affiliate_portal.withdrawal_rejected_reason', ['reason' => $request->publicReason()]) }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-3 font-mono text-xs">{{ $request->payoutPaymentId() }}</td>
                        <td class="px-5 py-3 text-xs text-gray-600">{{ $request->paid_at?->timezone(config('app.timezone'))->translatedFormat('d M Y') ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="lg:hidden divide-y divide-gray-100">
        @foreach ($withdrawals as $request)
            <div class="px-5 py-4 space-y-1.5">
                <p class="font-mono text-xs text-gray-500">{{ $request->requestNumber() }}</p>
                <p class="text-sm font-semibold">{{ format_money($request->amount) }} · {{ __('site.affiliate_portal.withdrawal_status_'.$request->publicStatus()) }}</p>
                <p class="text-sm text-gray-700">{{ $request->payout_account_label ?: ($payoutAccountLabel ?? '—') }}</p>
                <p class="text-xs text-gray-500">{{ $request->created_at?->timezone(config('app.timezone'))->translatedFormat('d M Y') }}
                    · {{ $request->payoutPaymentId() }}</p>
                @if ($request->publicReason())
                    <p class="text-xs text-gray-600">{{ __('site.affiliate_portal.withdrawal_rejected_reason', ['reason' => $request->publicReason()]) }}</p>
                @endif
            </div>
        @endforeach
    </div>
@endif
