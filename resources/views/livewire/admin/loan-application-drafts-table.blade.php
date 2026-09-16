<div class="space-y-4">
    @php
        $cards = [
            ['', __('admin.application_drafts.card_total'), $counts['total'] ?? 0],
            ['browsing', __('admin.application_drafts.card_browsing'), $counts['browsing'] ?? 0],
            ['fee_pending', __('admin.application_drafts.card_fee_pending'), $counts['fee_pending'] ?? 0],
            ['awaiting_guarantor', __('admin.application_drafts.card_awaiting_guarantor'), $counts['awaiting_guarantor'] ?? 0],
            ['in_progress', __('admin.application_drafts.card_in_progress'), $counts['in_progress'] ?? 0],
        ];
    @endphp
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        @foreach ($cards as [$key, $label, $value])
            <button type="button"
                    wire:click="setStatus(@js($key))"
                    class="rounded-xl px-4 py-3 text-left ring-1 transition {{ $status === $key ? 'bg-brand text-white ring-brand shadow-sm' : 'bg-white text-gray-900 ring-brand/10 hover:ring-brand/30' }}">
                <p class="text-[10px] uppercase tracking-widest font-semibold {{ $status === $key ? 'text-brand-gold' : 'text-gray-500' }}">{{ $label }}</p>
                <p class="mt-1 text-2xl font-bold tabular-nums">{{ format_number($value) }}</p>
            </button>
        @endforeach
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <div class="relative">
            <select wire:model.live="phase"
                    class="appearance-none text-sm bg-white border border-gray-300 rounded-lg shadow-sm pl-3.5 pr-9 py-2 font-medium text-gray-700 cursor-pointer hover:border-gray-400 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 transition">
                <option value="">{{ __('admin.application_drafts.all_phases') }}</option>
                @foreach ($phases as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <svg class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 size-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
        </div>
        @if ($status !== '')
            <button type="button" wire:click="setStatus('')"
                    class="text-xs font-semibold text-brand hover:text-brand-light">
                {{ __('admin.application_drafts.card_clear') }} →
            </button>
        @endif
    </div>

    <x-admin.table-shell :records="$rows" :statuses="[]" searchPlaceholder="Search customer, phone, product…">
        <x-slot:headers>
            <x-admin.th :sort="$sort" :direction="$direction" col="saved_at" label="{{ __('admin.application_drafts.last_activity') }}" />
            <th class="px-5 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('admin.application_drafts.customer') }}</th>
            <th class="px-5 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('admin.application_drafts.product') }}</th>
            <th class="px-5 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('admin.application_drafts.amount') }}</th>
            <th class="px-5 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('admin.application_drafts.progress') }}</th>
            <th class="px-5 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('admin.application_drafts.status') }}</th>
            <th class="px-5 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('admin.application_drafts.actions') }}</th>
        </x-slot:headers>
        <x-slot:rows>
            @forelse ($rows as $r)
                @php
                    $badge = $draftService->statusBadge($r);
                    $toneMap = [
                        'amber'  => 'bg-amber-100 text-amber-800',
                        'blue'   => 'bg-blue-100 text-blue-800',
                        'purple' => 'bg-purple-100 text-purple-800',
                        'gray'   => 'bg-gray-100 text-gray-700',
                    ];
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 text-gray-500 whitespace-nowrap">
                        <div>{{ $r->saved_at?->format('Y-m-d H:i') ?? '—' }}</div>
                        <div class="text-[10px] text-gray-400">{{ $r->saved_at?->diffForHumans() }}</div>
                    </td>
                    <td class="px-5 py-3">
                        <div class="font-medium text-gray-900">
                            {{ trim(($r->customer?->first_name ?? '').' '.($r->customer?->last_name ?? '')) ?: '—' }}
                        </div>
                        <div class="text-xs text-gray-500">{{ $r->customer?->phone }}</div>
                    </td>
                    <td class="px-5 py-3">
                        <div>{{ $r->product?->name ?? '—' }}</div>
                        <div class="text-[10px] text-gray-400 font-mono">{{ $r->product?->code }}</div>
                    </td>
                    <td class="px-5 py-3 text-right font-mono">
                        @if ($amount = $draftService->requestedAmount($r))
                            {{ format_money($amount) }}
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-sm text-gray-700 max-w-xs">
                        {{ $draftService->progressLabel($r) }}
                    </td>
                    <td class="px-5 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ $toneMap[$badge['tone']] ?? 'bg-gray-100 text-gray-700' }}">
                            {{ $badge['label'] }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-right">
                        @if ($r->customer)
                            <a href="{{ route('admin.loan-applications.incomplete.show', $r) }}"
                               class="text-xs font-medium text-brand hover:text-brand-light">{{ __('admin.application_drafts.view_application') }} →</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-5 py-12 text-center text-gray-500">
                        {{ __('admin.application_drafts.empty') }}
                    </td>
                </tr>
            @endforelse
        </x-slot:rows>
    </x-admin.table-shell>
</div>
