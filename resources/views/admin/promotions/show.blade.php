<x-admin.layout title="{{ $record->name }}" heading="{{ $record->name }}" subheading="{{ $record->code }} · {{ ucfirst($record->status) }}">
    @php
        $meta = $orchestration ?? ($record->metadata ?? []);
        $results = $results ?? ($meta['results'] ?? []);
        $reach = (int) ($results['reach'] ?? $meta['estimated_reach'] ?? 0);
    @endphp

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Reach</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-brand">{{ \App\Support\MoneyFormat::compact($reach) }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Delivered</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-brand">{{ \App\Support\MoneyFormat::compact($results['delivered'] ?? 0) }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Opened / clicked</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-brand">{{ \App\Support\MoneyFormat::compact($results['opened'] ?? 0) }} / {{ \App\Support\MoneyFormat::compact($results['clicked'] ?? 0) }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Converted</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-brand">{{ \App\Support\MoneyFormat::compact($results['converted'] ?? 0) }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Offers claimed</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-brand">{{ \App\Support\MoneyFormat::compact($results['offers_claimed'] ?? 0) }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Plus joined</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-brand">{{ \App\Support\MoneyFormat::compact($results['plus_joined'] ?? 0) }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Referral outcome</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-brand">{{ \App\Support\MoneyFormat::compact($results['referrals'] ?? 0) }}</p>
        </div>

    <div class="grid lg:grid-cols-2 gap-4 mb-6">
        <section class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 text-sm space-y-2">
            <h2 class="font-semibold">Orchestration</h2>
            <p>Goal: <strong>{{ $meta['intent_other'] ?? ($meta['intent'] ?? '—') }}</strong></p>
            <p>Audience: <strong>{{ $meta['audience_mode'] ?? '—' }}</strong> · estimated {{ number_format($reach) }}</p>
            <p>Channels: <strong>{{ implode(', ', $meta['channels'] ?? ['in_app']) }}</strong></p>
            <p>Timing: <strong>{{ ($meta['send_mode'] ?? 'now') === 'now' ? 'Send now' : 'Scheduled' }}</strong></p>
            @if (!empty($meta['quiet_hours_honoured']))
                <p class="text-amber-800">Deferred for quiet hours.</p>
            @endif
            @if (!empty($results['note']))
                <p class="text-xs text-gray-500">{{ $results['note'] }}</p>
            @endif
        </section>
        <section class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 text-sm space-y-2">
            <h2 class="font-semibold">Offer / fee engine</h2>
            <p>Type: {{ str_replace('_', ' ', $record->type) }}</p>
            <p>Applies to: {{ $record->applies_to ? str_replace('_', ' ', $record->applies_to) : '—' }}</p>
            <p>Discount: {{ $record->discount_percent ? format_number((float) $record->discount_percent, 2).'%' : ($record->discount_amount ? 'TZS '.format_number((float) $record->discount_amount) : '—') }}</p>
            <p>Period: {{ optional($record->starts_at)->format('d M Y') ?? '—' }} → {{ optional($record->ends_at)->format('d M Y') ?? '—' }}</p>
            <p class="text-xs text-gray-500">Fee behaviour is still PromotionService. This page does not duplicate it.</p>
        </section>
    </div>

    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.promotions.edit', $record) }}" class="rounded-xl bg-brand-gold text-brand text-sm font-bold px-4 py-2.5">Edit</a>
        <a href="{{ route('admin.promotions.index') }}" class="rounded-xl bg-white ring-1 ring-brand/15 text-sm font-semibold px-4 py-2.5">All campaigns</a>
    </div>
</x-admin.layout>
