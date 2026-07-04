<x-site.borrower-layout :title="brand_title(__('borrower.profile.account_title'))" active="profile" content-width="wide">

    @include('site.borrower.profile._profile_shell', [
        'title' => __('borrower.profile.account_title'),
        'subtitle' => __('borrower.membership.card_subtitle'),
        'customer' => $customer,
        'active' => 'membership',
        'accountPanel' => 'membership',
        'wizardMode' => false,
    ])

    @include('site.borrower.profile._membership_panel', [
        'customer' => $customer,
        'history' => $history,
        'referralCode' => $referralCode,
        'referralWallet' => $referralWallet,
    ])

</x-site.borrower-layout>
