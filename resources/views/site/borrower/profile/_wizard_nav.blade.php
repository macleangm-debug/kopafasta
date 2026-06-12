{{-- Wizard navigation uses the same profile progress card as the regular profile tabs. --}}
@include('site.borrower.profile._kyc_progress', [
    'customer' => $customer,
    'active' => 'personal',
    'wizardMode' => $wizardMode ?? true,
    'wizardKey' => $currentKey ?? $wizardKey ?? 'nida',
])
