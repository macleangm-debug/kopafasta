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
                        $productName = $row->product?->name ?? __('borrower.guarantor.loan');
                        $needsProfile = $row->needs_guarantor_profile ?? false;
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
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if ($needsProfile)
                                <a href="{{ $row->profile_url }}" class="text-brand font-bold hover:underline">{{ __('borrower.guarantor.complete_profile') }}</a>
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
    <div class="space-y-8">
        @foreach ($rows as $row)
            @include('site.borrower.loans._guarantor-profile-preview', ['row' => $row])
        @endforeach
    </div>
@endif
