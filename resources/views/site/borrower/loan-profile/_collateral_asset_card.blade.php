@props([
    'selected' => null,
    'typeIcons' => [],
    'showInsured' => false,
    'sourceLabel' => null,
])

@if ($selected)
    @php
        $isInsured = $showInsured
            || (($selected['insurance_type'] ?? null) === 'comprehensive' && filled($selected['insurance_expires_at'] ?? null));
    @endphp
    <div class="rounded-2xl ring-1 ring-brand/15 overflow-hidden bg-brand-muted/20">
        <div class="flex gap-3 sm:gap-4 p-3.5 sm:p-4 items-center">
            <div class="shrink-0 size-16 sm:size-20 rounded-xl overflow-hidden bg-white ring-1 ring-gray-200">
                @if (! empty($selected['thumbnail']))
                    <img src="{{ $selected['thumbnail'] }}" alt="" class="h-full w-full object-cover">
                @else
                    <span class="h-full w-full grid place-items-center text-2xl">{{ $typeIcons[$selected['asset_type'] ?? ''] ?? '📦' }}</span>
                @endif
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-widest text-brand font-bold">{{ $selected['type_label'] ?? '' }}</p>
                        <p class="text-base sm:text-lg font-extrabold text-gray-900 mt-0.5 truncate">{{ $selected['label'] }}</p>
                    </div>
                    @if ($isInsured)
                        <span class="inline-flex items-center rounded-full bg-emerald-100 text-emerald-900 text-[11px] font-bold px-2.5 py-1 ring-1 ring-emerald-200/80 shrink-0">
                            {{ __('borrower.collateral_secure.badge_insured') }}
                        </span>
                    @endif
                </div>
                @if (! empty($selected['registration_number']))
                    <p class="text-xs sm:text-sm font-semibold text-gray-600 mt-0.5 truncate">{{ __('borrower.profile.collateral_fields.registration_number') }}: {{ $selected['registration_number'] }}</p>
                @endif
                @if ($isInsured)
                    <p class="text-xs text-emerald-800 mt-1 font-semibold">
                        {{ __('borrower.profile.insurance_comprehensive') }}
                        @if (! empty($selected['insurance_expires_at']))
                            · {{ __('borrower.collateral_secure.insurance_expires') }}: {{ $selected['insurance_expires_at'] }}
                        @endif
                    </p>
                    @if (! empty($selected['insurance_policy_number']))
                        <p class="text-[11px] text-gray-500 mt-0.5 font-mono truncate">{{ $selected['insurance_policy_number'] }}</p>
                    @endif
                @elseif (! empty($selected['insurance_type']))
                    <p class="text-xs sm:text-sm font-semibold text-gray-600 mt-0.5 truncate">
                        {{ __('borrower.profile.collateral_fields.insurance_type') }}:
                        {{ $selected['insurance_type'] === 'comprehensive'
                            ? __('borrower.profile.insurance_comprehensive')
                            : __('borrower.profile.insurance_third_party') }}
                    </p>
                @endif
                @if ($sourceLabel)
                    <p class="text-[11px] font-bold text-brand mt-1">{{ $sourceLabel }}</p>
                @endif
            </div>
        </div>
    </div>
@endif
