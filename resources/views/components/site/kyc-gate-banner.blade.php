@props([
    'applyRequirements' => null,
    'variant' => 'hint',
])

@if ($applyRequirements && ! ($applyRequirements['can_apply'] ?? true))
    <div {{ $attributes->merge(['class' => 'rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-4 text-sm text-amber-900']) }}>
        <p class="font-semibold">
            {{ $variant === 'submit' ? __('borrower.apply.kyc_incomplete_submit') : __('borrower.apply.kyc_incomplete_title') }}
        </p>
        @if ($variant !== 'submit')
            <p class="mt-1 text-amber-800">{{ __('borrower.apply.kyc_incomplete_hint') }}</p>
        @endif
        <ul class="mt-2 space-y-1 text-amber-800">
            @foreach (($applyRequirements['items'] ?? []) as $item)
                @if (! ($item['complete'] ?? false))
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
        @if ($variant === 'submit' && ($applyRequirements['first_action_url'] ?? null))
            <a href="{{ $applyRequirements['first_action_url'] }}" class="inline-flex mt-3 bg-white hover:bg-gray-50 text-gray-900 font-semibold px-4 py-2 rounded-full text-xs ring-1 ring-amber-300">
                {{ __('borrower.apply.details.complete_missing') }}
            </a>
        @endif
    </div>
@endif
