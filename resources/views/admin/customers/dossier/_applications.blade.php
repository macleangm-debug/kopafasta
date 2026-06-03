<x-admin.review-section id="customer-applications" title="Loan applications" subtitle="Application history for this borrower">
    @if ($dossier['applications']->isEmpty())
        <p class="text-sm text-gray-500">No applications yet.
            <a href="{{ route('admin.loan-applications.create') }}?customer={{ $dossier['customer']->id }}" class="text-amber-700 font-semibold hover:text-amber-800">Create one →</a>
        </p>
    @else
        <div class="overflow-x-auto -mx-6 px-6">
            <table class="min-w-full text-sm">
                <thead class="text-xs uppercase tracking-wider text-gray-500 border-b border-gray-100">
                    <tr>
                        <th class="py-2 text-left font-semibold">Application</th>
                        <th class="py-2 text-left font-semibold">Product</th>
                        <th class="py-2 text-right font-semibold">Amount</th>
                        <th class="py-2 text-left font-semibold">Stage</th>
                        <th class="py-2 text-left font-semibold">Status</th>
                        <th class="py-2 text-right font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($dossier['applications'] as $app)
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 font-mono text-xs">{{ $app->application_number }}</td>
                            <td class="py-3">{{ $app->product?->name ?? '—' }}</td>
                            <td class="py-3 text-right font-medium">{{ format_money((float) ($app->recommended_amount ?: $app->requested_amount)) }}</td>
                            <td class="py-3">{{ display_label($app->current_stage ?? 'submitted', 'application_stage') }}</td>
                            <td class="py-3">{{ display_label($app->status, 'application_status') }}</td>
                            <td class="py-3 text-right">
                                <a href="{{ route('admin.loan-applications.show', $app) }}" class="text-xs font-semibold text-amber-700 hover:text-amber-800">Open →</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-admin.review-section>
