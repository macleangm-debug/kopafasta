<x-site.affiliate-layout :title="brand_title(__('site.affiliate_portal.wallet_title'))" active="wallet" :hero="false">

    <section class="mb-6 kf-premium-panel rounded-2xl p-6 sm:p-8 relative" x-data="{
        withdrawing: {{ $errors->has('amount') || $errors->has('notes') ? 'true' : 'false' }},
        step: 'form'
    }">
        <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_50%)]"></div>
        <div class="relative flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.affiliate_portal.wallet_title') }}</p>
                <p class="text-xs uppercase tracking-widest text-white/70 font-semibold mt-3">{{ __('site.affiliate_portal.hero_available') }}</p>
                <p class="text-3xl sm:text-4xl font-bold mt-1 tabular-nums">{{ format_money($available) }}</p>
                <p class="text-sm text-white/70 mt-2">{{ __('site.affiliate_portal.hero_pending', ['amount' => format_money($pending ?? $totals['pending'] ?? 0)]) }}</p>
                @if (($inProgress ?? 0) > 0)
                    <p class="text-sm text-white/70 mt-1">{{ __('site.affiliate_portal.hero_in_progress', ['amount' => format_money($inProgress)]) }}</p>
                @endif
                <p class="text-sm text-white/70 mt-1">{{ __('site.affiliate_portal.min_payout_note', ['amount' => format_money($minPayout)]) }}</p>
            </div>
            <button type="button" @click="withdrawing = true; step = 'form'"
                    class="inline-flex justify-center bg-white text-brand font-semibold px-6 py-3 rounded-xl text-sm shrink-0 hover:bg-brand-gold transition">
                {{ __('site.affiliate_portal.withdraw') }}
            </button>
        </div>

        <div x-show="withdrawing" x-cloak class="relative mt-6 rounded-2xl bg-white text-gray-900 p-5 ring-1 ring-brand/15 shadow-sm">
            @if (! ($hasPayoutAccount ?? false))
                <p class="text-sm font-bold text-gray-900">{{ __('site.affiliate_portal.withdraw_need_account_title') }}</p>
                <p class="text-sm text-gray-600 mt-1">{{ __('site.affiliate_portal.withdraw_need_account_body') }}</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('site.affiliate.profile', ['section' => 'payment']) }}"
                       class="inline-flex justify-center bg-brand hover:bg-brand-light text-white font-semibold px-4 py-2.5 rounded-xl text-sm">
                        {{ __('site.affiliate_portal.withdraw_need_account_cta') }} →
                    </a>
                    <button type="button" @click="withdrawing = false"
                            class="inline-flex justify-center rounded-xl ring-1 ring-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-800 hover:bg-gray-50">
                        {{ __('site.affiliate_portal.cancel_withdraw') }}
                    </button>
                </div>
            @elseif ($available >= $minPayout)
                <form id="payout-form" method="POST" action="{{ route('site.affiliate.wallet.payout-request') }}" class="space-y-4"
                      x-data="{ amount: @js(old('amount', (int) max($minPayout, $available))) }">
                    @csrf
                    <div x-show="step === 'form'">
                        <h2 class="text-sm font-bold text-gray-900">{{ __('site.affiliate_portal.withdraw') }}</h2>
                        <p class="text-sm text-gray-600 mt-1">{{ __('site.affiliate_portal.available_balance', ['amount' => format_money($available)]) }}</p>
                        <div class="mt-4">
                            <p class="text-xs font-medium text-gray-600">{{ __('site.affiliate_portal.payout_account') }}</p>
                            <p class="mt-1 text-sm font-semibold text-gray-900">{{ $payoutAccountLabel ?? '—' }}</p>
                        </div>
                        <div class="mt-4 grid sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_portal.payout_amount') }}</label>
                                <input type="number" name="amount" min="{{ (int) $minPayout }}" max="{{ (int) $available }}" step="1000" required
                                       x-model="amount"
                                       value="{{ old('amount', (int) max($minPayout, $available)) }}"
                                       class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-brand/10 outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_portal.payout_notes') }}</label>
                                <input type="text" name="notes" value="{{ old('notes') }}" maxlength="500"
                                       class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-brand/10 outline-none">
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="button" @click="step = 'review'"
                                    class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm">{{ __('site.affiliate_portal.review_withdrawal') }}</button>
                            <button type="button" @click="withdrawing = false"
                                    class="inline-flex justify-center rounded-xl ring-1 ring-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-800 hover:bg-gray-50">{{ __('site.affiliate_portal.cancel_withdraw') }}</button>
                        </div>
                    </div>
                    <div x-show="step === 'review'" x-cloak>
                        <h2 class="text-sm font-bold text-gray-900">{{ __('site.affiliate_portal.review_withdrawal') }}</h2>
                        <p class="mt-3 text-sm text-gray-700">{{ __('site.affiliate_portal.withdraw_confirm_body') }}</p>
                        <dl class="mt-4 space-y-2 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-gray-500">{{ __('site.affiliate_portal.payout_amount') }}</dt>
                                <dd class="font-semibold tabular-nums" x-text="amount"></dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-gray-500">{{ __('site.affiliate_portal.payout_account') }}</dt>
                                <dd class="font-semibold text-right">{{ $payoutAccountLabel ?? '—' }}</dd>
                            </div>
                        </dl>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="submit" class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm">{{ __('site.affiliate_portal.confirm_withdrawal') }}</button>
                            <button type="button" @click="step = 'form'"
                                    class="inline-flex justify-center rounded-xl ring-1 ring-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-800 hover:bg-gray-50">{{ __('site.affiliate_portal.back_to_amount') }}</button>
                        </div>
                    </div>
                </form>
            @else
                <p class="text-sm font-bold text-gray-900">{{ __('site.affiliate_portal.withdraw') }}</p>
                <p class="text-sm text-gray-700 mt-1">{{ __('site.affiliate_portal.payout_not_ready', ['amount' => format_money($minPayout), 'available' => format_money($available)]) }}</p>
                <button type="button" @click="withdrawing = false"
                        class="mt-4 inline-flex justify-center rounded-xl ring-1 ring-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-800 hover:bg-gray-50">{{ __('site.affiliate_portal.cancel_withdraw') }}</button>
            @endif
        </div>
    </section>

    <div class="glass-card overflow-hidden" x-data="{ tab: 'commissions' }">
        <nav class="grid grid-cols-2 gap-1 p-1 m-3 rounded-2xl bg-brand/5 ring-1 ring-brand/10" role="tablist">
            <button type="button" @click="tab = 'commissions'" role="tab"
                    class="min-w-0 px-2 py-2.5 rounded-xl text-[11px] sm:text-sm font-bold tracking-tight transition text-center"
                    :class="tab === 'commissions' ? 'bg-brand text-white shadow-sm' : 'text-brand/70 hover:bg-white hover:text-brand'">
                {{ __('site.affiliate_portal.tab_commissions') }}
            </button>
            <button type="button" @click="tab = 'withdrawals'" role="tab"
                    class="min-w-0 px-2 py-2.5 rounded-xl text-[11px] sm:text-sm font-bold tracking-tight transition text-center"
                    :class="tab === 'withdrawals' ? 'bg-brand text-white shadow-sm' : 'text-brand/70 hover:bg-white hover:text-brand'">
                {{ __('site.affiliate_portal.tab_withdrawals') }}
            </button>
        </nav>

        <div x-show="tab === 'commissions'">
            <div class="px-5 py-3 border-b border-gray-100">
                <h2 class="font-semibold text-gray-900">{{ __('site.affiliate_portal.commission_transactions') }}</h2>
            </div>
            @if (empty($commissions))
                <x-site.empty-state
                    icon="💰"
                    :title="__('site.affiliate_portal.no_payments')"
                    :description="__('site.affiliate_portal.no_payments_hint')"
                    :action-label="__('site.affiliate_portal.nav_share')"
                    :action-url="route('site.affiliate.share')"
                />
            @else
                <div class="hidden lg:block overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-left text-[10px] uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-5 py-3">{{ __('site.affiliate_portal.col_payment_id') }}</th>
                                <th class="px-5 py-3">{{ __('site.affiliate_portal.col_date') }}</th>
                                <th class="px-5 py-3">{{ __('site.affiliate_portal.col_member') }}</th>
                                <th class="px-5 py-3">{{ __('site.affiliate_portal.col_paid_for') }}</th>
                                <th class="px-5 py-3">{{ __('site.affiliate_portal.col_payment_amount') }}</th>
                                <th class="px-5 py-3">{{ __('site.affiliate_portal.col_your_commission') }}</th>
                                <th class="px-5 py-3">{{ __('site.affiliate_portal.col_status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($commissions as $row)
                                <tr>
                                    <td class="px-5 py-3 font-mono text-xs">{{ $row['payment_id'] }}</td>
                                    <td class="px-5 py-3 text-xs text-gray-600">{{ optional($row['date'])->timezone(config('app.timezone'))->translatedFormat('d M Y') }}</td>
                                    <td class="px-5 py-3 font-mono text-xs">{{ $row['member_no'] }}</td>
                                    <td class="px-5 py-3">{{ $row['paid_for'] }}</td>
                                    <td class="px-5 py-3 tabular-nums">{{ $row['payment_amount'] !== null ? format_money($row['payment_amount']) : '—' }}</td>
                                    <td class="px-5 py-3 font-semibold tabular-nums">{{ format_money($row['commission']) }}</td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex text-[10px] font-bold uppercase tracking-wide rounded-full px-2.5 py-1 ring-1
                                            {{ match($row['status']) {
                                                'approved' => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
                                                'reserved' => 'bg-sky-100 text-sky-800 ring-sky-200',
                                                'paid' => 'bg-sky-100 text-sky-800 ring-sky-200',
                                                'disputed' => 'bg-red-100 text-red-800 ring-red-200',
                                                default => 'bg-amber-100 text-amber-900 ring-amber-200',
                                            } }}">
                                            {{ __('site.affiliate_portal.commission_status_'.$row['status']) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="lg:hidden divide-y divide-gray-100">
                    @foreach ($commissions as $row)
                        <div class="px-5 py-4 space-y-1.5">
                            <p class="font-mono text-xs text-gray-500">{{ $row['payment_id'] }}</p>
                            <p class="text-sm font-semibold text-gray-900">{{ $row['paid_for'] }} · {{ $row['member_no'] }}</p>
                            <p class="text-sm text-gray-700">{{ $row['payment_amount'] !== null ? format_money($row['payment_amount']) : '—' }}
                                · {{ __('site.affiliate_portal.col_your_commission') }} {{ format_money($row['commission']) }}</p>
                            <p class="text-xs text-gray-500">{{ optional($row['date'])->timezone(config('app.timezone'))->translatedFormat('d M Y') }}
                                · {{ __('site.affiliate_portal.commission_status_'.$row['status']) }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div x-show="tab === 'withdrawals'" x-cloak>
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
                                <th class="px-5 py-3">{{ __('site.affiliate_portal.col_settled') }}</th>
                                <th class="px-5 py-3">{{ __('site.affiliate_portal.col_payout_payment_id') }}</th>
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
                                    <td class="px-5 py-3 text-xs text-gray-600">{{ $request->paid_at?->timezone(config('app.timezone'))->translatedFormat('d M Y') ?: '—' }}</td>
                                    <td class="px-5 py-3 font-mono text-xs">{{ $request->payoutPaymentId() }}</td>
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
        </div>
    </div>

</x-site.affiliate-layout>
