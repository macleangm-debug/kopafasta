<x-site.borrower-layout :title="brand_title(__('borrower.offer.asset_conversion_title'))" active="loans">
    <div class="max-w-2xl mx-auto" x-data="{ channel: @js(old('channel', 'mobile_money')) }">
        <div class="mb-6">
            <a href="{{ route('site.borrower.application', $application) }}" class="text-sm text-amber-700 hover:text-amber-800">&larr; {{ __('borrower.offer.back_to_application') }}</a>
            <h1 class="text-2xl font-bold mt-2">{{ __('borrower.offer.asset_conversion_title') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $application->application_number }} · {{ $application->product?->name }}</p>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-6 mb-6 space-y-4">
            <p class="text-sm text-gray-700">{{ __('borrower.offer.asset_conversion_intro') }}</p>

            <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-gray-500">{{ __('borrower.offer.current_product') }}</dt>
                    <dd class="font-semibold mt-0.5">{{ $application->product?->name }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">{{ __('borrower.offer.suggested_product') }}</dt>
                    <dd class="font-semibold mt-0.5 text-amber-800">{{ $application->alternativeProduct?->name }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">{{ __('borrower.offer.prior_application_fee') }}</dt>
                    <dd class="font-semibold mt-0.5">{{ format_money($quote['prior_product_fee'] ?? 0) }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">{{ __('borrower.offer.new_application_fee') }}</dt>
                    <dd class="font-semibold mt-0.5">{{ format_money($quote['new_product_fee'] ?? 0) }}</dd>
                </div>
            </dl>

            @if (($quote['credit'] ?? 0) > 0)
                <p class="text-sm text-emerald-700">{{ __('borrower.offer.fee_credit_applied', ['amount' => format_money($quote['credit'])]) }}</p>
            @endif

            @if (($quote['due'] ?? 0) > 0)
                <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-4">
                    <p class="text-sm font-semibold text-amber-900">{{ __('borrower.offer.additional_fee_required') }}</p>
                    <p class="text-2xl font-bold text-amber-900 mt-1">{{ format_money($feeQuote['after_discount'] ?? $quote['due']) }}</p>
                </div>
            @else
                <p class="text-sm text-emerald-700 font-medium">{{ __('borrower.offer.no_additional_fee') }}</p>
            @endif
        </div>

        @php
            $offers = app(\App\Services\ApplicationOfferService::class);
            $awaitingAccept = $offers->pendingAssetConversion($application);
            $awaitingPayment = $offers->needsConversionFee($application);
        @endphp

        @if ($awaitingAccept)
            <form method="POST" action="{{ route('site.borrower.application.asset-conversion.respond', $application) }}" class="flex flex-wrap gap-3 mb-6">
                @csrf
                <button type="submit" name="decision" value="accept" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-3 rounded-full text-sm">
                    {{ __('borrower.offer.accept_asset_conversion') }}
                </button>
                <button type="submit" name="decision" value="decline" class="bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-800 font-semibold px-6 py-3 rounded-full text-sm"
                        onclick="return confirm(@js(__('borrower.offer.decline_confirm')))">
                    {{ __('borrower.offer.decline_asset_conversion') }}
                </button>
            </form>
        @endif

        @if ($awaitingPayment && ($quote['due'] ?? 0) > 0)
            @if (payment_gateway_is_dummy())
                <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900 mb-4">
                    {{ __('borrower.apply.application_fee.dummy_banner') }}
                </div>
            @endif

            <form method="POST" action="{{ route('site.borrower.application.asset-conversion.pay', $application) }}" enctype="multipart/form-data" class="space-y-4 bg-white rounded-2xl border border-gray-200 p-6">
                @csrf

                <div class="rounded-2xl bg-gradient-to-br from-amber-500 to-amber-600 text-white p-5">
                    <p class="text-[10px] uppercase tracking-widest text-white/80">{{ __('borrower.offer.additional_fee_required') }}</p>
                    <p class="mt-1 text-2xl font-extrabold">TZS {{ format_number($feeQuote['after_discount'] ?? $quote['due']) }}</p>
                    <p class="mt-2 text-xs text-white/90">{{ __('borrower.membership.payment_reference') }}: <span class="font-mono">{{ $paymentReference }}</span></p>
                </div>

                <div>
                    <p class="text-xs uppercase tracking-wider text-gray-500 mb-2">{{ __('borrower.membership.payment_method') }}</p>
                    <div class="grid sm:grid-cols-2 gap-3">
                        <label class="cursor-pointer">
                            <input type="radio" name="channel" value="mobile_money" x-model="channel" class="sr-only peer">
                            <div class="rounded-xl border-2 border-gray-200 peer-checked:border-amber-500 peer-checked:bg-amber-50 p-3 text-sm">
                                <p class="font-semibold">{{ __('borrower.membership.mobile_money') }}</p>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="channel" value="bank" x-model="channel" class="sr-only peer">
                            <div class="rounded-xl border-2 border-gray-200 peer-checked:border-amber-500 peer-checked:bg-amber-50 p-3 text-sm">
                                <p class="font-semibold">{{ __('borrower.membership.bank') }}</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div x-show="channel === 'mobile_money'" x-cloak>
                    <label class="block">
                        <span class="text-xs font-medium text-gray-600">{{ __('borrower.membership.mobile_number') }}</span>
                        <input type="text" name="mobile_number" value="{{ old('mobile_number', $customer->phone) }}" class="mt-1 w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm" placeholder="255712345678">
                    </label>
                </div>

                <div x-show="channel === 'bank'" x-cloak class="space-y-3">
                    @foreach ($bankAccounts as $account)
                        <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 p-4 text-sm">
                            <p class="font-semibold">{{ $account['bank_name'] ?? $account['bank'] ?? 'Bank' }}</p>
                            <p class="font-mono text-xs mt-1">{{ $account['account_number'] ?? '—' }}</p>
                            <p class="text-xs text-gray-600 mt-1">{{ $account['account_name'] ?? '' }}</p>
                        </div>
                    @endforeach
                    <label class="block">
                        <span class="text-xs font-medium text-gray-600">{{ __('borrower.membership.payment_date') }}</span>
                        <input type="date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" class="mt-1 w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm">
                    </label>
                    <label class="block">
                        <span class="text-xs font-medium text-gray-600">{{ __('borrower.membership.proof_optional') }}</span>
                        <input type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf" class="mt-1 w-full text-sm">
                    </label>
                </div>

                <button type="submit" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-3 rounded-full text-sm">
                    {{ __('borrower.post_approval_fees.pay_now') }}
                </button>
            </form>
        @endif
    </div>
</x-site.borrower-layout>
