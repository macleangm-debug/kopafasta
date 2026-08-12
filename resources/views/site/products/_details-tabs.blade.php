@props(['p'])

<section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 lg:py-14"
         x-data="{ tab: 'features', showAllFaq: false }">
    <h2 class="text-xl font-bold text-gray-900 mb-4">{{ __('site.product_detail.essentials') }}</h2>
    <div class="grid sm:grid-cols-2 gap-4 mb-8">
        <div class="glass-card p-5">
            <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-2">{{ __('site.product_detail.overview') }}</p>
            <p class="text-sm text-gray-700 leading-relaxed">{{ $p['overview_short'] }}</p>
        </div>
        <div class="glass-card p-5">
            <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-2">{{ __('site.product_detail.target_audience') }}</p>
            <p class="text-sm text-gray-700 leading-relaxed">{{ $p['target_audience_short'] }}</p>
        </div>
    </div>

    <div class="glass-card overflow-hidden">
        <div class="flex overflow-x-auto border-b border-gray-100">
            @foreach (['features' => __('site.product_detail.features'), 'eligibility' => __('site.product_detail.eligibility_heading'), 'fees' => __('site.product_detail.fees_heading'), 'documents' => __('site.product_detail.documents_heading')] as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'"
                        class="shrink-0 px-5 py-3.5 text-sm font-semibold border-b-2 transition whitespace-nowrap"
                        :class="tab === '{{ $key }}' ? 'border-brand text-brand bg-brand-muted/30' : 'border-transparent text-gray-500 hover:text-gray-800'">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="p-6 sm:p-8">
            <div x-show="tab === 'features'" x-cloak>
                <ul class="grid sm:grid-cols-2 gap-3">
                    @foreach ($p['benefits'] as $benefit)
                        <li class="flex gap-2 text-sm text-gray-700">
                            <span class="text-brand shrink-0">✓</span><span>{{ $benefit }}</span>
                        </li>
                    @endforeach
                    @foreach ($p['features'] as $feature)
                        <li class="flex gap-2 text-sm text-gray-600">
                            <span class="text-brand-gold shrink-0">•</span><span>{{ $feature }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div x-show="tab === 'eligibility'" x-cloak>
                <ul class="space-y-4 max-w-2xl">
                    @foreach ($p['eligibility'] as $item)
                        <li>
                            <p class="font-semibold text-sm text-gray-900">{{ $item['label'] }}</p>
                            <p class="text-xs text-gray-600 mt-0.5">{{ $item['detail'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div x-show="tab === 'fees'" x-cloak>
                <dl class="space-y-3 text-sm max-w-md">
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">{{ __('site.product_detail.application_fee') }}</dt>
                        <dd class="font-semibold tabular-nums">{{ format_money($p['fees']['application']) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">{{ __('site.product_detail.post_approval_fees') }}</dt>
                        <dd class="font-semibold tabular-nums">{{ format_money($p['fees']['post_approval_total']) }}</dd>
                    </div>
                    @if (! empty($p['penalties']))
                        <div class="pt-3 mt-1 border-t border-gray-100 space-y-3">
                            <div class="flex justify-between gap-3">
                                <dt class="text-gray-500">{{ __('site.product_detail.late_penalty') }}</dt>
                                <dd class="font-semibold tabular-nums text-right">
                                    {{ rtrim(rtrim(number_format((float) $p['penalties']['rate_percent'], 2, '.', ''), '0'), '.') }}%
                                    <span class="block text-[11px] font-normal text-gray-500">{{ $p['penalties']['basis_label'] }}</span>
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-gray-500">{{ __('site.product_detail.grace_period') }}</dt>
                                <dd class="font-semibold tabular-nums">{{ __('site.product_detail.grace_days', ['days' => $p['penalties']['grace_days']]) }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-gray-500">{{ __('site.product_detail.penalty_cap') }}</dt>
                                <dd class="font-semibold tabular-nums">{{ rtrim(rtrim(number_format((float) $p['penalties']['cap_percent'], 2, '.', ''), '0'), '.') }}%</dd>
                            </div>
                        </div>
                    @endif
                </dl>
                @if (! empty($p['rate_disclosure']))
                    <ul class="mt-4 pt-4 border-t border-gray-100 space-y-1 text-xs text-gray-600 max-w-xl">
                        @foreach (array_slice($p['rate_disclosure'], 0, 3) as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div x-show="tab === 'documents'" x-cloak>
                <ul class="space-y-3 max-w-xl">
                    @foreach (array_slice($p['documents'], 0, 5) as $doc)
                        <li class="flex gap-3 text-sm">
                            <span class="size-8 rounded-lg bg-brand-muted text-brand grid place-items-center text-xs shrink-0">📄</span>
                            <div>
                                <p class="font-medium text-gray-900">{{ $doc['name'] }}</p>
                                <p class="text-xs text-gray-500">{{ $doc['detail'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
                @if (! empty($p['product_specific']))
                    <div class="mt-6 pt-6 border-t border-gray-100">
                        <ul class="space-y-2">
                            @foreach (array_slice($p['product_specific'], 0, 3) as $item)
                                <li class="text-sm">
                                    <span class="font-semibold">{{ $item['label'] }}</span>
                                    <span class="text-gray-600"> — {{ $item['detail'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
