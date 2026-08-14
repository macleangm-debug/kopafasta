@php
    $code = $availability['code'] ?? 'available';
    $number = $availability['application_number'] ?? null;
    $incomplete = $availability['incomplete'] ?? null;
    $tag = match ($code) {
        'on_this_loan' => [
            'label' => __('borrower.profile.collateral_on_this_loan'),
            'class' => 'bg-emerald-100 text-emerald-900',
            'hint' => __('borrower.profile.collateral_on_this_loan_hint'),
        ],
        'pledged_other' => [
            'label' => $number
                ? __('borrower.profile.collateral_tied_named', ['number' => $number])
                : __('borrower.profile.collateral_tied_other_loan'),
            'class' => 'bg-amber-100 text-amber-950',
            'hint' => __('borrower.profile.collateral_tied_other_loan_hint'),
        ],
        'declined' => [
            'label' => __('borrower.profile.collateral_declined'),
            'class' => 'bg-red-100 text-red-800',
            'hint' => __('borrower.profile.collateral_declined_hint'),
        ],
        'incomplete' => [
            'label' => __('borrower.profile.collateral_incomplete'),
            'class' => 'bg-slate-100 text-slate-800',
            'hint' => match ($incomplete) {
                'photos' => __('borrower.profile.collateral_incomplete_photos'),
                'ownership' => __('borrower.profile.collateral_incomplete_ownership'),
                'insurance' => __('borrower.profile.collateral_incomplete_insurance'),
                'insurance_expired' => __('borrower.profile.collateral_incomplete_insurance_expired'),
                default => __('borrower.profile.collateral_incomplete_hint'),
            },
        ],
        default => [
            'label' => ($availability['selectable'] ?? false)
                ? __('borrower.profile.collateral_ready')
                : __('borrower.profile.collateral_status_active'),
            'class' => 'bg-brand-muted text-brand',
            'hint' => ($availability['selectable'] ?? false)
                ? __('borrower.profile.collateral_ready_hint')
                : null,
        ],
    };
@endphp

<span @class(['inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold', $tag['class']])>
    {{ $tag['label'] }}
</span>
@if (! empty($showHint) && filled($tag['hint']))
    <p class="mt-1 text-xs text-gray-600">{{ $tag['hint'] }}</p>
@endif
