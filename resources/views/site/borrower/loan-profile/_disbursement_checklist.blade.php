@props(['checklist' => []])

@if (! empty($checklist))
    <div class="glass-card p-5 mb-6">
        <h2 class="font-semibold mb-4">{{ __('borrower.contract.checklist.title') }}</h2>
        <ul class="space-y-2 text-sm">
            @foreach ($checklist as $item)
                @php
                    $statusLabel = match ($item['status']) {
                        'paid', 'accepted', 'complete', 'available' => '✓ '.__('borrower.contract.checklist.complete'),
                        'pending' => '⏳ '.__('borrower.contract.checklist.pending'),
                        'insufficient' => '✗ '.__('borrower.contract.checklist.insufficient'),
                        'locked' => '🔒 '.__('borrower.contract.checklist.locked'),
                        'not_generated' => '⏳ '.__('borrower.contract.checklist.generating'),
                        'not_required' => '✓ '.__('borrower.contract.checklist.not_required'),
                        default => ucfirst(str_replace('_', ' ', $item['status'])),
                    };
                    $tone = ($item['complete'] ?? false) ? 'text-emerald-700' : 'text-gray-700';
                @endphp
                <li class="flex items-center justify-between gap-3 {{ $tone }}">
                    <span class="font-medium">{{ $item['label'] }}</span>
                    <span class="text-xs font-semibold">{{ $statusLabel }}</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
