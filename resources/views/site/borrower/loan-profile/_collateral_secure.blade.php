@php
    $secure = $profile['collateral_secure'] ?? null;
    $application = $profile['application'] ?? null;
@endphp

@if ($application && ! empty($secure['active']))
    @php
        $status = $secure['status'] ?? null;
        $daysLeft = $secure['days_left'];
        $feeQuote = $secure['fee_quote'] ?? null;
        $assets = $secure['assets'] ?? collect();
        $open = (bool) ($secure['open'] ?? false);
    @endphp

    <div class="mb-6 overflow-hidden rounded-2xl ring-1 ring-brand/15 bg-gradient-to-br from-brand-muted/40 via-white to-white">
        <div class="px-5 sm:px-6 py-5 border-b border-brand/10 flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.collateral_secure.eyebrow') }}</p>
                <h2 class="text-lg font-bold text-gray-900 mt-1">{{ __('borrower.collateral_secure.title') }}</h2>
                <p class="text-sm text-gray-600 mt-1">{{ __('borrower.collateral_secure.why') }}</p>
            </div>
            @if ($open && $daysLeft !== null)
                <span class="inline-flex text-xs font-semibold rounded-full px-3 py-1.5 bg-amber-100 text-amber-900">
                    {{ __('borrower.collateral_secure.days_left', ['days' => $daysLeft]) }}
                </span>
            @elseif ($status === 'secured')
                <span class="inline-flex text-xs font-semibold rounded-full px-3 py-1.5 bg-emerald-100 text-emerald-800">
                    {{ __('borrower.collateral_secure.status_secured') }}
                </span>
            @endif
        </div>

        <div class="px-5 sm:px-6 py-5 space-y-4">
            @if ($status === 'awaiting_borrower_has_collateral')
                <p class="text-sm text-gray-800 font-medium">{{ __('borrower.collateral_secure.q_has_collateral') }}</p>
                <div class="flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('site.borrower.collateral-secure.has', $application) }}">
                        @csrf
                        <input type="hidden" name="has_collateral" value="1">
                        <button type="submit" class="inline-flex font-bold px-6 py-3 rounded-xl text-sm bg-brand-gold hover:brightness-95 text-brand shadow-sm">
                            {{ __('borrower.collateral_secure.yes') }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('site.borrower.collateral-secure.has', $application) }}">
                        @csrf
                        <input type="hidden" name="has_collateral" value="0">
                        <button type="submit" class="inline-flex font-semibold px-6 py-3 rounded-xl text-sm bg-white ring-1 ring-gray-200 text-gray-800 hover:bg-gray-50">
                            {{ __('borrower.collateral_secure.no') }}
                        </button>
                    </form>
                </div>
            @elseif ($status === 'awaiting_ask_guarantor')
                <p class="text-sm text-gray-800 font-medium">{{ __('borrower.collateral_secure.q_ask_guarantor') }}</p>
                <div class="flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('site.borrower.collateral-secure.ask-guarantor', $application) }}">
                        @csrf
                        <input type="hidden" name="ask_guarantor" value="1">
                        <button type="submit" class="inline-flex font-bold px-6 py-3 rounded-xl text-sm bg-brand-gold hover:brightness-95 text-brand shadow-sm">
                            {{ __('borrower.collateral_secure.yes_ask') }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('site.borrower.collateral-secure.ask-guarantor', $application) }}">
                        @csrf
                        <input type="hidden" name="ask_guarantor" value="0">
                        <button type="submit" class="inline-flex font-semibold px-6 py-3 rounded-xl text-sm bg-white ring-1 ring-gray-200 text-gray-800 hover:bg-gray-50">
                            {{ __('borrower.collateral_secure.no') }}
                        </button>
                    </form>
                </div>
            @elseif ($status === 'awaiting_guarantor_consent')
                <p class="text-sm text-gray-800">{{ __('borrower.collateral_secure.waiting_guarantor') }}</p>
            @elseif ($status === 'awaiting_borrower_add')
                <p class="text-sm text-gray-800 font-medium">{{ __('borrower.collateral_secure.choose_or_add') }}</p>
                <a href="{{ $secure['add_collateral_url'] }}"
                   class="inline-flex font-bold px-5 py-2.5 rounded-xl text-sm bg-brand-gold hover:brightness-95 text-brand shadow-sm">
                    {{ __('borrower.collateral_secure.add_collateral') }}
                </a>
                @if ($assets->isNotEmpty() && ($secure['state']['source'] ?? '') === 'borrower')
                    <form method="POST" action="{{ route('site.borrower.collateral-secure.link', $application) }}" class="mt-4 space-y-3">
                        @csrf
                        <label class="block text-xs font-semibold uppercase tracking-widest text-gray-500">{{ __('borrower.collateral_secure.select_saved') }}</label>
                        <select name="customer_asset_id" required class="w-full rounded-xl border-0 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                            <option value="">{{ __('borrower.collateral_secure.select_placeholder') }}</option>
                            @foreach ($assets as $asset)
                                <option value="{{ $asset->id }}">{{ $asset->label }} · {{ $asset->asset_type }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="inline-flex font-bold px-5 py-2.5 rounded-xl text-sm bg-brand text-white hover:bg-brand-light">
                            {{ __('borrower.collateral_secure.use_this') }}
                        </button>
                    </form>
                @elseif (($secure['state']['source'] ?? '') === 'guarantor')
                    <p class="text-sm text-gray-600 mt-2">{{ __('borrower.collateral_secure.waiting_guarantor_link') }}</p>
                @endif
            @elseif ($status === 'awaiting_insurance')
                <p class="text-sm text-gray-800 font-medium">{{ __('borrower.collateral_secure.insurance_needed') }}</p>
                <p class="text-xs text-gray-500">{{ __('borrower.collateral_secure.insurance_hint') }}</p>
                <a href="{{ route('site.borrower.profile', ['section' => 'assets']) }}"
                   class="inline-flex font-bold px-5 py-2.5 rounded-xl text-sm bg-brand-gold hover:brightness-95 text-brand shadow-sm">
                    {{ __('borrower.collateral_secure.update_insurance') }}
                </a>
            @elseif ($status === 'awaiting_fee')
                <p class="text-sm text-gray-800 font-medium">{{ __('borrower.collateral_secure.fee_title') }}</p>
                @if ($feeQuote)
                    <p class="text-2xl font-bold text-brand tabular-nums">{{ format_money($feeQuote['due'] ?? 0) }}</p>
                    <p class="text-xs text-gray-500">{{ __('borrower.collateral_secure.fee_hint') }}</p>
                @endif
                <form method="POST" action="{{ route('site.borrower.collateral-secure.pay', $application) }}">
                    @csrf
                    <button type="submit" class="inline-flex font-bold px-6 py-3 rounded-xl text-sm bg-brand-gold hover:brightness-95 text-brand shadow-sm">
                        {{ __('borrower.collateral_secure.pay_now') }}
                    </button>
                </form>
            @elseif ($status === 'secured')
                <p class="text-sm text-emerald-800">{{ __('borrower.collateral_secure.secured_body') }}</p>
            @elseif (in_array($status, ['rejected', 'expired'], true))
                <p class="text-sm text-red-800">{{ __('borrower.collateral_secure.rejected_body') }}</p>
            @endif
        </div>
    </div>
@endif
