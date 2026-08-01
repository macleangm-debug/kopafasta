<x-site.supplier-layout title="Expected payouts" active="applications">
    <div class="mb-6">
        <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Expected payouts</h1>
        <p class="text-sm text-gray-500 mt-1">What you can expect from loans linked to your marketplace assets — not the full underwriting file.</p>
    </div>
    <div class="glass-card rounded-2xl ring-1 ring-brand/10 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-brand-muted/30 text-left text-xs uppercase tracking-widest text-brand">
                <tr>
                    <th class="px-4 py-3 font-semibold">Asset</th>
                    <th class="px-4 py-3 font-semibold">Borrower</th>
                    <th class="px-4 py-3 font-semibold">Stage</th>
                    <th class="px-4 py-3 font-semibold">Expected / outstanding</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($applications as $app)
                    @php
                        $loan = $app->loan;
                        $outstanding = $loan && in_array($loan->status, ['active', 'disbursed', 'arrears'], true)
                            ? app(\App\Services\LoanBalanceService::class)->breakdown($loan)['total_outstanding']
                            : null;
                        $assetTitle = $app->assetReservation?->asset?->title ?? '—';
                    @endphp
                    <tr>
                        <td class="px-4 py-3">
                            <p class="font-semibold text-gray-900">{{ $assetTitle }}</p>
                            <p class="text-xs text-gray-500 font-mono mt-0.5">{{ $app->application_number }}</p>
                        </td>
                        <td class="px-4 py-3">{{ $app->customer?->full_name }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex text-xs font-semibold rounded-full px-2.5 py-1 bg-gray-100 text-gray-700">
                                {{ display_label($app->status, 'application_status') ?: ucfirst($app->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 font-semibold tabular-nums text-brand">
                            {{ $outstanding !== null ? format_money($outstanding) : 'Pending disbursement' }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-gray-500">No payouts linked to your assets yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $applications->links() }}</div>
</x-site.supplier-layout>
