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
    <div class="space-y-10">
        @foreach ($rows as $row)
            @include('site.borrower.loans._guarantor-pending-preview', ['row' => $row])
        @endforeach
    </div>
@endif
