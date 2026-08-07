@props(['kpi', 'wallet' => null, 'compact' => false])

@if ($kpi)
    @php
        $statCards = array_values(array_filter([
            ((int) ($kpi['assigned_cases'] ?? 0) > 0)
                ? [__('site.partner_portal.recovery_stat_assigned'), $kpi['assigned_cases']]
                : null,
            ((int) ($kpi['recovered_cases'] ?? 0) > 0)
                ? [__('site.partner_portal.recovery_stat_recovered'), $kpi['recovered_cases']]
                : null,
            ((float) ($kpi['recovery_rate'] ?? 0) > 0)
                ? [__('site.partner_portal.recovery_stat_rate'), ($kpi['recovery_rate'] ?? 0).'%']
                : null,
            ((float) ($kpi['commission_earned'] ?? 0) > 0)
                ? [__('site.partner_portal.recovery_stat_commission'), format_money($kpi['commission_earned'] ?? 0)]
                : null,
            isset($kpi['avg_resolution_days'])
                ? [__('site.partner_portal.recovery_stat_avg'), $kpi['avg_resolution_days'].' '.__('site.partner_portal.recovery_days')]
                : null,
        ]));
        $walletHasValue = $wallet && (
            (float) ($wallet['pending'] ?? 0) > 0
            || (float) ($wallet['approved'] ?? 0) > 0
            || (float) ($wallet['paid'] ?? 0) > 0
            || (float) ($wallet['disputed'] ?? 0) > 0
        );
    @endphp

    @if (count($statCards) > 0 || $walletHasValue)
        <div class="{{ $compact ? 'mb-5' : 'mb-6' }} rounded-2xl border border-brand/200 bg-gradient-to-br from-indigo-50 to-white p-5 sm:p-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">{{ __('site.partner_portal.recovery_kpi_title') }}</h2>
                    <p class="text-sm text-gray-600">{{ __('site.partner_portal.recovery_kpi_subtitle') }}</p>
                </div>
                <a href="{{ route('site.partner.recovery-wallet') }}" class="text-sm font-semibold text-brand hover:underline shrink-0">
                    {{ __('site.partner_portal.recovery_kpi_wallet') }}
                </a>
            </div>

            @if (count($statCards) > 0)
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                    @foreach ($statCards as [$label, $value])
                        <div class="rounded-xl bg-white ring-1 ring-brand/100 p-3">
                            <p class="text-[11px] uppercase tracking-wide text-gray-500">{{ $label }}</p>
                            <p class="text-lg font-bold text-gray-900 mt-1">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($walletHasValue)
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4 pt-4 border-t border-brand/100">
                    @if ((float) ($wallet['pending'] ?? 0) > 0)
                        <div class="rounded-lg bg-white/80 ring-1 ring-gray-100 px-3 py-2 text-sm">
                            <p class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('site.partner_portal.recovery_wallet_pending') }}</p>
                            <p class="font-bold text-amber-700">{{ format_money($wallet['pending'] ?? 0) }}</p>
                        </div>
                    @endif
                    @if ((float) ($wallet['approved'] ?? 0) > 0)
                        <div class="rounded-lg bg-white/80 ring-1 ring-gray-100 px-3 py-2 text-sm">
                            <p class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('site.partner_portal.recovery_wallet_approved') }}</p>
                            <p class="font-bold text-blue-700">{{ format_money($wallet['approved'] ?? 0) }}</p>
                        </div>
                    @endif
                    @if ((float) ($wallet['paid'] ?? 0) > 0)
                        <div class="rounded-lg bg-white/80 ring-1 ring-gray-100 px-3 py-2 text-sm">
                            <p class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('site.partner_portal.recovery_wallet_paid') }}</p>
                            <p class="font-bold text-emerald-700">{{ format_money($wallet['paid'] ?? 0) }}</p>
                        </div>
                    @endif
                    @if ((float) ($wallet['disputed'] ?? 0) > 0)
                        <div class="rounded-lg bg-white/80 ring-1 ring-gray-100 px-3 py-2 text-sm">
                            <p class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('site.partner_portal.recovery_wallet_disputed') }}</p>
                            <p class="font-bold text-red-700">{{ format_money($wallet['disputed'] ?? 0) }}</p>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    @endif
@endif
