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
                            <a href="{{ route('site.borrower.guarantor-requests.show', $link) }}" data-kf-share="kf-gtr-{{ $link->id }}" class="text-brand font-semibold hover:underline">
                                {{ __('borrower.guaranteed.view_request') }}
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
                $detailUrl = route('site.borrower.guarantor-requests.show', $link);
            @endphp
            <div class="glass-card p-5 ring-1 ring-brand/10" data-kf-share="kf-gtr-{{ $link->id }}">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ $productName }}</p>
                        <p class="text-lg font-bold text-gray-900 tracking-tight mt-0.5 leading-snug">{{ $borrowerName }}</p>
                        <p class="font-mono text-xs text-gray-500 mt-1">{{ $reference }}</p>
                    </div>
                    <span class="shrink-0 text-xs font-semibold rounded-full px-2.5 py-1 bg-amber-100 text-amber-900">
                        {{ __('borrower.guarantor.action_required') }}
                    </span>
                </div>
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="rounded-xl bg-gray-50 px-3 py-2.5">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.guarantor_invite.amount_label') }}</p>
                        <p class="font-semibold text-sm mt-0.5">{{ $amount !== null ? format_money((float) $amount) : '—' }}</p>
                    </div>
                    <div class="rounded-xl bg-gray-50 px-3 py-2.5">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.guaranteed.current_step') }}</p>
                        <p class="font-semibold text-sm mt-0.5">{{ __('borrower.guarantor.awaiting_your_decision') }}</p>
                    </div>
                </div>
                <a href="{{ $detailUrl }}"
                   class="inline-flex items-center justify-center w-full sm:w-auto font-bold px-5 py-2.5 rounded-xl text-sm bg-brand-gold hover:bg-yellow-400 text-brand shadow-sm">
                    {{ __('borrower.guaranteed.view_request') }}
                </a>
            </div>
        @endforeach
    </div>
@endif
