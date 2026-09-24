<x-site.supplier-layout :title="__('site.supplier_portal.requests_title')" active="requests">
    @php
        $reservations = $reservations ?? collect();
        $deals = $deals ?? collect();
    @endphp

    @if ($deals->isEmpty())
        <x-site.empty-state
            icon="👥"
            :title="__('site.supplier_portal.requests_empty_title')"
            :description="__('site.supplier_portal.requests_empty_desc')"
        />
    @else
        <div class="hidden sm:block glass-card rounded-2xl ring-1 ring-brand/10 overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-brand-muted/30 text-left text-xs uppercase tracking-widest text-brand">
                    <tr>
                        <th class="px-4 py-3 font-semibold">{{ __('site.supplier_portal.buyer_col') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('site.supplier_portal.recent_col_asset') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('site.supplier_portal.recent_col_status') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('site.supplier_portal.buyer_collected') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('site.supplier_portal.buyer_remaining') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($deals as $deal)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $deal['buyer'] }}</td>
                            <td class="px-4 py-3">{{ $deal['asset'] }}</td>
                            <td class="px-4 py-3">{{ $deal['deposit_label'] }}</td>
                            <td class="px-4 py-3 font-semibold tabular-nums">{{ format_money($deal['collected']) }}</td>
                            <td class="px-4 py-3 font-semibold tabular-nums">{{ format_money($deal['remaining']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="sm:hidden space-y-2">
            @foreach ($deals as $deal)
                <div class="rounded-2xl bg-white ring-1 ring-gray-200 px-4 py-3">
                    <p class="font-semibold text-gray-900">{{ $deal['buyer'] }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $deal['asset'] }} · {{ $deal['deposit_label'] }}</p>
                    <p class="text-xs text-gray-600 mt-2 tabular-nums">
                        {{ __('site.supplier_portal.buyer_collected') }}: {{ format_money($deal['collected']) }}
                    </p>
                    <p class="text-xs text-gray-600 mt-0.5 tabular-nums">
                        {{ __('site.supplier_portal.buyer_remaining') }}: {{ format_money($deal['remaining']) }}
                    </p>
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $reservations->links() }}</div>
    @endif
</x-site.supplier-layout>
