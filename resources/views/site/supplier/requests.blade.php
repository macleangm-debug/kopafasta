<x-site.supplier-layout :title="__('site.supplier_portal.requests_title')" active="requests">
    @php
        $status = app(\App\Services\SupplierPortalHomeService::class);
        $reservations = $reservations ?? collect();
        $hasAssigned = isset($requests) && ! $requests->isEmpty();
        $hasJourney = ! $reservations->isEmpty();
    @endphp

    <x-site.borrower-page-header
        :eyebrow="__('site.supplier_portal.title')"
        :title="__('site.supplier_portal.requests_title')"
        :subtitle="__('site.supplier_portal.requests_subtitle')"
    />

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    @if (! $hasAssigned && ! $hasJourney)
        <x-site.empty-state
            icon="📋"
            :title="__('site.supplier_portal.requests_empty_title')"
            :description="__('site.supplier_portal.requests_empty_desc')"
        />
    @endif

    @if ($hasAssigned)
        <h2 class="font-bold text-gray-900 mb-3">{{ __('site.supplier_portal.requests_assigned') }}</h2>
        <div class="hidden sm:block glass-card rounded-2xl ring-1 ring-brand/10 overflow-hidden mb-6">
            <table class="min-w-full text-sm">
                <thead class="bg-brand-muted/30 text-left text-xs uppercase tracking-widest text-brand">
                    <tr>
                        <th class="px-4 py-3 font-semibold">{{ __('site.supplier_portal.recent_col_asset') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('site.supplier_portal.recent_col_status') }}</th>
                        <th class="px-4 py-3 font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($requests as $row)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $row->asset_name }}</td>
                            <td class="px-4 py-3">{{ $status->requestStatusLabel((string) $row->status) }}</td>
                            <td class="px-4 py-3">
                                @if ($row->status === 'reviewing')
                                    <div class="flex flex-wrap gap-3">
                                        <form method="POST" action="{{ route('site.supplier.requests.update', $row) }}">
                                            @csrf
                                            <input type="hidden" name="action" value="accept">
                                            <button class="text-xs font-semibold text-emerald-700 hover:underline">Accept</button>
                                        </form>
                                        <form method="POST" action="{{ route('site.supplier.requests.update', $row) }}">
                                            @csrf
                                            <input type="hidden" name="action" value="decline">
                                            <button class="text-xs font-semibold text-red-700 hover:underline">Decline</button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="sm:hidden space-y-2 mb-6">
            @foreach ($requests as $row)
                <div class="rounded-2xl bg-white ring-1 ring-gray-200 px-4 py-3">
                    <p class="font-semibold text-gray-900">{{ $row->asset_name }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $status->requestStatusLabel((string) $row->status) }}</p>
                </div>
            @endforeach
        </div>
        <div class="mb-6">{{ $requests->links() }}</div>
    @endif

    @if ($hasJourney)
        <h2 class="font-bold text-gray-900 mb-3">{{ __('site.supplier_portal.requests_journey') }}</h2>
        <div class="hidden sm:block glass-card rounded-2xl ring-1 ring-brand/10 overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-brand-muted/30 text-left text-xs uppercase tracking-widest text-brand">
                    <tr>
                        <th class="px-4 py-3 font-semibold">{{ __('site.supplier_portal.recent_col_asset') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('site.supplier_portal.recent_col_reference') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('site.supplier_portal.recent_col_status') }}</th>
                        <th class="px-4 py-3 font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($reservations as $row)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $row->asset?->title }}</td>
                            <td class="px-4 py-3">{{ $row->customer?->member_no ?: ($row->loanApplication?->application_number ?: '—') }}</td>
                            <td class="px-4 py-3">{{ $status->requestStatusLabel((string) $row->status) }}</td>
                            <td class="px-4 py-3">
                                @if ($row->status === 'viewing_scheduled')
                                    <form method="POST" action="{{ route('site.supplier.reservations.update', $row) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="action" value="confirm_viewing">
                                        <button class="text-xs font-semibold text-brand hover:underline mr-2">Acknowledge</button>
                                    </form>
                                    <form method="POST" action="{{ route('site.supplier.reservations.update', $row) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="action" value="complete_viewing">
                                        <button class="text-xs font-semibold text-emerald-700 hover:underline">Mark viewed</button>
                                    </form>
                                @elseif ($row->status === 'post_approval_fees_paid')
                                    <form method="POST" action="{{ route('site.supplier.reservations.update', $row) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="action" value="gps_installation">
                                        <button class="text-xs font-semibold text-brand hover:underline">GPS installed</button>
                                    </form>
                                @elseif ($row->status === 'gps_installation')
                                    <form method="POST" action="{{ route('site.supplier.reservations.update', $row) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="action" value="insurance_active">
                                        <button class="text-xs font-semibold text-brand hover:underline">Insurance active</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="sm:hidden space-y-2">
            @foreach ($reservations as $row)
                <div class="rounded-2xl bg-white ring-1 ring-gray-200 px-4 py-3">
                    <p class="font-semibold text-gray-900">{{ $row->asset?->title }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $status->requestStatusLabel((string) $row->status) }}</p>
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $reservations->links() }}</div>
    @endif
</x-site.supplier-layout>
