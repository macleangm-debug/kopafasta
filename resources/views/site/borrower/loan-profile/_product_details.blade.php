@php
    $details = $profile['product_details'] ?? null;
@endphp

@if (! empty($details) && ($details['type'] ?? '') !== 'group')
    <div class="glass-card p-5 mb-6 ring-1 ring-brand/15">
        <div class="mb-4">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.loan_profile.special.eyebrow') }}</p>
            <h2 class="font-semibold text-gray-900 mt-1">{{ $details['title'] ?? __('borrower.loan_profile.summary_title') }}</h2>
        </div>

        @if (! empty($details['asset']))
            <dl class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm mb-5">
                @foreach ([
                    'name' => __('borrower.loan_profile.special.asset_name'),
                    'description' => __('borrower.loan_profile.special.asset_description'),
                    'type' => __('borrower.loan_profile.special.asset_type'),
                    'reference' => __('borrower.loan_profile.special.asset_reference'),
                    'value' => __('borrower.loan_profile.special.asset_value'),
                    'price' => __('borrower.loan_profile.special.asset_price'),
                    'remaining' => __('borrower.loan_profile.special.remaining_loan'),
                    'location' => __('borrower.loan_profile.special.asset_location'),
                ] as $key => $label)
                    @if (filled($details['asset'][$key] ?? null))
                        <div>
                            <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ $label }}</dt>
                            <dd class="font-semibold mt-1 text-gray-900">
                                @if (in_array($key, ['value', 'price', 'remaining'], true) && is_numeric($details['asset'][$key]))
                                    {{ format_money((float) $details['asset'][$key]) }}
                                @else
                                    {{ $details['asset'][$key] }}
                                @endif
                            </dd>
                        </div>
                    @endif
                @endforeach
            </dl>
        @endif

        @if (! empty($details['steps']))
            <div class="mb-2">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-3">{{ __('borrower.loan_profile.special.steps_title') }}</p>
                <ol class="grid sm:grid-cols-2 lg:grid-cols-4 gap-2">
                    @foreach ($details['steps'] as $step)
                        <li @class([
                            'rounded-xl px-3 py-3 text-sm ring-1',
                            'bg-emerald-50 text-emerald-800 ring-emerald-200' => $step['complete'] ?? false,
                            'bg-gray-50 text-gray-600 ring-gray-200' => ! ($step['complete'] ?? false),
                        ])>
                            <span class="font-semibold">{{ ($step['complete'] ?? false) ? '✓' : '○' }}</span>
                            {{ $step['label'] ?? '' }}
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif

        @if (! empty($details['photos']) || ! empty($details['ownership_documents']) || ! empty($details['insurance_documents']))
            @include('shared._draft_asset_media', [
                'snapshot' => [
                    'asset_photos' => $details['photos'] ?? [],
                    'ownership_documents' => $details['ownership_documents'] ?? [],
                    'insurance_documents' => $details['insurance_documents'] ?? [],
                ],
                'heading' => __('borrower.loan_profile.sections.asset_documents'),
            ])
        @endif
    </div>
@endif

@if (! empty($details) && ($details['type'] ?? '') === 'group' && ! empty($details['group']))
    <div class="glass-card p-5 mb-6 ring-1 ring-brand/15">
        <div class="mb-4">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.loan_profile.special.eyebrow') }}</p>
            <h2 class="font-semibold text-gray-900 mt-1">{{ $details['title'] }}</h2>
        </div>
        <dl class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
            @if (filled($details['group']['name'] ?? null))
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_profile.special.group_name') }}</dt>
                    <dd class="font-semibold mt-1">{{ $details['group']['name'] }}</dd>
                </div>
            @endif
            @if (filled($details['group']['purpose'] ?? null))
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_profile.special.group_purpose') }}</dt>
                    <dd class="font-semibold mt-1">{{ $details['group']['purpose'] }}</dd>
                </div>
            @endif
            @if (filled($details['group']['amount_per_member'] ?? null))
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.apply.group.headline.per_member') }}</dt>
                    <dd class="font-semibold mt-1">{{ format_money((float) $details['group']['amount_per_member']) }}</dd>
                </div>
            @endif
            @if (filled($details['group']['target_member_count'] ?? null))
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_profile.special.group_size') }}</dt>
                    <dd class="font-semibold mt-1">{{ $details['group']['target_member_count'] }}</dd>
                </div>
            @endif
        </dl>
    </div>
@endif
