@props([
    'paymentLabel' => '',
    'amount' => 0,
    'currency' => 'TZS',
    'reference' => '',
    'formId' => null,
    'methodField' => 'payment_method',
    'mobileField' => 'mobile_number',
    'defaultMethod' => 'mobile_money',
    'mobileThreshold' => null,
    'mobileDetails' => [],
    'bankDetails' => [],
    'showMobile' => true,
    'showBank' => true,
])

@php
    $threshold = $mobileThreshold ?? config('payments.mobile_money_threshold', 3_000_000);
    $bankOnlyMessage = __('borrower.payments_page.create.bank_only', ['threshold' => format_money($threshold)]);
@endphp

<div x-data="{
    amount: {{ (int) $amount }},
    method: @js(old($methodField, $defaultMethod)),
    threshold: {{ (int) $threshold }},
    get mobileAllowed() { return !this.threshold || this.amount <= this.threshold; },
}" class="space-y-5">
    <div class="rounded-2xl bg-gradient-to-br from-brand to-brand-light text-white p-6 shadow-lg">
        <p class="text-[10px] uppercase tracking-widest text-white/80">{{ $paymentLabel }}</p>
        <p class="mt-1 text-3xl font-extrabold tabular-nums">{{ $currency }} {{ format_number($amount) }}</p>
        @if ($reference)
            <p class="mt-3 text-xs text-white/90">{{ __('borrower.membership.payment_reference_label') }}</p>
            <p class="mt-1 font-mono text-sm bg-white/15 inline-block px-3 py-1 rounded-lg">{{ $reference }}</p>
        @endif
    </div>

    <div>
        <label class="block text-xs font-medium text-gray-600 mb-2">{{ __('borrower.payments_page.create.method_label') }}</label>
        <div class="grid grid-cols-2 gap-2">
            @if ($showMobile)
                <label class="cursor-pointer" x-show="mobileAllowed">
                    <input type="radio" name="{{ $methodField }}" value="mobile_money" class="sr-only peer" x-model="method" @if($formId) form="{{ $formId }}" @endif required>
                    <div class="rounded-xl border-2 border-gray-200 peer-checked:border-brand peer-checked:bg-brand-muted/50 px-3 py-3 text-center text-xs font-medium transition">
                        {{ __('borrower.payments_page.create.mobile_money') }}
                    </div>
                </label>
            @endif
            @if ($showBank)
                <label class="cursor-pointer" :class="!mobileAllowed && 'col-span-2'">
                    <input type="radio" name="{{ $methodField }}" value="bank_transfer" class="sr-only peer" x-model="method" @if($formId) form="{{ $formId }}" @endif required>
                    <div class="rounded-xl border-2 border-gray-200 peer-checked:border-brand peer-checked:bg-brand-muted/50 px-3 py-3 text-center text-xs font-medium transition">
                        {{ __('borrower.payments_page.create.bank_transfer') }}
                    </div>
                </label>
            @endif
        </div>
        <p class="text-xs mt-2" :class="mobileAllowed ? 'text-emerald-700' : 'text-amber-700'"
           x-text="mobileAllowed ? @js(__('borrower.payments_page.create.mobile_allowed')) : @js($bankOnlyMessage)"></p>
    </div>

    <div x-show="method === 'mobile_money'" x-cloak class="space-y-3">
        @if (! empty($mobileDetails['number']))
            <div class="rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-3 text-xs text-sky-900">
                <p class="font-semibold">{{ __('borrower.membership.pay_to', ['provider' => $mobileDetails['provider'] ?? __('borrower.membership.mobile_money')]) }}</p>
                <p class="font-mono mt-1">{{ $mobileDetails['number'] }}</p>
                @if (! empty($mobileDetails['instructions']))
                    <p class="mt-2 text-sky-800">{{ $mobileDetails['instructions'] }}</p>
                @endif
            </div>
        @endif
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.payments_page.create.mobile_number_label') }}</label>
            <input type="text" name="{{ $mobileField }}" placeholder="255712345678"
                   @if($formId) form="{{ $formId }}" @endif
                   class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
            <p class="text-xs text-gray-500 mt-2">{{ __('borrower.membership.mobile_ussd_hint') }}</p>
        </div>
    </div>

    <div x-show="method === 'bank_transfer'" x-cloak>
        @if (! empty($bankDetails))
            <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-4 text-sm space-y-2">
                @foreach ($bankDetails as $key => $value)
                    @if (filled($value) && ! is_array($value))
                        <div class="flex justify-between gap-3"><span class="text-gray-500 capitalize">{{ str_replace('_', ' ', $key) }}</span><span class="font-semibold text-right">{{ $value }}</span></div>
                    @endif
                @endforeach
            </div>
            <p class="text-xs text-gray-500 mt-3">{{ __('borrower.membership.bank_hint') }}</p>
        @endif
    </div>

    {{ $slot }}
</div>
