@props([
    'selected' => null,
    'typeIcons' => [],
    'showInsured' => false,
    'sourceLabel' => null,
    'layout' => 'default',
])

@if ($selected)
    @php
        $icons = $typeIcons ?: \App\Models\CustomerAsset::typeIcons();
        $show = $selected['show'] ?? [
            'identity' => true,
            'ownership' => true,
            'insurance' => true,
            'insurance_warning' => false,
            'valuation' => false,
            'ltv' => false,
            'valuer' => false,
        ];
        $badges = collect($selected['badges'] ?? []);
        if ($badges->isEmpty()) {
            $legacyInsured = $showInsured
                || (($selected['insurance_type'] ?? null) === 'comprehensive' && filled($selected['insurance_expires_at'] ?? null));
            if ($legacyInsured) {
                $badges = collect([['label' => __('borrower.collateral_secure.badge_insured'), 'tone' => 'emerald']]);
            } elseif (! empty($selected['status_label'])) {
                $badges = collect([['label' => $selected['status_label'], 'tone' => 'amber']]);
            }
            if ($sourceLabel) {
                $badges = $badges->push(['label' => $sourceLabel, 'tone' => 'brand']);
            }
        }
        $badgeClass = function (string $tone): string {
            return match ($tone) {
                'emerald' => 'bg-emerald-100 text-emerald-900 ring-emerald-200/80',
                'sky' => 'bg-sky-100 text-sky-950 ring-sky-200/80',
                'amber' => 'bg-amber-50 text-amber-900 ring-amber-200/80',
                'rose' => 'bg-rose-50 text-rose-900 ring-rose-200/80',
                default => 'bg-brand-muted text-brand ring-brand/20',
            };
        };
        $insuranceTypeLabel = match ($selected['insurance_type'] ?? null) {
            'comprehensive' => __('borrower.profile.insurance_comprehensive'),
            'third_party' => __('borrower.profile.insurance_third_party'),
            default => null,
        };
        $makeModel = trim(implode(' ', array_filter([
            $selected['make'] ?? null,
            $selected['model'] ?? null,
        ])));
        $isBorrower = ($selected['viewer'] ?? '') === \App\Services\CollateralCardService::VIEWER_BORROWER;
        $identityRows = [];
        if (! empty($show['identity'])) {
            if (! empty($selected['registration_number']) && $selected['registration_number'] !== '—') {
                $identityRows[] = [__('borrower.profile.collateral_fields.registration_number'), $selected['registration_number']];
            }
            if ($makeModel !== '') {
                $identityRows[] = [__('borrower.profile.collateral_fields.make'), $makeModel];
            }
            if (! empty($selected['year']) && $selected['year'] !== '—') {
                $identityRows[] = [__('borrower.profile.collateral_fields.year'), $selected['year']];
            }
            if (! empty($selected['chassis']) && $selected['chassis'] !== '—') {
                $identityRows[] = [__('borrower.profile.collateral_fields.chassis_number'), $selected['chassis']];
            } elseif (! empty($selected['serial'])) {
                $identityRows[] = [__('borrower.profile.collateral_fields.serial_number'), $selected['serial']];
            }
        }
    @endphp
    <div @class([
        'rounded-2xl ring-1 ring-brand/15 overflow-hidden',
        'bg-white' => $isBorrower,
        'bg-brand-muted/20' => ! $isBorrower,
        'h-full flex flex-col' => $layout === 'grid',
    ])>
        <div @class([
            'flex gap-4 items-start',
            'flex-col' => $isBorrower,
            'flex-col sm:flex-row p-3.5 sm:p-4' => ! $isBorrower,
        ])>
            <div @class([
                'overflow-hidden bg-brand-muted/40',
                'w-full aspect-[16/10] sm:aspect-[21/9]' => $isBorrower,
                'shrink-0 size-16 sm:size-20 rounded-xl ring-1 ring-gray-200' => ! $isBorrower,
            ])>
                @if (! empty($selected['thumbnail']))
                    <img src="{{ $selected['thumbnail'] }}" alt="" class="h-full w-full object-cover">
                @else
                    <span class="h-full w-full grid place-items-center {{ $isBorrower ? 'text-4xl' : 'text-2xl' }}">{{ $icons[$selected['asset_type'] ?? ''] ?? '📦' }}</span>
                @endif
            </div>
            <div @class([
                'min-w-0 flex-1 space-y-3',
                'px-4 pb-4' => $isBorrower,
            ])>
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-widest text-brand font-bold">{{ $selected['type_label'] ?? '' }}</p>
                        <p class="text-lg sm:text-xl font-extrabold text-gray-900 mt-0.5 truncate">{{ $selected['label'] }}</p>
                    </div>
                    @if ($badges->isNotEmpty())
                        <div class="flex flex-wrap justify-end gap-1">
                            @foreach ($badges as $badge)
                                <span class="inline-flex items-center rounded-full text-[11px] font-bold px-2.5 py-1 ring-1 shrink-0 {{ $badgeClass($badge['tone'] ?? 'brand') }}">
                                    {{ $badge['label'] }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if ($identityRows !== [])
                    <dl class="grid grid-cols-[minmax(0,8.5rem)_1fr] gap-x-3 gap-y-1.5 text-sm">
                        @foreach ($identityRows as [$rowLabel, $rowValue])
                            <dt class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ $rowLabel }}</dt>
                            <dd class="font-semibold text-gray-900 truncate">{{ $rowValue }}</dd>
                        @endforeach
                    </dl>
                @endif

                @if (! empty($show['ownership']) && (! empty($selected['owner_name']) || ! empty($selected['belongs_to']) || (! $isBorrower && ! empty($selected['ownership_status']))))
                    <div class="space-y-0.5">
                        @if (! $isBorrower && ! empty($selected['belongs_to']))
                            <p class="text-sm font-semibold text-gray-700 truncate">{{ __('site.partner_portal.valuation_belongs_to') }}: {{ $selected['belongs_to'] }}</p>
                        @elseif (! empty($selected['owner_name']))
                            <p class="text-sm font-semibold text-gray-800 truncate">{{ $selected['owner_name'] }}</p>
                            @if (! empty($selected['owner_role']))
                                <p class="text-[11px] font-bold text-brand">{{ $selected['owner_role'] }}</p>
                            @endif
                        @endif
                        @if (! $isBorrower && ! empty($selected['ownership_status']))
                            <p class="text-[11px] font-semibold text-gray-500">{{ $selected['ownership_status'] }}</p>
                        @endif
                    </div>
                @endif

                @if (! empty($show['insurance']) && (! empty($insuranceTypeLabel) || ! empty($selected['insurer']) || ! empty($selected['insurance_expires_at']) || ! empty($show['insurance_warning'])))
                    <div>
                        @if ($insuranceTypeLabel || ! empty($selected['insurer']) || ! empty($selected['insurance_expires_at']))
                            <p class="text-xs sm:text-sm font-semibold text-gray-600">
                                @if ($insuranceTypeLabel)
                                    {{ $insuranceTypeLabel }}
                                @endif
                                @if (! empty($selected['insurer']))
                                    {{ $insuranceTypeLabel ? ' · ' : '' }}{{ $selected['insurer'] }}
                                @endif
                                @if (! empty($selected['insurance_expires_at']) && empty($show['insurance_warning']))
                                    · {{ __('borrower.collateral_secure.insurance_expires') }}: {{ $selected['insurance_expires_at'] }}
                                @endif
                            </p>
                        @endif
                        @if (! empty($show['insurance_warning']) && ! empty($selected['insurance_warning']))
                            <p class="text-xs font-bold text-rose-800 mt-0.5">
                                {{ $selected['insurance_warning'] }}
                                @if (! empty($selected['insurance_expires_at']))
                                    · {{ $selected['insurance_expires_at'] }}
                                @endif
                            </p>
                        @endif
                    </div>
                @endif

                @if (! empty($show['valuation']) && ! empty($selected['valuation']['forced_sale_value']))
                    @php $val = $selected['valuation']; @endphp
                    <div class="rounded-xl bg-white/80 ring-1 ring-sky-100 px-3 py-2 space-y-1">
                        @if (! empty($val['market_value']))
                            <p class="text-xs text-gray-600">Market value <span class="font-bold text-gray-900 tabular-nums">{{ format_money($val['market_value']) }}</span></p>
                        @endif
                        <p class="text-xs text-gray-600">Forced sale value <span class="font-extrabold text-gray-900 tabular-nums">{{ format_money($val['forced_sale_value']) }}</span></p>
                        @if (! empty($show['valuer']) && (! empty($val['valuer']) || ! empty($val['valued_at'])))
                            <p class="text-[11px] text-gray-500">
                                @if (! empty($val['valuer'])){{ $val['valuer'] }}@endif
                                @if (! empty($val['valuer']) && ! empty($val['valued_at'])) · @endif
                                @if (! empty($val['valued_at'])){{ $val['valued_at'] }}@endif
                            </p>
                        @elseif (! empty($val['valued_at']))
                            <p class="text-[11px] text-gray-500">{{ $val['valued_at'] }}</p>
                        @endif
                        @if (! empty($show['ltv']) && ! empty($val['cover_amount']))
                            <p class="text-xs font-semibold text-brand">
                                This collateral can support {{ format_money($val['cover_amount']) }}
                                @if (! empty($val['ltv_percent']))
                                    <span class="font-medium text-gray-500">(LTV {{ (int) $val['ltv_percent'] }}%)</span>
                                @endif
                            </p>
                        @endif
                    </div>
                @endif

                {{ $slot ?? '' }}
            </div>
        </div>
    </div>
@endif
