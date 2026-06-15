<x-site.supplier-layout title="Loan applications" active="applications">
    <h1 class="text-2xl font-bold mb-6">Loan applications</h1>
    <p class="text-sm text-gray-600 mb-4">Applications linked to your marketplace assets.</p>
    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Application</th>
                    <th class="px-4 py-3">Borrower</th>
                    <th class="px-4 py-3">Asset</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Reservation</th>
                    <th class="px-4 py-3">Outstanding</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($applications as $app)
                    @php
                        $loan = $app->loan;
                        $outstanding = $loan && in_array($loan->status, ['active', 'disbursed', 'arrears'], true)
                            ? app(\App\Services\LoanBalanceService::class)->breakdown($loan)['total_outstanding']
                            : null;
                    @endphp
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $app->application_number }}</td>
                        <td class="px-4 py-3">{{ $app->customer?->full_name }}</td>
                        <td class="px-4 py-3">{{ $app->assetReservation?->asset?->title }}</td>
                        <td class="px-4 py-3">{{ display_label($app->status, 'application_status') ?: ucfirst($app->status) }}</td>
                        <td class="px-4 py-3">{{ str_replace('_', ' ', ucfirst($app->assetReservation?->status ?? '—')) }}</td>
                        <td class="px-4 py-3 font-semibold">{{ $outstanding !== null ? format_money($outstanding) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No linked applications yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $applications->links() }}</div>
</x-site.supplier-layout>
