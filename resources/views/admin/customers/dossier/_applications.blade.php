@php($draftService = app(\App\Services\LoanApplicationDraftService::class))

<x-admin.review-section id="customer-applications" title="Loan applications" subtitle="Application history for this borrower">

    @if (($dossier['application_drafts'] ?? collect())->isNotEmpty())
        <div class="mb-6 rounded-xl bg-sky-50 ring-1 ring-sky-200 p-4">
            <h3 class="text-sm font-semibold text-sky-900 mb-3">{{ __('admin.application_drafts.dossier_heading') }}</h3>
            <div class="overflow-x-auto -mx-2 px-2">
                <table class="min-w-full text-sm">
                    <thead class="text-xs uppercase tracking-wider text-sky-800/70 border-b border-sky-200">
                        <tr>
                            <th class="py-2 text-left font-semibold">Product</th>
                            <th class="py-2 text-right font-semibold">Amount</th>
                            <th class="py-2 text-left font-semibold">Progress</th>
                            <th class="py-2 text-left font-semibold">Status</th>
                            <th class="py-2 text-left font-semibold">Last activity</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sky-200/60">
                        @foreach ($dossier['application_drafts'] as $draft)
                            @php
                                $badge = $draftService->statusBadge($draft);
                                $toneMap = [
                                    'amber'  => 'bg-amber-100 text-amber-800',
                                    'blue'   => 'bg-blue-100 text-blue-800',
                                    'purple' => 'bg-purple-100 text-purple-800',
                                    'gray'   => 'bg-gray-100 text-gray-700',
                                ];
                            @endphp
                            <tr>
                                <td class="py-2">{{ $draft->product?->name ?? '—' }}</td>
                                <td class="py-2 text-right font-medium">
                                    @if ($amount = $draftService->requestedAmount($draft))
                                        {{ format_money($amount) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-2 text-gray-700">{{ $draftService->progressLabel($draft) }}</td>
                                <td class="py-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $toneMap[$badge['tone']] ?? 'bg-gray-100 text-gray-700' }}">
                                        {{ $badge['label'] }}
                                    </span>
                                </td>
                                <td class="py-2 text-gray-500 whitespace-nowrap">{{ $draft->saved_at?->diffForHumans() ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-sky-800">
                <a href="{{ route('admin.loan-applications.incomplete') }}" class="font-semibold hover:underline">{{ __('admin.application_drafts.title') }} →</a>
            </p>
        </div>
    @endif

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
