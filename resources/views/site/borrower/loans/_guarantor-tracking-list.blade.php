@php
    $rows = $rows ?? collect();
    $viewMode = $viewMode ?? 'cards';
@endphp

@if ($viewMode === 'table')
    <div class="glass-card overflow-hidden ring-1 ring-brand/15">
        <table class="w-full text-sm">
            <thead class="bg-brand-muted/30 text-left text-xs uppercase text-gray-500">
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
                        $productName = $row->product?->localizedName() ?? __('borrower.guarantor.loan');
                        $needsProfile = $row->needs_guarantor_profile ?? false;
                        $detailUrl = route('site.borrower.guaranteed.show', $row->link);
                    @endphp
                    <tr class="hover:bg-brand-muted/20 {{ ($row->is_terminal ?? false) ? 'opacity-75' : '' }}">
                        <td class="px-4 py-3">
                            <p class="font-semibold text-gray-900">{{ $borrowerName }}</p>
                            <p class="font-mono text-xs text-gray-500">{{ $row->reference }}</p>
                        </td>
                        <td class="px-4 py-3">{{ $productName }}</td>
                        <td class="px-4 py-3 text-right font-mono tabular-nums">{{ format_money($row->amount) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex text-xs font-semibold rounded-full px-2 py-0.5 {{ $needsProfile ? 'bg-amber-100 text-amber-900' : 'bg-sky-100 text-sky-800' }}">{{ $row->stage_label }}</span>
                            @if (! empty($row->deadline_label))
                                <p class="text-[11px] text-gray-500 mt-1">{{ $row->deadline_label }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ $detailUrl }}" class="text-brand font-semibold hover:underline">{{ __('borrower.guaranteed.view_details') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach ($rows as $row)
            @php
                $borrowerName = $row->borrower?->legalDisplayName() ?? __('borrower.loans_page.borrower');
                $productName = $row->product?->localizedName() ?? __('borrower.guarantor.loan');
                $needsProfile = (bool) ($row->needs_guarantor_profile ?? false);
                $detailUrl = route('site.borrower.guaranteed.show', $row->link);
                $isTerminal = (bool) ($row->is_terminal ?? false);
            @endphp
            <div class="glass-card p-5 ring-1 ring-brand/10 {{ $isTerminal ? 'opacity-80' : '' }}">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ $productName }}</p>
                        <p class="text-lg font-bold text-gray-900 tracking-tight mt-0.5 leading-snug">{{ $borrowerName }}</p>
                        <p class="font-mono text-xs text-gray-500 mt-1">{{ $row->reference }}</p>
                    </div>
                    <div class="flex flex-col items-end gap-1.5 shrink-0">
                        <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $needsProfile ? 'bg-amber-100 text-amber-900' : 'bg-sky-100 text-sky-800' }}">
                            {{ $row->stage_label }}
                        </span>
                        @if ($needsProfile)
                            <span class="text-[10px] font-bold uppercase tracking-wide text-amber-800">
                                {{ __('borrower.guaranteed.your_profile_pct', ['percent' => (int) ($row->profile_percent ?? 0)]) }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="rounded-xl bg-gray-50 px-3 py-2.5">
                        <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.loan_amount') }}</p>
                        <p class="font-semibold text-sm mt-0.5">{{ format_money($row->amount) }}</p>
                    </div>
                    <div class="rounded-xl bg-gray-50 px-3 py-2.5">
                        <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.guaranteed.current_step') }}</p>
                        <p class="font-semibold text-sm mt-0.5 leading-snug">{{ $row->stage_label }}</p>
                    </div>
                </div>

                @if (! empty($row->deadline_label))
                    <p class="text-xs font-semibold mb-3 {{ ($row->deadline_urgent ?? false) ? 'text-red-700' : 'text-brand' }}">
                        {{ $row->deadline_label }}
                    </p>
                @elseif ($row->pending_hint)
                    <p class="text-xs text-gray-600 mb-3">{{ $row->pending_hint }}</p>
                @endif

                <a href="{{ $detailUrl }}"
                   class="inline-flex items-center justify-center w-full sm:w-auto font-bold px-5 py-2.5 rounded-xl text-sm bg-brand-gold hover:bg-yellow-400 text-brand shadow-sm">
                    {{ __('borrower.guaranteed.view_details') }}
                </a>
            </div>
        @endforeach
    </div>
@endif
