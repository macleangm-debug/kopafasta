@props([
    'title',
    'subtitle',
    'customer',
    'active' => 'personal',
    'wizardMode' => false,
    'wizardKey' => null,
])

@include('site.borrower.profile._heading', [
    'title' => $title,
    'subtitle' => $subtitle,
])

@if ($wizardMode)
    @include('site.borrower.profile._kyc_progress', [
        'customer' => $customer,
        'active' => $active,
        'wizardMode' => true,
        'wizardKey' => $wizardKey,
    ])
@else
    @include('site.borrower.profile._tabs', ['active' => $active])
    @include('site.borrower.profile._kyc_progress', ['customer' => $customer, 'active' => $active])
    @include('site.borrower.profile._completion')
@endif
