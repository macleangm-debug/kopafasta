@php
    $rows = $rows ?? [];
    $toneClasses = $toneClasses ?? [
        'gray'    => 'bg-gray-100 text-gray-700',
        'amber'   => 'bg-brand-muted text-brand',
        'sky'     => 'bg-sky-100 text-sky-700',
        'emerald' => 'bg-emerald-100 text-emerald-700',
        'red'     => 'bg-red-100 text-red-700',
        'orange'  => 'bg-orange-100 text-orange-700',
    ];
@endphp

<div class="space-y-3">
    @foreach ($rows as $row)
        @php
            $badge = $toneClasses[$row['status_tone'] ?? 'sky'] ?? $toneClasses['sky'];
            $updated = optional($row['updated_at'] ?? null)->format('d M Y') ?: ($row['last_updated_human'] ?? '—');
        @endphp
        <a href="{{ $row['action_url'] }}"
           data-kf-share="kf-app-{{ $row['id'] }}"
           class="glass-card p-4 sm:p-5 flex flex-wrap items-center justify-between gap-3 hover:ring-brand/25 transition">
            <div class="min-w-0">
                <p class="text-sm sm:text-base font-bold text-gray-900 leading-snug">{{ $row['product_name'] }}</p>
                <p class="font-mono text-xs text-gray-500 mt-0.5">{{ $row['application_number'] }}</p>
                @if (($row['requested_amount'] ?? null) !== null)
                    <p class="text-sm font-bold tabular-nums text-gray-900 mt-1">{{ format_money($row['requested_amount']) }}</p>
                @endif
            </div>
            <div class="flex flex-col items-end gap-1.5 shrink-0">
                <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $badge }}">{{ $row['application_status'] ?? $row['status_label'] }}</span>
                <p class="text-[11px] text-gray-500">{{ $updated }}</p>
                <span class="text-xs font-semibold text-brand">{{ __('borrower.loan_profile.view_decision') }}</span>
            </div>
        </a>
    @endforeach
</div>
