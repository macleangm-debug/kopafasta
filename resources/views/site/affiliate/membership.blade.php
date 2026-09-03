<x-site.affiliate-layout :title="brand_title(__('site.affiliate_portal.membership_title'))" active="profile">

    <x-site.borrower-page-header
        :eyebrow="$eyebrow ?? __('site.affiliate_portal.title')"
        :title="__('site.affiliate_portal.membership_title')"
        :subtitle="__('site.affiliate_portal.membership_subtitle')"
    />

    @if (! empty($accountTabs))
        <x-site.partner-account-tabs active="profile" :tabs="$accountTabs" />
    @endif

    @if (isset($partner, $profileRoute))
        @include('site.partner-account._shell', [
            'partner' => $partner,
            'portal' => $portal ?? 'affiliate',
            'active' => 'membership',
            'profileRoute' => $profileRoute,
        ])
    @endif

  <div class="glass-card p-6 ring-1 ring-brand/15">
        <dl class="grid sm:grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-xs text-gray-500">{{ __('site.affiliate_portal.membership_status') }}</dt>
                <dd class="font-semibold text-gray-900 mt-0.5">{{ $membership['label'] }}</dd>
            </div>
            @unless ($membership['premium'] ?? false)
                <div>
                    <dt class="text-xs text-gray-500">{{ __('site.affiliate_portal.membership_fee') }}</dt>
                    <dd class="font-semibold text-gray-900 mt-0.5 tabular-nums">{{ format_money($membership['fee']) }}</dd>
                </div>
            @endunless
            @if ($membership['expires_at'])
                <div>
                    <dt class="text-xs text-gray-500">{{ __('site.affiliate_portal.membership_expires') }}</dt>
                    <dd class="font-semibold text-gray-900 mt-0.5">{{ $membership['expires_at']->format('d M Y') }}</dd>
                </div>
            @endif
        </dl>
        @unless (($membership['active'] ?? false) || ($membership['premium'] ?? false))
            <a href="{{ route('site.affiliate.membership.pay') }}"
               class="inline-flex mt-5 justify-center bg-brand hover:bg-brand-light text-white font-semibold px-5 py-2.5 rounded-xl text-sm">
                {{ __('site.affiliate_portal.membership_pay') }}
            </a>
        @endunless
    </div>

</x-site.affiliate-layout>
