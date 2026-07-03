<x-site.layout :title="brand_title($asset['title'])">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-12">
        <a href="{{ route('site.marketplace') }}" class="text-sm text-brand hover:underline inline-flex items-center gap-1">
            ← {{ __('borrower.marketplace.back_to_marketplace') }}
        </a>

        <div class="grid lg:grid-cols-2 gap-8 lg:gap-12 mt-6">
            <div>
                @include('site.marketplace._photo-slider', ['photos' => $asset['photos'] ?? [], 'category' => $asset['category'] ?? 'other', 'zoom' => true])
            </div>
            <div>
                <p class="text-xs uppercase tracking-widest text-brand font-semibold">{{ config('asset_marketplace.categories.'.$asset['category']) }}</p>
                <h1 class="text-3xl sm:text-4xl font-bold mt-2 text-gray-900">{{ $asset['title'] }}</h1>
                @if (! empty($asset['vendor']))
                    <p class="text-sm text-gray-500 mt-3">
                        {{ __('borrower.marketplace.supplier') }}: <span class="font-medium text-gray-700">{{ $asset['vendor'] }}</span>
                        @if (! empty($asset['supplier_region']))
                            <span class="text-gray-400">· {{ $asset['supplier_region'] }}</span>
                        @endif
                    </p>
                @endif

                @if (! empty($asset['description']))
                    <div class="mt-6 glass-card p-5">
                        <h2 class="text-xs uppercase tracking-widest text-gray-500 mb-2">{{ __('site.product_detail.overview') }}</h2>
                        <p class="text-sm text-gray-700 leading-relaxed">{{ $asset['description'] }}</p>
                    </div>
                @endif

                <div class="mt-6">
                    <h2 class="text-xs uppercase tracking-widest text-brand font-semibold mb-3">{{ __('site.marketplace.financing') }}</h2>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="glass-card p-4">
                            <p class="text-[10px] uppercase text-gray-400">{{ __('borrower.marketplace.asset_value') }}</p>
                            <p class="text-xl font-bold mt-1 tabular-nums">{{ format_money($asset['asset_value'] ?? 0) }}</p>
                        </div>
                        <div class="glass-card p-4">
                            <p class="text-[10px] uppercase text-gray-400">{{ __('borrower.marketplace.deposit') }}</p>
                            <p class="text-xl font-bold mt-1 text-brand tabular-nums">{{ format_money($asset['deposit']) }}</p>
                        </div>
                        <div class="glass-card p-4">
                            <p class="text-[10px] uppercase text-gray-400">{{ __('borrower.marketplace.loan_amount') }}</p>
                            <p class="text-xl font-bold mt-1 tabular-nums">{{ format_money($asset['remaining_loan'] ?? 0) }}</p>
                        </div>
                        <div class="glass-card p-4">
                            <p class="text-[10px] uppercase text-gray-400">{{ __('borrower.marketplace.weekly_installment') }}</p>
                            <p class="text-xl font-bold mt-1 tabular-nums">{{ format_money($asset['weekly_installment']) }}</p>
                        </div>
                    </div>
                </div>

                @if (! empty($asset['max_tenure_months']) || ! empty($asset['serial_number']) || ! empty($asset['chassis_number']))
                    <div class="mt-6 glass-card p-5">
                        <h2 class="text-xs uppercase tracking-widest text-gray-500 mb-3">{{ __('site.marketplace.specifications') }}</h2>
                        <dl class="space-y-2 text-sm">
                            @if (! empty($asset['max_tenure_months']))
                                <div class="flex justify-between"><dt class="text-gray-500">{{ __('borrower.marketplace.max_tenure') }}</dt><dd class="font-semibold">{{ $asset['max_tenure_months'] }} {{ __('borrower.apply.quote.months') }}</dd></div>
                            @endif
                            @if (! empty($asset['serial_number']))
                                <div class="flex justify-between"><dt class="text-gray-500">Serial</dt><dd class="font-mono text-xs">{{ $asset['serial_number'] }}</dd></div>
                            @endif
                            @if (! empty($asset['chassis_number']))
                                <div class="flex justify-between"><dt class="text-gray-500">Chassis</dt><dd class="font-mono text-xs">{{ $asset['chassis_number'] }}</dd></div>
                            @endif
                        </dl>
                    </div>
                @endif

                <div class="mt-8 glass-card p-6 ring-2 ring-brand/10">
                    <p class="text-sm text-gray-700 mb-4">{{ __('borrower.marketplace.public_apply_hint') }}</p>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ $loginUrl }}" class="inline-flex bg-brand hover:bg-brand-light text-white font-semibold px-6 py-3 rounded-xl text-sm transition">
                            {{ __('borrower.marketplace.public_apply_login') }}
                        </a>
                        <a href="{{ route('site.register.borrower') }}" class="inline-flex ring-1 ring-brand/30 text-brand font-semibold px-6 py-3 rounded-xl text-sm hover:bg-brand-muted transition">
                            {{ __('borrower.marketplace.public_apply_register') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-site.layout>
