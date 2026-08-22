@php
    $canManage = auth()->user()?->hasPermission('marketplace.manage');
    $depositPercent = rtrim(rtrim(number_format($record->depositPercent(), 2), '0'), '.');
    $availability = ucfirst(str_replace('_', ' ', $record->availability_status ?? 'available'));
    $status = $record->is_active ? 'Active' : 'Inactive';
@endphp

<x-admin.layout
    :title="$record->title"
    heading=""
    :backUrl="route('admin.marketplace-assets.index')"
    backLabel="Marketplace">

    <x-admin.letterhead
        kicker="Marketplace"
        :title="$record->title"
        :subtitle="($categoryLabel ?? $record->category).' · '.$record->supplier_name">
        <x-slot:actions>
            @if ($canManage)
                <a href="{{ route('admin.marketplace-assets.edit', $record) }}"
                   class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2.5 rounded-xl shadow-sm">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Edit
                </a>
            @endif
        </x-slot:actions>
        <x-slot:stats>
            <div class="flex flex-wrap gap-2">
                <span class="inline-flex items-center rounded-full bg-brand-muted px-3 py-1 text-xs font-semibold text-brand">{{ $status }}</span>
                <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700">{{ $availability }}</span>
            </div>
        </x-slot:stats>
    </x-admin.letterhead>

    <div class="grid lg:grid-cols-5 gap-6">
        <div class="lg:col-span-2">
            @if (! empty($record->photos))
                @include('site.marketplace._photo-slider', [
                    'photos' => $record->photos,
                    'category' => $record->category ?? 'other',
                    'zoom' => true,
                ])
            @else
                <div class="rounded-2xl bg-gray-50 ring-1 ring-gray-200 aspect-[4/3] flex items-center justify-center text-sm text-gray-500">
                    No photos yet
                </div>
            @endif
        </div>

        <div class="lg:col-span-3 space-y-6">
            <section class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5">
                <h2 class="text-sm font-semibold text-gray-900 mb-4">Listing</h2>
                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">Category</dt>
                        <dd class="mt-1 font-semibold">{{ $categoryLabel ?? $record->category }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">Supplier</dt>
                        <dd class="mt-1 font-semibold">{{ $record->supplier_name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">Serial / registration</dt>
                        <dd class="mt-1 font-semibold">{{ $record->serial_number ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">Chassis</dt>
                        <dd class="mt-1 font-semibold">{{ $record->chassis_number ?: '—' }}</dd>
                    </div>
                    @if (filled($record->description))
                        <div class="sm:col-span-2">
                            <dt class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">Description</dt>
                            <dd class="mt-1 text-gray-700 font-normal">{{ $record->description }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            <section class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5">
                <h2 class="text-sm font-semibold text-gray-900 mb-4">Pricing</h2>
                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">Asset value</dt>
                        <dd class="mt-1 font-semibold tabular-nums">{{ format_money($record->asset_value) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">Supplier deposit</dt>
                        <dd class="mt-1 font-semibold tabular-nums">{{ $depositPercent }}% · {{ format_money($record->supplier_deposit) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">Platform markup</dt>
                        <dd class="mt-1 font-semibold tabular-nums">{{ rtrim(rtrim(number_format((float) $record->deposit_markup_percent, 2), '0'), '.') }}% · {{ format_money(app(\App\Services\AssetLendingService::class)->depositMarkupAmount($record)) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">Customer deposit</dt>
                        <dd class="mt-1 font-semibold tabular-nums">{{ format_money($record->customer_deposit ?: $record->computeCustomerDeposit()) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">Weekly installment</dt>
                        <dd class="mt-1 font-semibold tabular-nums">{{ format_money($record->weekly_installment) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">Max tenure</dt>
                        <dd class="mt-1 font-semibold">{{ $record->max_tenure_months }} months</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5">
                <h2 class="text-sm font-semibold text-gray-900 mb-4">Cover</h2>
                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">Insurance policy</dt>
                        <dd class="mt-1 font-semibold">{{ $record->insurance_policy_number ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-brand/60 font-semibold">Insurance expiry</dt>
                        <dd class="mt-1 font-semibold">{{ optional($record->insurance_expires_at)->format('d M Y') ?: '—' }}</dd>
                    </div>
                </dl>
            </section>
        </div>
    </div>
</x-admin.layout>
