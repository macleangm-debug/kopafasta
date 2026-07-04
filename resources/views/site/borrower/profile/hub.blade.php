<x-site.borrower-layout :title="brand_title(__('borrower.profile.account_title'))" active="profile" content-width="wide">

    @include('site.borrower.profile._profile_shell', [
        'title' => __('borrower.profile.account_title'),
        'subtitle' => __('borrower.profile.subtitle'),
        'customer' => $customer,
        'active' => 'hub',
        'accountPanel' => 'profile',
    ])

</x-site.borrower-layout>
