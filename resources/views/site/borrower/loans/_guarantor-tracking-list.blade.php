@php
    $rows = $rows ?? collect();
    $viewMode = $viewMode ?? 'cards';
    $ctaLabel = __('borrower.applications_list.view_progress');
@endphp

{{-- Desktop: reuse applications-table layout/spacing (no arbitrary column % widths) --}}
<div class="hidden lg:block">
    <div class="glass-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('borrower.loans_page.borrower') }}</th>
                        <th class="px-4 py-3">{{ __('borrower.guarantor_invite.product_label') }}</th>
                        <th class="px-4 py-3">{{ __('borrower.loans_page.loan_amount') }}</th>
                        <th class="px-4 py-3">{{ __('borrower.guaranteed.current_step') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('borrower.applications_list.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($rows as $row)
                        @php
                            $borrowerName = $row->borrower?->legalDisplayName() ?? __('borrower.loans_page.borrower');
                            $productName = $row->product?->localizedName() ?? __('borrower.guarantor.loan');
                            $needsProfile = $row->needs_guarantor_profile ?? false;
                            $needsReconfirm = (bool) ($row->needs_reconfirm ?? false);
                            $detailUrl = $needsReconfirm && ! empty($row->reconfirm_url)
                                ? $row->reconfirm_url
                                : route('site.borrower.guaranteed.show', $row->link);
                            $rowCta = $needsReconfirm
                                ? __('borrower.guarantor_invite.reconfirm_cta')
                                : $ctaLabel;
                        @endphp
                        <tr class="hover:bg-brand-muted/20 cursor-pointer transition {{ ($row->is_terminal ?? false) ? 'opacity-75' : '' }}"
                            data-kf-share="kf-gtd-{{ $row->link->id }}"
                            onclick="window.location='{{ $detailUrl }}'">
                            <td class="px-4 py-3">
                                <p class="font-bold text-gray-900 leading-snug">{{ $borrowerName }}</p>
                                <p class="font-mono text-xs text-gray-500 mt-0.5">{{ $row->reference }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-800">{{ $productName }}</td>
                            <td class="px-4 py-3 font-semibold tabular-nums whitespace-nowrap">{{ format_money($row->amount) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex text-xs font-semibold rounded-full px-2.5 py-1 {{ ($needsProfile || $needsReconfirm) ? 'bg-amber-100 text-amber-900' : 'bg-sky-100 text-sky-800' }}">
                                    {{ $row->stage_label }}
                                </span>
                                @if (! empty($row->deadline_label))
                                    <p class="text-[11px] text-gray-500 mt-1">{{ $row->deadline_label }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap" onclick="event.stopPropagation()">
                                <a href="{{ $detailUrl }}" data-kf-motion="push" class="text-brand font-semibold hover:underline text-xs">
                                    {{ $rowCta }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Mobile: reuse applications-card pattern — compact CTA, not full-width --}}
<div class="lg:hidden">
    <div class="grid gap-4">
        @foreach ($rows as $row)
            @php
                $borrowerName = $row->borrower?->legalDisplayName() ?? __('borrower.loans_page.borrower');
                $productName = $row->product?->localizedName() ?? __('borrower.guarantor.loan');
                $needsProfile = (bool) ($row->needs_guarantor_profile ?? false);
                $needsReconfirm = (bool) ($row->needs_reconfirm ?? false);
                $detailUrl = $needsReconfirm && ! empty($row->reconfirm_url)
                    ? $row->reconfirm_url
                    : route('site.borrower.guaranteed.show', $row->link);
                $isTerminal = (bool) ($row->is_terminal ?? false);
                $rowCta = $needsReconfirm
                    ? __('borrower.guarantor_invite.reconfirm_cta')
                    : $ctaLabel;
            @endphp
            <div class="glass-card p-5 {{ $isTerminal ? 'opacity-80' : '' }}" data-kf-share="kf-gtd-{{ $row->link->id }}">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ $productName }}</p>
                        <p class="text-lg font-bold text-gray-900 tracking-tight mt-0.5 leading-snug">{{ $borrowerName }}</p>
                        <p class="font-mono text-xs text-gray-500 mt-1">{{ $row->reference }}</p>
                    </div>
                    <span class="shrink-0 max-w-[45%] text-right text-xs font-semibold rounded-full px-2.5 py-1 leading-snug {{ ($needsProfile || $needsReconfirm) ? 'bg-amber-100 text-amber-900' : 'bg-sky-100 text-sky-800' }}">
                        {{ $row->stage_label }}
                    </span>
                </div>

                <p class="text-base font-bold tabular-nums text-gray-900 mb-1">{{ format_money($row->amount) }}</p>

                @if (! empty($row->deadline_label))
                    <p class="text-xs font-semibold mb-3 {{ ($row->deadline_urgent ?? false) ? 'text-red-700' : 'text-brand' }}">
                        {{ $row->deadline_label }}
                    </p>
                @elseif ($row->pending_hint)
                    <p class="text-xs text-gray-600 mb-3">{{ $row->pending_hint }}</p>
                @elseif ($needsProfile)
                    <p class="text-xs text-amber-800 font-semibold mb-3">
                        {{ __('borrower.guaranteed.your_profile_pct', ['percent' => (int) ($row->profile_percent ?? 0)]) }}
                    </p>
                @else
                    <div class="mb-3"></div>
                @endif

                <div class="flex items-center gap-2 text-xs flex-wrap">
                    <a href="{{ $detailUrl }}"
                       @class([
                           'inline-flex font-semibold px-4 py-2 rounded-xl text-sm',
                           'bg-brand-gold hover:brightness-95 text-brand' => $needsReconfirm || $needsProfile,
                           'bg-brand hover:bg-brand-light text-white' => ! ($needsReconfirm || $needsProfile),
                       ])>
                        {{ $rowCta }}
                    </a>
                </div>
            </div>
        @endforeach
    </div>
</div>
