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
                    <th class="px-4 py-3 text-right">{{ __('borrower.guarantor_invite.amount_label') }}</th>
                    <th class="px-4 py-3">{{ __('borrower.loans_page.reference') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($rows as $row)
                    @php
                        $link = $row->link;
                        $borrower = $row->borrower;
                        $application = $row->application;
                        $borrowerName = $borrower?->legalDisplayName()
                            ?? (trim(($borrower->first_name ?? '').' '.($borrower->last_name ?? '')) ?: '—');
                        $productName = $application?->product?->name
                            ?? $row->invitation?->product?->name
                            ?? __('borrower.guarantor.loan');
                        $amount = $application?->requested_amount
                            ?? $row->invitation?->requested_amount;
                        $reference = $application?->application_number
                            ?? $application?->draft_reference
                            ?? ($row->invitation?->short_code ? strtoupper((string) $row->invitation->short_code) : '—');
                    @endphp
                    <tr class="hover:bg-brand-muted/20">
                        <td class="px-4 py-3 font-semibold text-gray-900">{{ $borrowerName }}</td>
                        <td class="px-4 py-3">{{ $productName }}</td>
                        <td class="px-4 py-3 text-right font-mono tabular-nums">{{ $amount !== null ? format_money((float) $amount) : '—' }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $reference }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('site.borrower.guarantor-requests.show', $link) }}" class="text-brand font-semibold hover:underline">
                                {{ __('borrower.guarantor.view_details') }}
                            </a>
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
                $link = $row->link;
                $borrower = $row->borrower;
                $application = $row->application;
                $borrowerName = $borrower?->legalDisplayName()
                    ?? (trim(($borrower->first_name ?? '').' '.($borrower->last_name ?? '')) ?: '—');
                $productName = $application?->product?->name
                    ?? $row->invitation?->product?->name
                    ?? __('borrower.guarantor.loan');
                $amount = $application?->requested_amount
                    ?? $row->invitation?->requested_amount;
                $reference = $application?->application_number
                    ?? $application?->draft_reference
                    ?? ($row->invitation?->short_code ? strtoupper((string) $row->invitation->short_code) : '—');
            @endphp
            <a href="{{ route('site.borrower.guarantor-requests.show', $link) }}"
               class="block glass-card p-5 hover:ring-2 hover:ring-brand/20 transition-all group">
                <div class="flex items-start justify-between gap-2 mb-3">
                    <div class="min-w-0">
                        <p class="text-xs text-gray-500">{{ $productName }}</p>
                        <p class="font-semibold text-gray-900 group-hover:text-brand">{{ $borrowerName }}</p>
                        <p class="text-xs text-gray-500 mt-0.5 font-mono">{{ $reference }}</p>
                    </div>
                    <span class="shrink-0 text-xs font-semibold rounded-full px-2.5 py-1 bg-brand-muted text-brand">{{ __('borrower.guarantor.action_required') }}</span>
                </div>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.guarantor_invite.amount_label') }}</p>
                        <p class="font-semibold">{{ $amount !== null ? format_money((float) $amount) : '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.guaranteed.current_step') }}</p>
                        <p class="text-sm text-gray-700">{{ __('borrower.guarantor.awaiting_your_decision') }}</p>
                    </div>
                </div>
                <p class="mt-4 text-sm font-semibold text-brand">{{ __('borrower.guarantor.view_details') }} →</p>
            </a>
        @endforeach
    </div>
@endif
