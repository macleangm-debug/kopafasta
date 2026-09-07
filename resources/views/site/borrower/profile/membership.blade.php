<x-site.borrower-layout :title="brand_title(__('borrower.membership.my_card'))" active="profile" content-width="wide">

    <div>
        @include('site.borrower.profile._profile_shell', [
            'title' => __('borrower.membership.my_card'),
            'subtitle' => null,
            'customer' => $customer,
            'active' => 'personal',
            'accountPanel' => 'membership',
            'wizardMode' => false,
        ])

        <x-site.member-card
            :customer="$customer"
            :referral-code="$referralCode ?? null"
            :referral-link="$referralLink ?? null"
            class="mb-8"
        />
    </div>

</x-site.borrower-layout>
