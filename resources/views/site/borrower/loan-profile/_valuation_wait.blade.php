@php
    $wait = $profile['valuation_wait'] ?? null;
    $secureStatus = $profile['collateral_secure']['status'] ?? null;
@endphp

@if (! empty($wait['show']) && $secureStatus !== 'awaiting_valuer')
    <div id="valuation-wait" class="mb-6 overflow-hidden rounded-2xl ring-1 ring-brand/20 bg-white shadow-sm">
        <div class="px-5 sm:px-6 py-5 border-b border-brand/10 bg-gradient-to-br from-brand-muted/50 via-white to-white">
            <p class="text-[11px] uppercase tracking-widest text-brand font-bold">{{ __('borrower.collateral_secure.eyebrow') }}</p>
            @if (! empty($wait['no_regional_cover']))
                <h2 class="text-xl sm:text-2xl font-extrabold text-gray-900 mt-1 tracking-tight">{{ __('borrower.collateral_secure.awaiting_valuer_unassigned_title') }}</h2>
                <p class="text-sm text-gray-600 mt-1.5 leading-snug">{{ __('borrower.collateral_secure.awaiting_valuer_unassigned_body') }}</p>
            @elseif (! empty($wait['unassigned']))
                <h2 class="text-xl sm:text-2xl font-extrabold text-gray-900 mt-1 tracking-tight">{{ __('borrower.collateral_secure.awaiting_valuer_pending_title') }}</h2>
                <p class="text-sm text-gray-600 mt-1.5 leading-snug">{{ __('borrower.collateral_secure.awaiting_valuer_pending_body') }}</p>
            @else
                <h2 class="text-xl sm:text-2xl font-extrabold text-gray-900 mt-1 tracking-tight">{{ __('borrower.collateral_secure.awaiting_valuer_title') }}</h2>
                <p class="text-sm text-gray-600 mt-1.5 leading-snug">{{ __('borrower.collateral_secure.awaiting_valuer_body', [
                    'name' => $wait['valuer_name'] ?: __('borrower.collateral_secure.valuer_generic_name'),
                ]) }}</p>
            @endif
        </div>
    </div>
@endif
