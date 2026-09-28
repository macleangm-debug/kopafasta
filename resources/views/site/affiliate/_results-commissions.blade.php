<div class="px-5 py-3 border-b border-gray-100">
    <h2 class="font-semibold text-gray-900">{{ __('site.affiliate_portal.commission_transactions') }}</h2>
</div>
@if ($commissions->isEmpty())
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
                    <th class="px-5 py-3 lg:text-right">{{ __('site.affiliate_portal.col_payment_amount') }}</th>
                    <th class="px-5 py-3 lg:text-right">{{ __('site.affiliate_portal.col_your_commission') }}</th>
                    <th class="px-5 py-3">{{ __('site.affiliate_portal.col_commission_status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($commissions as $row)
                    <tr>
                        <td class="px-5 py-3 font-mono text-xs">{{ $row['payment_id'] }}</td>
                        <td class="px-5 py-3 text-xs text-gray-600">{{ optional($row['date'])->timezone(config('app.timezone'))->translatedFormat('d M Y') }}</td>
                        <td class="px-5 py-3 font-mono text-xs">{{ $row['member_no'] }}</td>
                        <td class="px-5 py-3">{{ $row['paid_for'] }}</td>
                        <td class="px-5 py-3 tabular-nums lg:text-right">{{ $row['payment_amount'] !== null ? format_money($row['payment_amount']) : '—' }}</td>
                        <td class="px-5 py-3 font-semibold tabular-nums lg:text-right">{{ format_money($row['commission']) }}</td>
                        <td class="px-5 py-3">
                            <span class="inline-flex text-[10px] font-bold uppercase tracking-wide rounded-full px-2.5 py-1 ring-1
                                {{ match($row['status']) {
                                    'complete', 'approved', 'earned' => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
                                    'reserved' => 'bg-sky-100 text-sky-800 ring-sky-200',
                                    'paid' => 'bg-sky-100 text-sky-800 ring-sky-200',
                                    'disputed', 'reversed' => 'bg-red-100 text-red-800 ring-red-200',
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
    <div class="lg:hidden space-y-3 p-4">
        @foreach ($commissions as $row)
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 px-4 py-4 space-y-1.5">
                <div class="flex items-start justify-between gap-3">
                    <p class="font-mono text-xs text-gray-500">{{ $row['payment_id'] }}</p>
                    <span class="inline-flex text-[10px] font-bold uppercase tracking-wide rounded-full px-2.5 py-1 ring-1 shrink-0
                        {{ match($row['status']) {
                            'complete', 'approved', 'earned' => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
                            'reserved' => 'bg-sky-100 text-sky-800 ring-sky-200',
                            'paid' => 'bg-sky-100 text-sky-800 ring-sky-200',
                            'disputed', 'reversed' => 'bg-red-100 text-red-800 ring-red-200',
                            default => 'bg-amber-100 text-amber-900 ring-amber-200',
                        } }}">
                        {{ __('site.affiliate_portal.commission_status_'.$row['status']) }}
                    </span>
                </div>
                <p class="text-sm font-semibold text-gray-900">{{ $row['paid_for'] }} · {{ $row['member_no'] }}</p>
                <p class="text-sm text-gray-700">{{ __('site.affiliate_portal.mobile_paid', ['amount' => $row['payment_amount'] !== null ? format_money($row['payment_amount']) : '—']) }}</p>
                <p class="text-sm text-gray-700">{{ __('site.affiliate_portal.mobile_commission', ['amount' => format_money($row['commission'])]) }}</p>
                <p class="text-xs text-gray-500">{{ optional($row['date'])->timezone(config('app.timezone'))->translatedFormat('d M Y') }}</p>
            </div>
        @endforeach
    </div>
    @if ($commissions->hasPages())
        <div class="px-5 py-3 border-t border-gray-100">{{ $commissions->appends(['tab' => 'commissions'])->links() }}</div>
    @endif
@endif
