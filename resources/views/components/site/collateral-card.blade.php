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
        $icons = $typeIcons ?: \App\Models\CustomerAsset::typeIcons();
    @endphp
    <div class="rounded-2xl ring-1 ring-brand/15 overflow-hidden bg-brand-muted/20">
        <div class="flex gap-3 sm:gap-4 p-3.5 sm:p-4 items-start">
            <div class="shrink-0 size-16 sm:size-20 rounded-xl overflow-hidden bg-white ring-1 ring-gray-200">
                @if (! empty($selected['thumbnail']))
                    <img src="{{ $selected['thumbnail'] }}" alt="" class="h-full w-full object-cover">
                @else
                    <span class="h-full w-full grid place-items-center text-2xl">{{ $icons[$selected['asset_type'] ?? ''] ?? '📦' }}</span>
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
                    @elseif (! empty($selected['status_label']))
                        <span class="inline-flex items-center rounded-full bg-amber-50 text-amber-900 text-[11px] font-bold px-2.5 py-1 ring-1 ring-amber-200/80 shrink-0">
                            {{ $selected['status_label'] }}
                        </span>
                    @endif
                </div>
                @if (! empty($selected['registration_number']))
                    <p class="text-xs sm:text-sm font-semibold text-gray-600 mt-0.5 truncate">{{ __('borrower.profile.collateral_fields.registration_number') }}: {{ $selected['registration_number'] }}</p>
                @endif
                @if (! empty($selected['make']) || ! empty($selected['year']))
                    <p class="text-xs sm:text-sm font-semibold text-gray-600 mt-0.5 truncate">
                        @if (! empty($selected['make'])){{ __('borrower.profile.collateral_fields.make') }}: {{ $selected['make'] }}@endif
                        @if (! empty($selected['make']) && ! empty($selected['year'])) · @endif
                        @if (! empty($selected['year'])){{ __('borrower.profile.collateral_fields.year') }}: {{ $selected['year'] }}@endif
                    </p>
                @endif
                @if (! empty($selected['chassis']))
                    <p class="text-xs sm:text-sm font-semibold text-gray-600 mt-0.5 truncate">{{ __('borrower.profile.collateral_fields.chassis_number') }}: {{ $selected['chassis'] }}</p>
                @endif
                @if (! empty($selected['belongs_to']))
                    <p class="text-xs font-semibold text-gray-700 mt-0.5 truncate">{{ __('site.partner_portal.valuation_belongs_to') }}: {{ $selected['belongs_to'] }}</p>
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
                {{ $slot }}
            </div>
        </div>
    </div>
@endif
