<x-admin.layout title="Affiliate Partners" heading="Affiliate Partners" subheading="Marketing partners with tracked referral links, discounts, and commission">

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-600 max-w-2xl">
            Affiliate partners receive unique codes and links. Track clicks, registrations, and application conversions from each partner profile.
        </p>
        <a href="{{ route('admin.partners.create', ['category' => 'affiliate']) }}"
           class="inline-flex bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-2 rounded-lg text-sm">
            + New affiliate partner
        </a>
    </div>

    @php $leaderboard = app(\App\Services\AffiliateEvaluationService::class)->leaderboard(10); @endphp
    @if ($leaderboard->isNotEmpty())
        <div class="mb-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Affiliate leaderboard</h3>
            <p class="text-xs text-gray-500 mb-4">Ranked by latest KPI score from monthly evaluation. Run <span class="font-mono">php artisan affiliate:evaluate</span> to refresh.</p>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-xs uppercase text-gray-500">
                        <tr>
                            <th class="text-left py-2 pr-4">Rank</th>
                            <th class="text-left py-2 pr-4">Partner</th>
                            <th class="text-left py-2 pr-4">KPI</th>
                            <th class="text-left py-2 pr-4">Risk</th>
                            <th class="text-left py-2">Lifecycle</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($leaderboard as $row)
                            @php $snap = $row->affiliate_evaluation_snapshot ?? []; @endphp
                            <tr>
                                <td class="py-2 pr-4 font-bold">#{{ $row->affiliate_leaderboard_rank }}</td>
                                <td class="py-2 pr-4">
                                    <a href="{{ route('admin.partners.show', $row) }}" class="text-amber-700 hover:underline">{{ $row->name }}</a>
                                </td>
                                <td class="py-2 pr-4">{{ number_format((float) ($snap['kpi_score'] ?? 0), 1) }}</td>
                                <td class="py-2 pr-4">{{ number_format((float) ($snap['risk_score'] ?? 0), 1) }}</td>
                                <td class="py-2">{{ app(\App\Services\AffiliateLifecycleService::class)->label(app(\App\Services\AffiliateLifecycleService::class)->statusFor($row)) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @livewire('admin.partners-table', ['category' => 'affiliate', 'lockCategory' => true, 'affiliateMode' => true])
</x-admin.layout>
