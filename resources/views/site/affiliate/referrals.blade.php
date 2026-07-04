<x-site.affiliate-layout :title="brand_title(__('site.affiliate_portal.referrals_title'))" active="referrals">

    <div class="mb-6">
        <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">{{ __('site.affiliate_portal.nav_referrals') }}</p>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mt-1">{{ __('site.affiliate_portal.referrals_title') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ __('site.affiliate_portal.referrals_subtitle') }}</p>
    </div>

    @if ($events->isEmpty())
        <x-site.empty-state
            icon="👥"
            :title="__('site.affiliate_portal.no_referrals_title')"
            :description="__('site.affiliate_portal.no_referrals_body')"
            :action-label="__('site.affiliate_portal.go_dashboard')"
            :action-url="route('site.affiliate.dashboard')"
        />
    @else
        <div class="glass-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-widest text-gray-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('site.affiliate_portal.col_event') }}</th>
                            <th class="px-4 py-3">{{ __('site.affiliate_portal.col_customer') }}</th>
                            <th class="px-4 py-3">{{ __('site.affiliate_portal.col_commission') }}</th>
                            <th class="px-4 py-3">{{ __('site.affiliate_portal.col_date') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($events as $event)
                            <tr class="hover:bg-gray-50/50">
                                <td class="px-4 py-3 font-medium capitalize">{{ str_replace('_', ' ', $event->event_type) }}</td>
                                <td class="px-4 py-3 text-gray-600">
                                    @if ($event->customer)
                                        {{ trim($event->customer->first_name.' '.$event->customer->last_name) ?: '—' }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 tabular-nums">
                                    {{ $event->commission_amount > 0 ? format_money($event->commission_amount) : '—' }}
                                </td>
                                <td class="px-4 py-3 text-gray-500">{{ $event->created_at?->format('d M Y, H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($events->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">{{ $events->links() }}</div>
            @endif
        </div>
    @endif

</x-site.affiliate-layout>
