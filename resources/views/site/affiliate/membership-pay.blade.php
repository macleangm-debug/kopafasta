@php
    $cfg = $config ?? \App\Services\AffiliateMembershipService::config();
@endphp
<x-site.affiliate-layout :title="brand_title(__('site.affiliate_portal.membership_pay'))" active="profile">
    <x-site.borrower-page-header
        :eyebrow="__('site.affiliate_portal.membership_title')"
        :title="__('site.affiliate_portal.membership_pay')"
        :subtitle="__('site.affiliate_portal.membership_subtitle')"
    />

    <div class="max-w-xl space-y-5">
        <x-site.payment-gate
            :title="__('site.affiliate_portal.membership_pay')"
            :fee-label="__('site.affiliate_portal.membership_fee')"
            :currency="$cfg['currency'] ?? 'TZS'"
            :amount="$cfg['fee_amount'] ?? 50000"
            :reference="$paymentReference"
            payment-type="registration_fee"
            promo-field-name="promo_code"
            :promo-value="old('promo_code', request('promo_code'))"
        />

        @if ($errors->any())
            <div class="rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('site.affiliate.membership.pay.post') }}" class="glass-card p-6 space-y-4 form-scroll-lock">
            @csrf
            <input type="hidden" name="payment_reference" value="{{ $paymentReference }}">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('borrower.payment_details.method_mobile') }} / {{ __('borrower.payment_details.method_bank') }}</label>
                <div class="grid sm:grid-cols-2 gap-3 text-sm">
                    <label class="flex items-center gap-3 rounded-xl ring-1 ring-gray-200 bg-white px-4 py-3 cursor-pointer has-[:checked]:ring-brand has-[:checked]:bg-brand-muted/40">
                        <input type="radio" name="channel" value="mobile_money" checked class="text-brand">
                        <span class="font-semibold text-gray-900">{{ __('borrower.payment_details.method_mobile') }}</span>
                    </label>
                    <label class="flex items-center gap-3 rounded-xl ring-1 ring-gray-200 bg-white px-4 py-3 cursor-pointer has-[:checked]:ring-brand has-[:checked]:bg-brand-muted/40">
                        <input type="radio" name="channel" value="bank" class="text-brand">
                        <span class="font-semibold text-gray-900">{{ __('borrower.payment_details.method_bank') }}</span>
                    </label>
                </div>
            </div>

            @if (! empty($mobileDetails))
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 p-4 text-sm space-y-1">
                    <p class="font-semibold text-gray-900">{{ __('borrower.payment_details.method_mobile') }}</p>
                    @foreach ($mobileDetails as $line)
                        <p class="text-gray-700">{{ is_array($line) ? implode(' · ', $line) : $line }}</p>
                    @endforeach
                </div>
            @endif

            @if (! empty($bankAccounts))
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 p-4 text-sm space-y-2">
                    <p class="font-semibold text-gray-900">{{ __('borrower.payment_details.method_bank') }}</p>
                    @foreach ($bankAccounts as $account)
                        <p class="text-gray-700">{{ is_array($account) ? ($account['label'] ?? json_encode($account)) : $account }}</p>
                    @endforeach
                </div>
            @endif

            <x-site.phone-input name="payment_phone" :label="__('borrower.payment_details.phone_number')" :value="old('payment_phone', $vendor->phone)" variant="rounded" />

            <button type="submit" class="w-full bg-brand hover:bg-brand-light text-white font-semibold px-5 py-3 rounded-xl text-sm">
                {{ __('site.affiliate_portal.membership_confirm_paid') }}
            </button>
        </form>
    </div>
</x-site.affiliate-layout>
