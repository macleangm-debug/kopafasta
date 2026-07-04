@if (! empty($profile['handover_milestones']))
    @php $handover = $profile['handover_milestones']; @endphp
    <div class="glass-card p-5 mb-6">
        <div class="flex items-start justify-between gap-3 mb-4 flex-wrap">
            <div>
                <h2 class="font-semibold">{{ __('borrower.handover_milestones.title') }}</h2>
                <p class="text-xs text-gray-500 mt-0.5">{{ $handover['asset_title'] }}</p>
            </div>
            @if ($handover['complete'] ?? false)
                <span class="text-xs font-semibold rounded-full px-2.5 py-1 bg-emerald-100 text-emerald-800">{{ __('borrower.handover_milestones.complete_badge') }}</span>
            @endif
        </div>

        <ol class="space-y-3">
            @foreach ($handover['milestones'] as $milestone)
                @php
                    $dotClass = match ($milestone['status']) {
                        'completed'   => 'bg-emerald-500',
                        'in_progress' => 'bg-amber-500 ring-4 ring-amber-100',
                        default         => 'bg-gray-200',
                    };
                    $textClass = match ($milestone['status']) {
                        'completed'   => 'text-emerald-800',
                        'in_progress' => 'text-amber-900 font-semibold',
                        default         => 'text-gray-500',
                    };
                @endphp
                <li class="flex items-start gap-3">
                    <span class="size-3 rounded-full shrink-0 mt-1.5 {{ $dotClass }}"></span>
                    <div class="min-w-0">
                        <p class="text-sm {{ $textClass }}">{{ $milestone['label'] }}</p>
                        @if (! empty($milestone['detail']))
                            <p class="text-xs text-gray-500 mt-0.5">{{ $milestone['detail'] }}</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
@endif
