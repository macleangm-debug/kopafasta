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
            <div class="min-w-0">
                <p class="text-[11px] uppercase tracking-widest text-brand font-bold">{{ __('borrower.collateral_secure.eyebrow') }}</p>
                <h2 class="text-xl sm:text-2xl font-extrabold text-gray-900 mt-1 tracking-tight">{{ __('borrower.collateral_secure.title') }}</h2>
                <p class="text-sm sm:text-base font-semibold text-gray-700 mt-2 leading-snug">{{ __('borrower.collateral_secure.why') }}</p>
            </div>
            @if ($open && $daysLeft !== null)
                <span class="inline-flex text-xs font-bold rounded-full px-3 py-1.5 bg-brand-gold/90 text-brand shadow-sm">
                    {{ __('borrower.collateral_secure.days_left', ['days' => $daysLeft]) }}
                </span>
            @elseif ($status === 'secured')
                <span class="inline-flex text-xs font-bold rounded-full px-3 py-1.5 bg-emerald-100 text-emerald-900">
                    {{ __('borrower.collateral_secure.status_secured') }}
                </span>
            @endif
        </div>

        <div class="px-5 sm:px-6 py-6 space-y-5">
            @if ($selected)
                <div class="rounded-2xl ring-1 ring-brand/15 overflow-hidden bg-brand-muted/20">
                    <div class="flex gap-4 p-4">
                        <div class="shrink-0 size-20 sm:size-24 rounded-xl overflow-hidden bg-white ring-1 ring-gray-200">
                            @if (! empty($selected['thumbnail']))
                                <img src="{{ $selected['thumbnail'] }}" alt="" class="h-full w-full object-cover">
                            @else
                                <span class="h-full w-full grid place-items-center text-3xl">{{ $typeIcons[$selected['asset_type'] ?? ''] ?? '📦' }}</span>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] uppercase tracking-widest text-brand font-bold">{{ $selected['type_label'] ?? '' }}</p>
                            <p class="text-lg font-extrabold text-gray-900 mt-0.5 truncate">{{ $selected['label'] }}</p>
                            @if (! empty($selected['registration_number']))
                                <p class="text-sm font-semibold text-gray-700 mt-1">{{ __('borrower.profile.collateral_fields.registration_number') }}: {{ $selected['registration_number'] }}</p>
                            @endif
                            @if (! empty($selected['insurance_expires_at']))
                                <p class="text-sm font-semibold text-gray-700 mt-1">
                                    {{ __('borrower.collateral_secure.insurance_expires') }}:
                                    <span class="tabular-nums">{{ $selected['insurance_expires_at'] }}</span>
                                </p>
                            @endif
                            @if ($isGuarantorSource)
                                <p class="text-xs font-bold text-brand mt-2">{{ __('borrower.collateral_secure.from_guarantor') }}</p>
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
                <p class="text-base font-extrabold text-gray-900">{{ __('borrower.collateral_secure.insurance_needed') }}</p>
                @if (! empty($insurance['expiry']))
                    <p class="text-sm font-bold text-amber-900">
                        {{ __('borrower.collateral_secure.insurance_expires') }}:
                        <span class="tabular-nums">{{ $insurance['expiry'] }}</span>
                        @if (! empty($insurance['required_by']))
                            · {{ __('borrower.collateral_secure.insurance_required_by', ['date' => $insurance['required_by']]) }}
                        @endif
                    </p>
                @endif
                <p class="text-sm font-semibold text-gray-700">{{ __('borrower.collateral_secure.insurance_hint') }}</p>
                @if ($isGuarantorSource)
                    <p class="text-sm font-bold text-brand">{{ __('borrower.collateral_secure.insurance_guarantor_owns') }}</p>
                @else
                    <a href="{{ $secure['owner_assets_url'] }}"
                       class="inline-flex font-extrabold px-6 py-3 rounded-xl text-sm bg-brand-gold hover:brightness-95 text-brand shadow-sm">
                        {{ __('borrower.collateral_secure.update_insurance') }}
                    </a>
                    @if ($selected)
                        <form method="POST" action="{{ route('site.borrower.collateral-secure.link', $application) }}" class="pt-2">
                            @csrf
                            <input type="hidden" name="customer_asset_id" value="{{ $selected['id'] }}">
                            <button type="submit" class="inline-flex font-bold px-5 py-2.5 rounded-xl text-sm bg-brand text-white hover:bg-brand-light">
                                {{ __('borrower.collateral_secure.recheck_insurance') }}
                            </button>
                        </form>
                    @endif
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
