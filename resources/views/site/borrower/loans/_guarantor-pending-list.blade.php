@php
    $rows = $rows ?? collect();
    $viewMode = $viewMode ?? 'cards';
@endphp

{{-- Desktop: full-width balanced table (same responsive pattern as applications) --}}
<div class="hidden lg:block">
    <div class="glass-card overflow-hidden ring-1 ring-brand/15">
        <div class="overflow-x-auto">
            <table class="w-full table-fixed text-sm">
                <thead class="bg-brand-muted/30 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="w-[28%] px-5 py-3">{{ __('borrower.loans_page.borrower') }}</th>
                        <th class="w-[24%] px-5 py-3">{{ __('borrower.guarantor_invite.product_label') }}</th>
                        <th class="w-[18%] px-5 py-3 text-right">{{ __('borrower.guarantor_invite.amount_label') }}</th>
                        <th class="w-[18%] px-5 py-3">{{ __('borrower.loans_page.loan_status') }}</th>
                        <th class="w-[12%] px-5 py-3 text-right">{{ __('borrower.applications_list.actions') }}</th>
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
                            $detailUrl = route('site.borrower.guarantor-requests.show', $link);
                        @endphp
                        <tr class="hover:bg-brand-muted/20 cursor-pointer transition"
                            data-kf-share="kf-gtr-{{ $link->id }}"
                            onclick="window.location='{{ $detailUrl }}'">
                            <td class="px-5 py-4">
                                <p class="font-semibold text-gray-900 leading-snug">{{ $borrowerName }}</p>
                                <p class="font-mono text-xs text-gray-500 mt-0.5">{{ $reference }}</p>
                            </td>
                            <td class="px-5 py-4 text-gray-800">{{ $productName }}</td>
                            <td class="px-5 py-4 text-right font-semibold tabular-nums whitespace-nowrap">
                                {{ $amount !== null ? format_money((float) $amount) : '—' }}
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex text-xs font-semibold rounded-full px-2.5 py-1 bg-amber-100 text-amber-900">
                                    {{ __('borrower.guarantor.action_required') }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right" onclick="event.stopPropagation()">
                                <a href="{{ $detailUrl }}" class="text-brand font-semibold hover:underline">
                                    {{ __('borrower.applications_list.view') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Mobile: Application Card pattern — full width, no squeezed columns --}}
<div class="lg:hidden">
    <div class="grid gap-4">
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
                    <span class="shrink-0 max-w-[45%] text-right text-xs font-semibold rounded-full px-2.5 py-1 bg-amber-100 text-amber-900 leading-snug">
                        {{ __('borrower.guarantor.action_required') }}
                    </span>
                </div>

                <p class="text-base font-bold tabular-nums text-gray-900 mb-1">
                    {{ $amount !== null ? format_money((float) $amount) : '—' }}
                </p>
                <p class="text-xs text-gray-600 mb-4">{{ __('borrower.guarantor.awaiting_your_decision') }}</p>

                <a href="{{ $detailUrl }}"
                   class="inline-flex items-center justify-center w-full font-bold px-5 py-3 rounded-xl text-sm bg-brand-gold hover:bg-yellow-400 text-brand shadow-sm">
                    {{ __('borrower.applications_list.view') }}
                </a>
            </div>
        @endforeach
    </div>
</div>
