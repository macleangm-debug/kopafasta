<x-admin.review-section id="review-guarantors" title="Guarantor review" subtitle="Guarantor exposure and approval status">
    @if ($review['guarantors']->isEmpty())
        <p class="text-sm text-gray-500">
            @if ($review['product']?->requires_guarantor)
                No guarantor linked yet — application may still be awaiting guarantor acceptance.
            @else
                This loan product does not require a guarantor.
            @endif
        </p>
    @else
        <div class="space-y-4">
            @foreach ($review['guarantors'] as $guarantor)
                @php
                    $riskClass = match ($guarantor['risk_band']) {
                        'low'    => 'bg-emerald-100 text-emerald-800',
                        'medium' => 'bg-amber-100 text-amber-800',
                        default  => 'bg-red-100 text-red-800',
                    };
                    $statusClass = match ($guarantor['status']) {
                        'approved' => 'bg-emerald-100 text-emerald-800',
                        'rejected' => 'bg-red-100 text-red-800',
                        default    => 'bg-amber-100 text-amber-800',
                    };
                @endphp
                <div class="rounded-xl ring-1 ring-gray-200 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-gray-900">{{ $guarantor['name'] ?: '—' }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                @if ($guarantor['membership_no']) Member {{ $guarantor['membership_no'] }} · @endif
                                {{ $guarantor['phone'] ?? '—' }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $statusClass }}">{{ $guarantor['status_label'] ?? ucfirst($guarantor['status']) }}</span>
                            <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $riskClass }}">{{ $guarantor['risk_label'] }}</span>
                        </div>
                    </div>
                    <dl class="grid sm:grid-cols-3 gap-3 mt-4 text-sm">
                        <div><dt class="text-xs text-gray-500">Active loans</dt><dd class="font-semibold mt-0.5">{{ $guarantor['active_loans'] }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Guarantees</dt><dd class="font-semibold mt-0.5">{{ $guarantor['guarantee_count'] ?? 0 }} / {{ $guarantor['guarantee_max'] ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Exposure</dt><dd class="font-semibold mt-0.5">{{ format_money($guarantor['guarantee_exposure'] ?? 0) }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Guaranteed loans</dt><dd class="font-semibold mt-0.5">{{ $guarantor['guaranteed_loans'] }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Relationship</dt><dd class="font-semibold mt-0.5">{{ ucfirst($guarantor['relationship'] ?? '—') }}</dd></div>
                        @if (! empty($guarantor['affordability']))
                            <div>
                                <dt class="text-xs text-gray-500">Capacity</dt>
                                <dd class="font-semibold mt-0.5 {{ ($guarantor['affordability']['verdict'] ?? '') === 'fail' ? 'text-red-700' : (($guarantor['affordability']['verdict'] ?? '') === 'warn' ? 'text-amber-700' : 'text-emerald-700') }}">
                                    {{ $guarantor['affordability']['status_label'] ?? '—' }}
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            @endforeach
        </div>
    @endif
</x-admin.review-section>
