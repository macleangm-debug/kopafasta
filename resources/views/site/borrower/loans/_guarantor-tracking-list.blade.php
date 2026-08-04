@php
    $rows = $rows ?? collect();
    $viewMode = $viewMode ?? 'cards';
@endphp

@if ($viewMode === 'table')
    <div class="glass-card overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50/80 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">{{ __('borrower.loans_page.borrower') }}</th>
                    <th class="px-4 py-3">{{ __('borrower.guarantor_invite.product_label') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('borrower.loans_page.loan_amount') }}</th>
                    <th class="px-4 py-3">{{ __('borrower.guaranteed.current_step') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($rows as $row)
                    @php
                        $borrowerName = $row->borrower?->legalDisplayName() ?? __('borrower.loans_page.borrower');
                        $productName = $row->product?->name ?? __('borrower.guarantor.loan');
                        $needsProfile = $row->needs_guarantor_profile ?? false;
                        $appTone = $needsProfile
                            ? 'bg-amber-100 text-amber-900'
                            : match ($row->application_status['tone'] ?? 'gray') {
                                'emerald' => 'bg-emerald-100 text-emerald-800',
                                'red' => 'bg-red-100 text-red-800',
                                'amber' => 'bg-brand-muted text-brand',
                                'orange' => 'bg-orange-100 text-orange-800',
                                default => 'bg-sky-100 text-sky-800',
                            };
                    @endphp
                    <tr class="hover:bg-brand-muted/20 {{ ($row->is_terminal ?? false) ? 'opacity-75' : '' }} {{ $needsProfile ? 'bg-amber-50/40' : '' }}">
                        <td class="px-4 py-3">
                            <p class="font-semibold text-gray-900">{{ $borrowerName }}</p>
                            <p class="font-mono text-xs text-gray-500">{{ $row->reference }}</p>
                        </td>
                        <td class="px-4 py-3">{{ $productName }}</td>
                        <td class="px-4 py-3 text-right font-mono tabular-nums">{{ format_money($row->amount) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex text-xs font-semibold rounded-full px-2 py-0.5 {{ $appTone }}">{{ $row->stage_label }}</span>
                            @if ($needsProfile)
                                <p class="text-xs font-semibold text-amber-800 mt-1">{{ __('borrower.guaranteed.your_profile_pct', ['percent' => $row->profile_percent ?? 0]) }}</p>
                            @endif
                            @if ($row->pending_hint)
                                <p class="text-xs text-gray-500 mt-1 max-w-xs">{{ $row->pending_hint }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if ($needsProfile)
                                <a href="{{ $row->profile_url }}" class="text-amber-800 font-bold hover:underline">{{ __('borrower.guarantor.complete_profile') }}</a>
                            @else
                                <a href="{{ route('site.borrower.guaranteed.show', $row->link) }}" class="text-brand font-semibold hover:underline">{{ __('borrower.guaranteed.view_details') }}</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <div class="space-y-4">
        @foreach ($rows as $row)
            @php
                $borrowerName = $row->borrower?->legalDisplayName() ?? __('borrower.loans_page.borrower');
                $productName = $row->product?->name ?? __('borrower.guarantor.loan');
                $needsProfile = $row->needs_guarantor_profile ?? false;
                $appTone = $needsProfile
                    ? 'bg-amber-100 text-amber-900'
                    : match ($row->application_status['tone'] ?? 'gray') {
                        'emerald' => 'bg-emerald-100 text-emerald-800',
                        'red' => 'bg-red-100 text-red-800',
                        'amber' => 'bg-brand-muted text-brand',
                        'orange' => 'bg-orange-100 text-orange-800',
                        default => 'bg-sky-100 text-sky-800',
                    };
                $cardClass = ($row->is_terminal ?? false)
                    ? 'opacity-75'
                    : ($needsProfile ? '' : 'hover:ring-brand/20');
            @endphp
            <div class="glass-card p-6 transition-all ring-1 ring-brand/10 {{ $cardClass }}">
                @if ($needsProfile)
                    <div class="mb-4 rounded-2xl bg-gradient-to-br from-brand-muted/50 to-white ring-1 ring-brand/15 px-4 py-3.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.loan_profile.profile_completion') }}</p>
                            <div class="flex items-center gap-3 mt-2 max-w-xs">
                                <div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                                    <div class="h-full rounded-full bg-brand" style="width: {{ (int) ($row->profile_percent ?? 0) }}%"></div>
                                </div>
                                <span class="text-xs font-bold tabular-nums">{{ (int) ($row->profile_percent ?? 0) }}%</span>
                            </div>
                            <p class="text-sm font-bold text-gray-900 mt-2">{{ __('borrower.guaranteed.profile_block_title') }}</p>
                            <p class="text-xs text-gray-600 mt-0.5">{{ __('borrower.guaranteed.profile_block_body', ['percent' => $row->profile_percent ?? 0]) }}</p>
                        </div>
                        <a href="{{ $row->profile_url }}"
                           class="inline-flex shrink-0 justify-center rounded-xl bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-4 py-2.5 text-sm shadow-sm">
                            {{ __('borrower.guarantor.complete_profile') }}
                        </a>
                    </div>
                @endif

                <a href="{{ route('site.borrower.guaranteed.show', $row->link) }}" class="block group">
                    <div class="flex items-start justify-between gap-3 mb-4 flex-wrap">
                        <div>
                            <p class="text-xs text-gray-500">{{ $productName }}</p>
                            <p class="font-semibold text-gray-900 group-hover:text-brand">{{ $borrowerName }}</p>
                            <p class="text-xs text-gray-500 mt-0.5 font-mono">{{ $row->reference }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span class="text-xs font-semibold rounded-full px-2.5 py-1 bg-sky-100 text-sky-800">{{ __('borrower.loans_page.guarantor_badge') }}</span>
                            @if ($needsProfile)
                                <span class="text-xs font-semibold rounded-full px-2.5 py-1 bg-amber-200 text-amber-950">{{ __('borrower.guaranteed.your_profile_pct', ['percent' => $row->profile_percent ?? 0]) }}</span>
                            @endif
                            @if ($row->is_terminal ?? false)
                                <span class="text-xs font-semibold rounded-full px-2.5 py-1 bg-gray-100 text-gray-700">{{ __('borrower.guaranteed.outcome_closed') }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-3 gap-4 text-sm mb-3">
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.loan_amount') }}</p>
                            <p class="font-semibold">{{ format_money($row->amount) }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.guaranteed.current_step') }}</p>
                            <span class="inline-flex text-xs font-semibold rounded-full px-2 py-0.5 {{ $appTone }}">{{ $row->stage_label }}</span>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.loan_status') }}</p>
                            <p class="text-gray-600">{{ __('borrower.loans_page.not_disbursed') }}</p>
                        </div>
                    </div>

                    @if (! $needsProfile && $row->pending_hint)
                        <p class="text-sm text-gray-600 mb-2">{{ $row->pending_hint }}</p>
                    @endif
                </a>

                @unless ($needsProfile)
                    <a href="{{ route('site.borrower.guaranteed.show', $row->link) }}"
                       class="mt-2 inline-flex text-sm font-semibold text-brand">{{ __('borrower.guaranteed.view_details') }} →</a>
                @else
                    <a href="{{ route('site.borrower.guaranteed.show', $row->link) }}"
                       class="mt-3 inline-flex text-sm font-semibold text-gray-600 hover:text-brand">{{ __('borrower.guaranteed.view_stages') }} →</a>
                @endunless
            </div>
        @endforeach
    </div>
@endif
