@props([
    'amount' => 0,
    'formId' => null,
    'methodField' => 'payment_method',
    'mobileField' => 'mobile_number',
    'mobileValue' => 'mobile_money',
    'bankValue' => 'bank_transfer',
    'defaultMethod' => null,
    'mobileThreshold' => null,
    'mobileDetails' => [],
    'bankDetails' => [],
    'bankAccounts' => [],
    'bankReference' => null,
    'showMobile' => true,
    'showBank' => true,
    'mobileInputValue' => null,
    'countryCode' => null,
])

@php
    $threshold = $mobileThreshold ?? payment_mobile_money_threshold();
    $defaultMethod = $defaultMethod ?? (old($methodField) ?: $mobileValue);
    $bankOnlyMessage = __('borrower.payments_page.create.bank_only', ['threshold' => format_money($threshold)]);
    $mobileInputValue = $mobileInputValue ?? old($mobileField);
    $countryCode = $countryCode
        ? strtoupper((string) $countryCode)
        : strtoupper((string) (auth()->user()?->customer?->country_code ?? 'TZ'));
@endphp

<div x-data="{
    amount: {{ (int) $amount }},
    method: @js($defaultMethod),
    threshold: {{ (int) $threshold }},
    get mobileAllowed() { return !this.threshold || this.amount <= this.threshold; },
}"
     x-init="
        if (!mobileAllowed && method === @js($mobileValue)) { method = @js($bankValue); }
        const form = $el.closest('form');
        const amountInput = form?.querySelector('input[name=amount]');
        if (amountInput) {
            const syncAmount = () => {
                const next = Number(amountInput.value || 0);
                if (!Number.isNaN(next)) {
                    amount = next;
                    if (!mobileAllowed && method === @js($mobileValue)) { method = @js($bankValue); }
                }
            };
            amountInput.addEventListener('input', syncAmount);
            amountInput.addEventListener('change', syncAmount);
            syncAmount();
        }
     "
     class="space-y-4">
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-2">{{ __('borrower.payments_page.create.method_label') }}</label>
        <div class="grid grid-cols-2 gap-2">
            @if ($showMobile)
                <label class="cursor-pointer" x-show="mobileAllowed">
                    <input type="radio" name="{{ $methodField }}" value="{{ $mobileValue }}" class="sr-only peer" x-model="method" @if($formId) form="{{ $formId }}" @endif required>
                    <div class="rounded-xl border-2 border-gray-200 peer-checked:border-brand peer-checked:bg-brand-muted/50 px-3 py-3 text-center text-xs font-medium transition">
                        {{ __('borrower.payments_page.create.mobile_money') }}
                    </div>
                </label>
            @endif
            @if ($showBank)
                <label class="cursor-pointer" :class="!mobileAllowed && 'col-span-2'">
                    <input type="radio" name="{{ $methodField }}" value="{{ $bankValue }}" class="sr-only peer" x-model="method" @if($formId) form="{{ $formId }}" @endif required>
                    <div class="rounded-xl border-2 border-gray-200 peer-checked:border-brand peer-checked:bg-brand-muted/50 px-3 py-3 text-center text-xs font-medium transition">
                        {{ __('borrower.payments_page.create.bank_transfer') }}
                    </div>
                </label>
            @endif
        </div>
        <p x-show="!mobileAllowed" x-cloak class="text-xs mt-2 text-amber-700"
           x-text="@js($bankOnlyMessage)"></p>
    </div>

    <div x-show="method === @js($mobileValue)" x-cloak class="space-y-3">
        @if (! empty($mobileDetails['number']))
            <div class="rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-3 text-xs text-sky-900">
                <p class="font-semibold">{{ __('borrower.membership.pay_to', ['provider' => $mobileDetails['provider'] ?? __('borrower.membership.mobile_money')]) }}</p>
                <p class="font-mono mt-1">{{ $mobileDetails['number'] }}</p>
                @if (! empty($mobileDetails['instructions']))
                    <p class="mt-2 text-sky-800">{{ $mobileDetails['instructions'] }}</p>
                @endif
            </div>
        @endif
        <x-site.phone-input
            :name="$mobileField"
            :label="__('borrower.payments_page.create.mobile_number_label')"
            :value="$mobileInputValue"
            :locked-country="$countryCode"
            :form="$formId"
            :show-errors="false"
            input-class="flex-1 rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-brand"
        />
        <div>
            <p class="block text-xs font-medium text-gray-600 mb-2">{{ __('borrower.payment_waiting.wallet_label') }}</p>
            <div class="grid grid-cols-2 gap-2">
                <label class="cursor-pointer">
                    <input type="radio" name="operator" value="" class="sr-only peer" @if($formId) form="{{ $formId }}" @endif {{ old('operator', '') === '' ? 'checked' : '' }}>
                    <div class="rounded-xl border-2 border-gray-200 peer-checked:border-brand peer-checked:bg-brand-muted/50 px-3 py-2.5 text-center text-[11px] font-medium leading-tight transition">
                        {{ __('borrower.payment_waiting.wallet_auto') }}
                    </div>
                </label>
                @foreach (['tigopesa' => 'wallet_mixx', 'mpesa' => 'wallet_mpesa', 'airtel' => 'wallet_airtel', 'halopesa' => 'wallet_halo'] as $code => $key)
                    <label class="cursor-pointer">
                        <input type="radio" name="operator" value="{{ $code }}" class="sr-only peer" @if($formId) form="{{ $formId }}" @endif {{ old('operator') === $code ? 'checked' : '' }}>
                        <div class="rounded-xl border-2 border-gray-200 peer-checked:border-brand peer-checked:bg-brand-muted/50 px-3 py-2.5 text-center text-[11px] font-medium leading-tight transition">
                            {{ __('borrower.payment_waiting.'.$key) }}
                        </div>
                    </label>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-gray-500">{{ __('borrower.payment_waiting.wallet_help') }}</p>
        </div>
    </div>

    <div x-show="method === @js($bankValue)" x-cloak class="space-y-3">
        @if (! empty($bankDetails))
            <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-4 text-sm space-y-2">
                @foreach ($bankDetails as $key => $value)
                    @if (filled($value) && ! is_array($value))
                        <div class="flex justify-between gap-3"><span class="text-gray-500 capitalize">{{ str_replace('_', ' ', $key) }}</span><span class="font-semibold text-right">{{ $value }}</span></div>
                    @endif
                @endforeach
            </div>
        @elseif (! empty($bankAccounts))
            <div class="rounded-xl bg-brand-muted/30 ring-1 ring-brand/15 p-4 text-sm">
                <p class="font-semibold text-brand mb-2">{{ __('borrower.membership.bank_instructions_title') }}</p>
                @if ($bankReference)
                    <p class="text-gray-700 text-xs mb-3">{{ __('borrower.membership.bank_reference_hint', ['ref' => $bankReference]) }}</p>
                @endif
                @foreach ($bankAccounts as $acct)
                    @php $acct = is_array($acct) ? $acct : []; @endphp
                    <div class="mb-2 last:mb-0">
                        <p class="font-medium">{{ $acct['bank'] ?? $acct['label'] ?? '' }}</p>
                        <p class="text-xs text-gray-700">
                            {{ $acct['account_name'] ?? '' }}
                            @if (! empty($acct['account_number'])) · {{ $acct['account_number'] }} @endif
                            @if (! empty($acct['branch'])) · {{ $acct['branch'] }} @endif
                        </p>
                        @if (! empty($acct['instructions']))
                            <p class="text-xs text-gray-600 mt-1">{{ $acct['instructions'] }}</p>
                        @endif
                    </div>
                @endforeach
                <p class="mt-3 text-xs text-amber-800 font-medium">⏳ {{ __('borrower.membership.bank_waiting_hint') }}</p>
            </div>
        @endif
        @if (empty($bankDetails) && empty($bankAccounts))
            <p class="text-xs text-gray-500">{{ __('borrower.membership.bank_hint') }}</p>
        @endif

        {{ $slot }}
    </div>
</div>
