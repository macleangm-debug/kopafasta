@php
    $accountTabs = [
        ['key' => 'profile', 'label' => __('site.partner_account.tab_profile'), 'url' => route('site.affiliate.profile')],
        ['key' => 'settings', 'label' => __('site.partner_account.tab_settings'), 'url' => route('site.affiliate.settings')],
    ];
    $membership = $membership ?? app(\App\Services\AffiliateMembershipService::class)->summary($vendor);
@endphp

<x-site.affiliate-layout :title="brand_title(__('site.partner_account.settings_title'))" active="profile">

    <x-site.borrower-page-header
        :eyebrow="__('site.affiliate_portal.title')"
        :title="__('site.partner_account.settings_title')"
        :subtitle="__('site.partner_account.settings_subtitle')"
    />

    <x-site.partner-account-tabs active="settings" :tabs="$accountTabs" />

    @if (! empty($membership['enabled']))
        <div class="glass-card p-6 mb-6 ring-1 ring-brand/15">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('site.affiliate_portal.membership_title') }}</p>
                    <h2 class="text-lg font-bold text-gray-900 mt-1">{{ __('site.affiliate_portal.membership_subtitle') }}</h2>
                    <dl class="mt-4 grid sm:grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-xs text-gray-500">{{ __('site.affiliate_portal.membership_status') }}</dt>
                            <dd class="font-semibold text-gray-900 mt-0.5">{{ $membership['label'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">{{ __('site.affiliate_portal.membership_fee') }}</dt>
                            <dd class="font-semibold text-gray-900 mt-0.5 tabular-nums">{{ format_money($membership['fee']) }}</dd>
                        </div>
                        @if ($membership['expires_at'])
                            <div>
                                <dt class="text-xs text-gray-500">{{ __('site.affiliate_portal.membership_expires') }}</dt>
                                <dd class="font-semibold text-gray-900 mt-0.5">{{ $membership['expires_at']->format('d M Y') }}</dd>
                            </div>
                        @endif
                        @if ($membership['due_at'] && ! $membership['active'])
                            <div>
                                <dt class="text-xs text-gray-500">{{ __('site.affiliate_portal.membership_due') }}</dt>
                                <dd class="font-semibold text-amber-800 mt-0.5">{{ $membership['due_at']->format('d M Y H:i') }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
                @unless ($membership['active'])
                    <a href="{{ route('site.affiliate.membership.pay') }}"
                       class="inline-flex justify-center bg-brand hover:bg-brand-light text-white font-semibold px-5 py-2.5 rounded-xl text-sm shrink-0">
                        {{ __('site.affiliate_portal.membership_pay') }}
                    </a>
                @endunless
            </div>
        </div>
    @endif

    @include('site.partner-account._settings', [
        'partner' => $vendor,
        'supportRoute' => null,
        'pinUpdateRoute' => route('site.affiliate.settings.pin'),
    ])

</x-site.affiliate-layout>
