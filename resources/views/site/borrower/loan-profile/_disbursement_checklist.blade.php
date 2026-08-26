@props(['checklist' => [], 'nextAction' => null, 'identityComplete' => false])

@php
    $items = collect($checklist);
    $ordered = collect();
    $ordered->push([
        'key' => 'offer',
        'label' => __('borrower.contract.checklist.offer'),
        'complete' => (bool) ($items['offer']['complete'] ?? false),
    ]);
    $ordered->push([
        'key' => 'identity',
        'label' => __('borrower.contract.checklist.identity'),
        'complete' => (bool) $identityComplete,
    ]);
    if ($items->has('post_approval_fee')) {
        $fee = $items['post_approval_fee'];
        $ordered->push([
            'key' => 'post_approval_fee',
            'label' => __('borrower.contract.checklist.post_approval_fee'),
            'complete' => (bool) ($fee['complete'] ?? false) || ($fee['status'] ?? '') === 'not_required',
        ]);
    }
    if ($items->has('destination')) {
        $dest = $items['destination'];
        $ordered->push([
            'key' => 'destination',
            'label' => __('borrower.contract.checklist.destination'),
            'complete' => (bool) ($dest['complete'] ?? false) || ($dest['status'] ?? '') === 'not_required',
        ]);
    }
    if ($items->has('contract')) {
        $ordered->push([
            'key' => 'contract',
            'label' => __('borrower.contract.checklist.contract'),
            'complete' => (bool) ($items['contract']['complete'] ?? false),
        ]);
    }
    $cta = is_array($nextAction) ? $nextAction : null;
@endphp

@if ($ordered->isNotEmpty())
    <div class="glass-card p-5 mb-6">
        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.celebration.post_approval_fee_title') }}</p>
        <h2 class="font-semibold text-gray-900 mt-1 mb-4">{{ __('borrower.contract.checklist.almost_there') }}</h2>
        <ul class="space-y-2 text-sm">
            @foreach ($ordered as $item)
                <li class="flex items-center justify-between gap-3 {{ ($item['complete'] ?? false) ? 'text-emerald-700' : 'text-gray-700' }}">
                    <span class="font-medium">{{ $item['label'] }}</span>
                    <span class="text-xs font-semibold">
                        {{ ($item['complete'] ?? false) ? '✓' : '○' }}
                    </span>
                </li>
            @endforeach
        </ul>
        @if ($cta && filled($cta['url'] ?? null) && ! in_array($cta['code'] ?? '', ['awaiting_management', 'under_review'], true))
            <a href="{{ $cta['url'] }}"
               class="mt-5 inline-flex w-full sm:w-auto justify-center items-center rounded-xl bg-brand-gold hover:brightness-95 text-brand font-bold px-6 py-3 text-sm shadow-sm">
                {{ $cta['button_label'] ?? __('borrower.contract.checklist.continue') }}
            </a>
        @endif
    </div>
@endif
