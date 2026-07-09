@props([
    'applyRequirements' => null,
    'variant' => 'hint',
])

@if ($applyRequirements && ! ($applyRequirements['can_apply'] ?? true))
    @php
        $firstIncomplete = $applyRequirements['first_incomplete'] ?? null;
        $firstActionUrl = $applyRequirements['first_action_url'] ?? null;
    @endphp
    <div {{ $attributes->merge(['class' => 'rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-4 text-sm text-amber-900']) }}>
        <p class="font-semibold">
            {{ $variant === 'submit' ? __('borrower.apply.kyc_incomplete_submit') : __('borrower.apply.kyc_incomplete_title') }}
        </p>
        @if ($variant !== 'submit')
            <p class="mt-1 text-amber-800">{{ __('borrower.apply.kyc_incomplete_hint') }}</p>
        @else
            <p class="mt-1 text-amber-800">{{ __('borrower.apply.kyc_incomplete_submit_hint') }}</p>
        @endif
        <ul class="mt-2 space-y-1 text-amber-800">
            @foreach (($applyRequirements['items'] ?? []) as $item)
                @if (! ($item['complete'] ?? false) && ($item['key'] ?? null) !== 'face_approval')
                    <li class="flex items-start gap-2">
                        <span class="shrink-0">•</span>
                        <span>
                            {{ $item['label'] }}
                            @if (! empty($item['detail']))
                                <span class="block text-xs text-brand mt-0.5">{{ $item['detail'] }}</span>
                            @endif
                            @if (! empty($item['action_url']))
                                — <a href="{{ $item['action_url'] }}" class="font-semibold underline">{{ __('borrower.apply.details.complete_missing') }}</a>
                            @endif
                        </span>
                    </li>
                @endif
            @endforeach
        </ul>
        @if ($variant === 'submit' && $firstActionUrl)
            <a href="{{ $firstActionUrl }}" class="inline-flex mt-3 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-2.5 rounded-full text-xs">
                @if (! empty($firstIncomplete['label']))
                    {{ __('borrower.apply.kyc_complete_section_cta', ['section' => $firstIncomplete['label']]) }}
                @else
                    {{ __('borrower.apply.details.complete_missing') }}
                @endif
            </a>
        @endif
    </div>
@endif
