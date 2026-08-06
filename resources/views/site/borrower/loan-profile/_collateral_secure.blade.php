@php
    $secure = $profile['collateral_secure'] ?? null;
    $application = $profile['application'] ?? null;
@endphp

@if ($application && ! empty($secure['active']))
    @php
        $status = $secure['status'] ?? null;
        $daysLeft = $secure['days_left'];
        $feeQuote = $secure['fee_quote'] ?? null;
        $assetCards = collect($secure['assets'] ?? []);
        $selected = $secure['selected_asset'] ?? null;
        $insurance = $secure['insurance'] ?? null;
        $isGuarantorSource = (bool) ($secure['is_guarantor_source'] ?? false);
        $open = (bool) ($secure['open'] ?? false);
        $typeIcons = \App\Models\CustomerAsset::typeIcons();
    @endphp

    <div class="mb-6 overflow-hidden rounded-2xl ring-1 ring-brand/20 bg-white shadow-sm">
        <div class="px-5 sm:px-6 py-5 border-b border-brand/10 bg-gradient-to-br from-brand-muted/50 via-white to-white flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <p class="text-[11px] uppercase tracking-widest text-brand font-bold">{{ __('borrower.collateral_secure.eyebrow') }}</p>
                <h2 class="text-xl sm:text-2xl font-extrabold text-gray-900 mt-1 tracking-tight">{{ __('borrower.collateral_secure.title') }}</h2>
                <p class="text-sm text-gray-600 mt-1.5 leading-snug">{{ __('borrower.collateral_secure.why') }}</p>
            </div>
            @if ($open && $daysLeft !== null)
                <span @class([
                    'inline-flex text-xs font-bold rounded-full px-3 py-1.5 shadow-sm shrink-0',
                    'bg-amber-100 text-amber-900' => ! empty($secure['in_grace']),
                    'bg-brand-gold/90 text-brand' => empty($secure['in_grace']),
                ])>
                    @if (! empty($secure['in_grace']))
                        {{ __('borrower.collateral_secure.grace_days_left', ['days' => $daysLeft]) }}
                    @else
                        {{ __('borrower.collateral_secure.days_left', ['days' => $daysLeft]) }}
                    @endif
                </span>
            @elseif ($status === 'secured')
                <span class="inline-flex text-xs font-bold rounded-full px-3 py-1.5 bg-emerald-100 text-emerald-900 shrink-0">
                    {{ __('borrower.collateral_secure.status_secured') }}
                </span>
            @endif
        </div>

        <div class="px-5 sm:px-6 py-5 space-y-4">
            @if ($selected)
                <div class="rounded-2xl ring-1 ring-brand/15 overflow-hidden bg-brand-muted/20">
                    <div class="flex gap-3 sm:gap-4 p-3.5 sm:p-4 items-center">
                        <div class="shrink-0 size-16 sm:size-20 rounded-xl overflow-hidden bg-white ring-1 ring-gray-200">
                            @if (! empty($selected['thumbnail']))
                                <img src="{{ $selected['thumbnail'] }}" alt="" class="h-full w-full object-cover">
                            @else
                                <span class="h-full w-full grid place-items-center text-2xl">{{ $typeIcons[$selected['asset_type'] ?? ''] ?? '📦' }}</span>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] uppercase tracking-widest text-brand font-bold">{{ $selected['type_label'] ?? '' }}</p>
                            <p class="text-base sm:text-lg font-extrabold text-gray-900 mt-0.5 truncate">{{ $selected['label'] }}</p>
                            @if (! empty($selected['registration_number']))
                                <p class="text-xs sm:text-sm font-semibold text-gray-600 mt-0.5 truncate">{{ __('borrower.profile.collateral_fields.registration_number') }}: {{ $selected['registration_number'] }}</p>
                            @endif
                            @if ($isGuarantorSource)
                                <p class="text-[11px] font-bold text-brand mt-1">{{ __('borrower.collateral_secure.from_guarantor') }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            @if ($status === 'awaiting_borrower_has_collateral')
                <p class="text-base font-bold text-gray-900">{{ __('borrower.collateral_secure.q_has_collateral') }}</p>
                <div class="flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('site.borrower.collateral-secure.has', $application) }}">
                        @csrf
                        <input type="hidden" name="has_collateral" value="1">
                        <button type="submit" class="inline-flex font-extrabold px-7 py-3.5 rounded-xl text-sm bg-brand-gold hover:brightness-95 text-brand shadow-sm">
                            {{ __('borrower.collateral_secure.yes') }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('site.borrower.collateral-secure.has', $application) }}">
                        @csrf
                        <input type="hidden" name="has_collateral" value="0">
                        <button type="submit" class="inline-flex font-bold px-7 py-3.5 rounded-xl text-sm bg-white ring-1 ring-gray-200 text-gray-900 hover:bg-gray-50">
                            {{ __('borrower.collateral_secure.no') }}
                        </button>
                    </form>
                </div>
            @elseif ($status === 'awaiting_ask_guarantor')
                <p class="text-base font-bold text-gray-900">{{ __('borrower.collateral_secure.q_ask_guarantor') }}</p>
                <div class="flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('site.borrower.collateral-secure.ask-guarantor', $application) }}"
                          @submit.prevent="window.confirmForm($el, {
                              title: @js(__('borrower.collateral_secure.ask_confirm_title')),
                              message: @js(__('borrower.collateral_secure.ask_confirm_body')),
                              confirmLabel: @js(__('borrower.collateral_secure.yes_ask')),
                              confirmClass: 'bg-brand-gold hover:brightness-95 text-brand font-extrabold',
                              tone: 'confirm'
                          })">
                        @csrf
                        <input type="hidden" name="ask_guarantor" value="1">
                        <button type="submit" class="inline-flex font-extrabold px-7 py-3.5 rounded-xl text-sm bg-brand-gold hover:brightness-95 text-brand shadow-sm">
                            {{ __('borrower.collateral_secure.yes_ask') }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('site.borrower.collateral-secure.ask-guarantor', $application) }}">
                        @csrf
                        <input type="hidden" name="ask_guarantor" value="0">
                        <button type="submit" class="inline-flex font-bold px-7 py-3.5 rounded-xl text-sm bg-white ring-1 ring-gray-200 text-gray-900 hover:bg-gray-50">
                            {{ __('borrower.collateral_secure.no') }}
                        </button>
                    </form>
                </div>
            @elseif ($status === 'awaiting_guarantor_consent')
                <p class="text-base font-bold text-gray-900">{{ __('borrower.collateral_secure.waiting_guarantor') }}</p>
            @elseif ($status === 'awaiting_borrower_add')
                @if (($secure['state']['source'] ?? '') === 'guarantor')
                    <p class="text-base font-bold text-gray-900">{{ __('borrower.collateral_secure.waiting_guarantor_link') }}</p>
                @else
                    <p class="text-base font-bold text-gray-900">{{ __('borrower.collateral_secure.choose_or_add') }}</p>
                    <a href="{{ $secure['add_collateral_url'] }}"
                       class="inline-flex font-extrabold px-6 py-3 rounded-xl text-sm bg-brand-gold hover:brightness-95 text-brand shadow-sm">
                        {{ __('borrower.collateral_secure.add_collateral') }}
                    </a>
                    @include('site.borrower.loan-profile._collateral_secure_picker', [
                        'assetCards' => $assetCards,
                        'typeIcons' => $typeIcons,
                        'formAction' => route('site.borrower.collateral-secure.link', $application),
                        'confirmTitle' => __('borrower.collateral_secure.use_confirm_title'),
                        'confirmBody' => __('borrower.collateral_secure.use_confirm_body'),
                    ])
                @endif
            @elseif ($status === 'awaiting_insurance')
                @php
                    $purchase = $secure['insurance_purchase'] ?? null;
                    $quoteDefaults = $secure['insurance_quote_defaults'] ?? [];
                    $ratePct = (float) ($quoteDefaults['rate_percent'] ?? 3.5);
                    $suggested = (int) ($quoteDefaults['suggested_value'] ?? 0);
                    $isOwner = (int) auth()->user()?->customer?->id === (int) ($secure['owner_customer_id'] ?? 0);
                @endphp

                @if ($purchase)
                    <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 space-y-1">
                        <p class="text-sm font-extrabold text-emerald-950">{{ __('borrower.collateral_secure.insurance_purchase_pending') }}</p>
                        <p class="text-sm font-semibold text-emerald-900">
                            {{ __('borrower.collateral_secure.insurance_purchase_summary', [
                                'value' => format_money($purchase['insured_value'] ?? 0),
                                'premium' => format_money($purchase['premium'] ?? 0),
                            ]) }}
                        </p>
                    </div>
                @elseif ($isGuarantorSource && ! $isOwner)
                    <div class="rounded-xl bg-brand-muted/40 ring-1 ring-brand/15 px-4 py-4 space-y-3">
                        <p class="text-sm font-extrabold text-brand">{{ __('borrower.collateral_secure.insurance_waiting_guarantor') }}</p>
                        @if ($selected)
                            <form method="POST" action="{{ route('site.borrower.collateral-secure.link', $application) }}">
                                @csrf
                                <input type="hidden" name="customer_asset_id" value="{{ $selected['id'] }}">
                                <button type="submit" class="inline-flex font-extrabold px-5 py-2.5 rounded-xl text-sm bg-brand-gold hover:brightness-95 text-brand shadow-sm">
                                    {{ __('borrower.collateral_secure.check_again') }}
                                </button>
                            </form>
                        @endif
                    </div>
                @elseif ($isOwner)
                    @php
                        $insReason = $insurance['reason'] ?? 'missing';
                        $assetInsType = $selected['insurance_type'] ?? null;
                        $insureHint = match (true) {
                            $assetInsType === 'third_party' => __('borrower.collateral_secure.insure_asset_hint_third_party'),
                            in_array($insReason, ['expiring_soon', 'buffer'], true) => __('borrower.collateral_secure.insure_asset_hint_expiring'),
                            default => __('borrower.collateral_secure.insure_asset_hint_missing'),
                        };
                        $markupPct = (float) ($quoteDefaults['markup_percent'] ?? 0);
                        $effectiveRate = $ratePct * (1 + ($markupPct / 100));
                    @endphp
                    <div class="space-y-4"
                         x-data="{
                             raw: '{{ number_format($suggested > 0 ? $suggested : 1000000) }}',
                             rate: {{ $effectiveRate }},
                             value() {
                                 const n = Number(String(this.raw || '').replace(/[^\d]/g, ''));
                                 return Number.isFinite(n) ? n : 0;
                             },
                             premium() { return Math.round(this.value() * (this.rate / 100)); }
                         }">
                        <div>
                            <p class="text-sm font-bold text-gray-900">{{ $insureHint }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ __('borrower.collateral_secure.insure_asset_eta') }}</p>
                        </div>
                        <form method="POST" action="{{ route('site.borrower.collateral-secure.buy-insurance', $application) }}"
                              class="rounded-2xl ring-1 ring-brand/15 bg-brand-muted/20 p-4 space-y-3">
                            @csrf
                            <label class="block">
                                <span class="text-xs font-bold uppercase tracking-widest text-gray-600">{{ __('borrower.collateral_secure.insured_value_label') }}</span>
                                <input type="text" name="insured_value" x-model="raw" data-money-input="0" inputmode="numeric" autocomplete="off" required
                                       class="mt-1.5 w-full rounded-xl border-gray-200 text-base font-extrabold tabular-nums">
                                <span class="mt-1 block text-xs text-gray-500">{{ __('borrower.collateral_secure.insured_value_help') }}</span>
                            </label>
                            <p class="text-sm font-bold text-gray-800">
                                {{ __('borrower.collateral_secure.premium_label') }}:
                                <span class="text-brand text-lg tabular-nums" x-text="new Intl.NumberFormat().format(premium())"></span>
                                <span class="text-xs font-semibold text-gray-500">({{ rtrim(rtrim(number_format($effectiveRate, 2), '0'), '.') }}%)</span>
                            </p>
                            <button type="submit" class="w-full sm:w-auto inline-flex justify-center font-extrabold px-6 py-3 rounded-xl text-sm bg-brand-gold hover:brightness-95 text-brand shadow-sm">
                                {{ __('borrower.collateral_secure.insure_asset') }}
                            </button>
                        </form>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ $secure['owner_assets_url'] }}"
                               class="inline-flex font-bold px-5 py-2.5 rounded-xl text-sm bg-white ring-1 ring-gray-200 text-gray-900 hover:bg-gray-50">
                                {{ __('borrower.collateral_secure.update_insurance') }}
                            </a>
                            @if ($selected)
                                <form method="POST" action="{{ route('site.borrower.collateral-secure.link', $application) }}">
                                    @csrf
                                    <input type="hidden" name="customer_asset_id" value="{{ $selected['id'] }}">
                                    <button type="submit" class="inline-flex font-bold px-5 py-2.5 rounded-xl text-sm bg-brand text-white hover:bg-brand-light">
                                        {{ __('borrower.collateral_secure.check_again') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endif
            @elseif ($status === 'awaiting_fee')
                <p class="text-base font-extrabold text-gray-900">{{ __('borrower.collateral_secure.fee_title') }}</p>
                @if ($feeQuote)
                    <p class="text-3xl font-extrabold text-brand tabular-nums">{{ format_money($feeQuote['due'] ?? 0) }}</p>
                    <p class="text-sm font-semibold text-gray-600">{{ __('borrower.collateral_secure.fee_hint') }}</p>
                @endif
                <form method="POST" action="{{ route('site.borrower.collateral-secure.pay', $application) }}">
                    @csrf
                    <button type="submit" class="inline-flex font-extrabold px-7 py-3.5 rounded-xl text-sm bg-brand-gold hover:brightness-95 text-brand shadow-sm">
                        {{ __('borrower.collateral_secure.pay_now') }}
                    </button>
                </form>
            @elseif ($status === 'secured')
                <p class="text-base font-bold text-emerald-900">{{ __('borrower.collateral_secure.secured_body') }}</p>
            @elseif (in_array($status, ['rejected', 'expired'], true))
                <p class="text-base font-bold text-red-800">{{ __('borrower.collateral_secure.rejected_body') }}</p>
            @endif
        </div>
    </div>
@endif

@if (session('collateral_secure_flash'))
    @php $flash = session('collateral_secure_flash'); @endphp
    <div x-data x-init="
        $nextTick(() => window.confirmAction({
            title: @js($flash['title'] ?? ''),
            message: @js($flash['message'] ?? ''),
            confirmLabel: @js($flash['confirm'] ?? __('borrower.feedback.ok')),
            confirmClass: 'bg-brand-gold hover:brightness-95 text-brand font-extrabold',
            tone: @js($flash['tone'] ?? 'success'),
            onConfirm: () => {}
        }))
    "></div>
@endif
