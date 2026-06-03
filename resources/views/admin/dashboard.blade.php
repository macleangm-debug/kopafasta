<x-admin.layout title="Dashboard" heading="Dashboard" subheading="Overview of operations">

    {{-- KPI cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        @php
            $cards = [
                ['Customers',     format_number($stats['customers']),    'bg-blue-500'],
                ['Applications',  format_number($stats['applications']), 'bg-violet-500'],
                ['Active Loans',  format_number($stats['active_loans']), 'bg-amber-500'],
                ['Portfolio',     'TZS '.format_number($stats['portfolio_tzs'] / 1_000_000, 1).'M', 'bg-emerald-500'],
            ];
        @endphp

        @foreach ($cards as [$label, $value, $accent])
            <div class="bg-white rounded-xl p-5 shadow-sm ring-1 ring-gray-200 relative overflow-hidden">
                <div class="absolute top-0 left-0 w-1 h-full {{ $accent }}"></div>
                <div class="pl-2">
                    <div class="text-xs font-medium uppercase tracking-wider text-gray-500">{{ $label }}</div>
                    <div class="mt-2 text-2xl font-bold text-gray-900">{{ $value }}</div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Recent applications --}}
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-900">Recent Applications</h2>
            <a href="{{ route('admin.loans.index') }}" class="text-xs font-medium text-amber-600 hover:text-amber-700">View all →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-5 py-2.5">Reference</th>
                        <th class="px-5 py-2.5">Customer</th>
                        <th class="px-5 py-2.5">Amount</th>
                        <th class="px-5 py-2.5">Status</th>
                        <th class="px-5 py-2.5">Submitted</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($recentApplications as $app)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 font-mono text-xs">{{ $app->application_number ?? $app->id }}</td>
                            <td class="px-5 py-3">
                                {{ trim(($app->customer?->first_name ?? '').' '.($app->customer?->last_name ?? '')) ?: '—' }}
                            </td>
                            <td class="px-5 py-3">{{ format_money((float) ($app->requested_amount ?? 0)) }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    @class([
                                        'bg-emerald-100 text-emerald-800' => $app->status === 'approved',
                                        'bg-red-100 text-red-800'         => $app->status === 'rejected',
                                        'bg-amber-100 text-amber-800'     => in_array($app->status, ['pending','submitted','under_review']),
                                        'bg-gray-100 text-gray-800'       => ! in_array($app->status, ['approved','rejected','pending','submitted','under_review']),
                                    ])">
                                    {{ display_label($app->status, 'application_status') }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-gray-500">{{ $app->created_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-gray-500">No applications yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</x-admin.layout>
